<?php
// 新版 Workerman WS 服务器（暗骰+跟注/弃权，最多 5 颗骰）
// 依赖：composer require workerman/workerman

date_default_timezone_set('Asia/Shanghai');

use Workerman\Worker;
use Workerman\Timer;
use Workerman\Connection\TcpConnection;

require __DIR__ . '/vendor/autoload.php';

$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$logFile = $logDir . '/workerman_chat_server2_' . date('Y-m-d') . '.log';
$wsLog = function (string $msg) use ($logFile) {
    error_log(date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL, 3, $logFile);
};

$worker = new Worker('websocket://0.0.0.0:2346');
Worker::$stdoutFile = $logFile;
Worker::$logFile = $logFile;
$worker->count = 1;
$worker->name = 'mars-duel-v2';

$roomState = [];
$userConnections = [];

$settleApi = getenv('MARS_DUEL_API') ?: 'http://127.0.0.1:8000/ajax.php';
$settleToken = getenv('MARS_DUEL_TOKEN') ?: '';

$postJson = function (array $payload) use ($settleApi, $settleToken, $wsLog) {
    $payload['token'] = $settleToken;
    $wsLog('API req ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
    $resp = @file_get_contents($settleApi, false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($payload),
            'timeout' => 5,
        ],
    ]));
    if ($resp === false) {
        $wsLog('API http failed');
        return ['ret' => 1, 'msg' => 'http failed'];
    }
    // 详细打印响应内容（包括不可见字符）
    $respHex = '';
    for ($i = 0; $i < min(strlen($resp), 200); $i++) {
        $c = $resp[$i];
        if (ctype_print($c) || $c === "\n" || $c === "\r" || $c === "\t") {
            $respHex .= $c;
        } else {
            $respHex .= sprintf('\\x%02x', ord($c));
        }
    }
    $wsLog('API resp (length=' . strlen($resp) . ', first 200 bytes): ' . $respHex);
    $wsLog('API resp (raw substr 0-500): ' . substr($resp, 0, 500));
    $data = json_decode($resp, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        $wsLog('API json decode failed: ' . json_last_error_msg());
        $wsLog('API resp full content (hex): ' . bin2hex(substr($resp, 0, 1000)));
        return ['ret' => 1, 'msg' => 'invalid json: ' . json_last_error_msg()];
    }
    return $data ?? ['ret' => 1, 'msg' => 'invalid json'];
};

$broadcastRoom = function (string $room, string $payload) use (&$worker) {
    foreach ($worker->connections as $conn) {
        if (($conn->room ?? 'global') === $room) $conn->send($payload);
    }
};

$clearTimers = function (string $room) use (&$roomState) {
    if (!isset($roomState[$room]['timers'])) return;
    foreach ($roomState[$room]['timers'] as $t) Timer::del($t);
    $roomState[$room]['timers'] = [];
};

$countDice = function (string $room, string $pk) use (&$roomState) {
    return isset($roomState[$room]['dice_log'][$pk]) ? count($roomState[$room]['dice_log'][$pk]) : 0;
};

$settleDuel = function (string $room, ?string $winnerKey, bool $isFold = false) use (&$roomState, $broadcastRoom, $postJson) {
    if (($roomState[$room]['duel_settled'] ?? false) === true) return;
    $roomState[$room]['duel_settled'] = true;
    $sum1 = array_sum(array_column($roomState[$room]['dice_log']['p1'] ?? [], 'val'));
    $sum2 = array_sum(array_column($roomState[$room]['dice_log']['p2'] ?? [], 'val'));
    if ($winnerKey === null) {
        if ($sum1 > $sum2) $winnerKey = 'p1';
        elseif ($sum2 > $sum1) $winnerKey = 'p2';
    }
    $winnerName = $winnerKey ? ($roomState[$room][$winnerKey] ?? null) : null;

    $settleResp = null;
    if (str_starts_with($room, 'mars-table-')) {
        $tableId = intval(substr($room, strlen('mars-table-')));
        $poolAmount = $roomState[$room]['pool_amount'] ?? 0; // 获取奖池总额
        $payload = [
            'action' => 'mars_duel_settle',
            'winner' => $winnerKey === 'p1' ? ($roomState[$room]['p1_id'] ?? null) : ($winnerKey === 'p2' ? ($roomState[$room]['p2_id'] ?? null) : 0),
            'table_id' => $tableId,
            'duel_id' => $roomState[$room]['duel_id'] ?? '',
            'p1_user_id' => $roomState[$room]['p1_id'] ?? null,
            'p2_user_id' => $roomState[$room]['p2_id'] ?? null,
            'pool_amount' => $poolAmount, // 传递奖池总额
        ];
        $settleResp = $postJson($payload);
    }

    $result = [
        'type' => 'duel_result',
        'room' => $room,
        'p1' => $roomState[$room]['p1'] ?? null,
        'p2' => $roomState[$room]['p2'] ?? null,
        'dice_log' => $roomState[$room]['dice_log'] ?? [],
        'sum1' => $sum1,
        'sum2' => $sum2,
        'winner' => $winnerName,
        'winner_key' => $winnerKey,
        'fold' => $isFold,
        'duel_id' => $roomState[$room]['duel_id'] ?? null,
    ];
    if ($settleResp && ($settleResp['ret'] ?? 1) === 0 && isset($settleResp['data'])) {
        $result['winner_bonus'] = $settleResp['data']['winner_bonus'] ?? 0;
        $result['p1_bonus'] = $settleResp['data']['p1_bonus'] ?? 0;
        $result['p2_bonus'] = $settleResp['data']['p2_bonus'] ?? 0;
    }
    $roomState[$room]['last_result'] = $result;
    $roomState[$room]['status'] = 'idle';
    $roomState[$room]['turn'] = null;
    $roomState[$room]['expected_roll'] = [];
    $roomState[$room]['pool_amount'] = 0; // 结算后重置奖池为0
    $broadcastRoom($room, json_encode($result, JSON_UNESCAPED_UNICODE));
};

// 先声明 $startRound，稍后定义
$startRound = null;

// 生成暗骰并扣款
$startDuel = function (string $room, string $starterTurn = 'p1') use (&$roomState, $broadcastRoom, $postJson, $clearTimers, $wsLog, &$startRound) {
    $st = $roomState[$room] ?? null;
    if (!$st || !($st['p1'] ?? null) || !($st['p2'] ?? null) || ($st['status'] ?? 'idle') !== 'idle') return;

    $clearTimers($room);
    $duelId = bin2hex(random_bytes(16));
    $roomState[$room]['duel_id'] = $duelId;
    $roomState[$room]['duel_settled'] = false;
    $roomState[$room]['starter'] = $starterTurn;
    $roomState[$room]['status'] = 'selecting_hidden';
    $roomState[$room]['round'] = 0;
    $roomState[$room]['hidden_pool'] = [];
    for ($i = 0; $i < 10; $i++) $roomState[$room]['hidden_pool'][] = random_int(1, 6);
    $roomState[$room]['hidden_selected'] = ['p1' => null, 'p2' => null];
    $roomState[$room]['hidden_val'] = ['p1' => null, 'p2' => null];
    $roomState[$room]['dice_log'] = ['p1' => [], 'p2' => []];
    $roomState[$room]['rolls'] = [];
    $roomState[$room]['decisions'] = ['p1' => null, 'p2' => null];
    $roomState[$room]['pool_amount'] = 0; // 奖池总额（开局扣款 + 所有跟注扣款）

    // 扣款（仅 mars-table-*）
    if (str_starts_with($room, 'mars-table-')) {
        $tableId = intval(substr($room, strlen('mars-table-')));
        // 获取桌子下注金额并缓存到房间状态
        $tableResp = $postJson([
            'action' => 'get_game_tables',
        ]);
        $betAmount = 0;
        $wsLog("查询桌子信息: tableId={$tableId}");
        if (($tableResp['ret'] ?? 1) === 0 && isset($tableResp['data'])) {
            $wsLog("桌子查询成功，数据: " . json_encode($tableResp['data'], JSON_UNESCAPED_UNICODE));
            foreach ($tableResp['data'] as $table) {
                if (intval($table['id'] ?? 0) === $tableId) {
                    $betAmount = intval($table['bet_amount'] ?? 0);
                    $roomState[$room]['bet_amount'] = $betAmount; // 缓存到房间状态
                    $wsLog("找到桌子 #{$tableId}, betAmount={$betAmount}");
                    break;
                }
            }
        } else {
            $wsLog("桌子查询失败: " . json_encode($tableResp, JSON_UNESCAPED_UNICODE));
        }
        
        // 如果下注金额为0，记录错误并取消对局
        if ($betAmount <= 0) {
            $wsLog("错误: 桌子 #{$tableId} 的下注金额为0或未找到，取消对局");
            $broadcastRoom($room, json_encode(['type' => 'system', 'text' => "桌子下注金额未设置，本局取消"], JSON_UNESCAPED_UNICODE));
            $roomState[$room]['status'] = 'idle';
            return;
        }
        
        $wsLog("开局扣款准备: betAmount={$betAmount}, tableId={$tableId}");
        foreach (['p1', 'p2'] as $pk) {
            $uid = $roomState[$room]["{$pk}_id"] ?? null;
            $uname = $roomState[$room][$pk] ?? '';
            if (!$uid) {
                $wsLog("错误: {$uname} 未提供用户ID，取消对局");
                $broadcastRoom($room, json_encode(['type' => 'system', 'text' => "{$uname} 未提供用户ID，扣款失败，本局取消"], JSON_UNESCAPED_UNICODE));
                $roomState[$room]['status'] = 'idle';
                return;
            }
            $wsLog("开局扣款: pk={$pk}, uid={$uid}, betAmount={$betAmount}, uname={$uname}");
            $resp = $postJson([
                'action' => 'mars_duel_bet',
                'user_id' => $uid,
                'table_id' => $tableId,
                'duel_id' => $duelId,
            ]);
            $wsLog("开局扣款响应: " . json_encode($resp, JSON_UNESCAPED_UNICODE));
            if (($resp['ret'] ?? 1) !== 0) {
                $msg = $resp['msg'] ?? '扣款失败';
                $wsLog("开局扣款失败: {$msg}");
                $broadcastRoom($room, json_encode(['type' => 'system', 'text' => "{$uname} 扣款失败：{$msg}，本局取消"], JSON_UNESCAPED_UNICODE));
                $roomState[$room]['status'] = 'idle';
                return;
            }
            // 将开局扣款加入奖池
            $roomState[$room]['pool_amount'] += $betAmount;
            $wsLog("开局扣款成功: pk={$pk}, 当前奖池=" . $roomState[$room]['pool_amount']);
        }
        // 广播奖池更新（开局后）
        $wsLog("开局后奖池总额: " . $roomState[$room]['pool_amount'] . ", betAmount={$betAmount}, 应该=" . ($betAmount * 2));
        $broadcastRoom($room, json_encode([
            'type' => 'pool_update',
            'pool_amount' => $roomState[$room]['pool_amount'],
        ], JSON_UNESCAPED_UNICODE));
    }

    $broadcastRoom($room, json_encode(['type' => 'system', 'text' => "本局开始！先手：" . ($starterTurn === 'p1' ? $st['p1'] : $st['p2'])], JSON_UNESCAPED_UNICODE));
    $broadcastRoom($room, json_encode([
        'type' => 'hidden_pool_generated',
        'room' => $room,
        'available_indexes' => range(1, 10),
        'selected_indexes' => [],
        'starter' => $starterTurn,
        'starter_timeout' => 5,
        'note' => '请选择一枚暗骰序号（1-10），点数服务器保密',
    ], JSON_UNESCAPED_UNICODE));
    
    // AI自动选择暗骰（延迟1-3秒，模拟真人思考）
    foreach (['p1', 'p2'] as $pk) {
        if (($roomState[$room]['is_ai'][$pk] ?? false)) {
            $delay = random_int(1, 3); // 1-3秒延迟
            Timer::add($delay, function () use (&$roomState, $room, $broadcastRoom, $startRound, $pk) {
                if (($roomState[$room]['status'] ?? '') !== 'selecting_hidden') return;
                if (($roomState[$room]['hidden_selected'][$pk] ?? null) !== null) return; // 已选择
                
                // 获取可用序号
                $selected = $roomState[$room]['hidden_selected'] ?? ['p1' => null, 'p2' => null];
                $available = [];
                for ($i = 1; $i <= 10; $i++) {
                    if (!in_array($i, array_filter($selected))) {
                        $available[] = $i;
                    }
                }
                if (empty($available)) return;
                
                // AI随机选择（模拟真人行为）
                $idx = $available[array_rand($available)];
                $roomState[$room]['hidden_selected'][$pk] = $idx;
                $val = $roomState[$room]['hidden_pool'][$idx - 1] ?? random_int(1, 6);
                $roomState[$room]['hidden_val'][$pk] = $val;
                $roomState[$room]['dice_log'][$pk][] = ['type' => 'hidden', 'val' => $val, 'idx' => $idx];
                
                // 广播选择结果
                $broadcastRoom($room, json_encode([
                    'type' => 'hidden_select_update',
                    'selected_indexes' => [
                        'p1' => $roomState[$room]['hidden_selected']['p1'] ?? null,
                        'p2' => $roomState[$room]['hidden_selected']['p2'] ?? null,
                    ],
                    'player' => $roomState[$room][$pk] ?? 'AI玩家',
                    'player_key' => $pk,
                    'auto' => false,
                ], JSON_UNESCAPED_UNICODE));
                
                // 检查是否双方都已选择
                if (($roomState[$room]['hidden_selected']['p1'] ?? null) !== null && ($roomState[$room]['hidden_selected']['p2'] ?? null) !== null) {
                    $startRound($room);
                }
            }, [], false);
        }
    }
    
    // 先手玩家5秒倒计时，超时自动随机选择
    $starterTimer = Timer::add(5, function () use (&$roomState, $room, $broadcastRoom, $startRound) {
        if (($roomState[$room]['status'] ?? '') !== 'selecting_hidden') return;
        $starter = $roomState[$room]['starter'] ?? 'p1';
        if (($roomState[$room]['hidden_selected'][$starter] ?? null) === null) {
            // 先手玩家还未选择，自动随机选择
            $selected = $roomState[$room]['hidden_selected'] ?? ['p1' => null, 'p2' => null];
            $available = [];
            for ($i = 1; $i <= 10; $i++) {
                if (!in_array($i, array_filter($selected))) {
                    $available[] = $i;
                }
            }
            if (!empty($available)) {
                $idx = $available[array_rand($available)];
                $roomState[$room]['hidden_selected'][$starter] = $idx;
                $val = $roomState[$room]['hidden_pool'][$idx - 1] ?? random_int(1, 6);
                $roomState[$room]['hidden_val'][$starter] = $val;
                $roomState[$room]['dice_log'][$starter][] = ['type' => 'hidden', 'val' => $val, 'idx' => $idx];
                
                // 广播选择结果
                $broadcastRoom($room, json_encode([
                    'type' => 'hidden_select_update',
                    'selected_indexes' => [
                        'p1' => $roomState[$room]['hidden_selected']['p1'] ?? null,
                        'p2' => $roomState[$room]['hidden_selected']['p2'] ?? null,
                    ],
                    'player' => $roomState[$room][$starter] ?? 'guest',
                    'player_key' => $starter,
                    'auto' => true,
                ], JSON_UNESCAPED_UNICODE));
                
                // 检查是否双方都已选择
                if (($roomState[$room]['hidden_selected']['p1'] ?? null) !== null && ($roomState[$room]['hidden_selected']['p2'] ?? null) !== null) {
                    $startRound($room);
                }
            }
        }
    }, [], false);
    $roomState[$room]['timers'][] = $starterTimer;
};

// 进入下一轮明骰（重新赋值，覆盖之前的 null）
$startRound = function (string $room) use (&$roomState, $broadcastRoom) {
    $roomState[$room]['round'] = ($roomState[$room]['round'] ?? 0) + 1;
    $roomState[$room]['status'] = 'playing';
    $roomState[$room]['rolls'] = [];
    $first = $roomState[$room]['starter'] ?? 'p1';
    $roomState[$room]['turn'] = $first;
    $broadcastRoom($room, json_encode([
        'type' => 'roll_request',
        'room' => $room,
        'player' => $roomState[$room][$first] ?? '',
        'turn' => $first,
        'timeout' => 30,
        'round' => $roomState[$room]['round'],
    ], JSON_UNESCAPED_UNICODE));
    
    // AI自动投掷骰子（延迟2-5秒，模拟真人思考）
    if (($roomState[$room]['is_ai'][$first] ?? false)) {
        $delay = random_int(2, 5);
        Timer::add($delay, function () use (&$roomState, $room, $broadcastRoom, $first) {
            if (($roomState[$room]['status'] ?? '') !== 'playing') return;
            if (($roomState[$room]['turn'] ?? null) !== $first) return;
            if (isset($roomState[$room]['rolls'][$first])) return; // 已投掷
            
            // AI投掷骰子
            $roll = [random_int(1, 6)];
            $sum = $roll[0];
            $roomState[$room]['dice_log'][$first][] = ['type' => 'open', 'val' => $sum, 'roll' => $roll];
            $roomState[$room]['rolls'][$first] = $roll;
            
            $broadcastRoom($room, json_encode([
                'type' => 'roll_done',
                'room' => $room,
                'player' => $roomState[$room][$first] ?? 'AI玩家',
                'turn' => $first,
                'roll' => $roll,
                'auto' => false,
            ], JSON_UNESCAPED_UNICODE));
            
            // 切换到下一个玩家或进入决策阶段
            $other = $first === 'p1' ? 'p2' : 'p1';
            if (!isset($roomState[$room]['rolls'][$other])) {
                $roomState[$room]['turn'] = $other;
                $broadcastRoom($room, json_encode([
                    'type' => 'roll_request',
                    'room' => $room,
                    'player' => $roomState[$room][$other] ?? '',
                    'turn' => $other,
                    'timeout' => 30,
                ], JSON_UNESCAPED_UNICODE));
            } else {
                $roomState[$room]['status'] = 'decision';
                $roomState[$room]['decisions'] = ['p1' => null, 'p2' => null];
                $broadcastRoom($room, json_encode([
                    'type' => 'decision_request',
                    'room' => $room,
                    'timeout' => 15,
                ], JSON_UNESCAPED_UNICODE));
            }
        }, [], false);
    }
};

$worker->onConnect = function (TcpConnection $connection) use (&$roomState, &$userConnections, $broadcastRoom, $startDuel, $wsLog) {
    $connection->onWebSocketConnect = function (TcpConnection $connection, $request = null) use (&$roomState, &$userConnections, $broadcastRoom, $startDuel, $wsLog) {
        $queryString = ($request !== null && method_exists($request, 'queryString')) ? $request->queryString() : ($_SERVER['QUERY_STRING'] ?? '');
        parse_str($queryString, $params);
        $user = urldecode($params['user'] ?? ('guest-' . substr(md5($connection->id . microtime(true)), 0, 6)));
        $uid = intval($params['uid'] ?? 0);
        $room = $params['room'] ?? 'global';
        $role = $params['role'] ?? 'spectator';
        $seat = intval($params['seat'] ?? 0);
        $tableId = intval($params['table_id'] ?? 0);
        $ownerId = intval($params['owner_id'] ?? 0);

        if (!isset($roomState[$room])) {
            $roomState[$room] = [
                'p1' => null, 'p2' => null,
                'p1_id' => null, 'p2_id' => null,
                'is_ai' => ['p1' => false, 'p2' => false],
                'spectators' => 0, 'spectators_list' => [],
                'status' => 'idle', 'last_result' => null,
                'table_id' => $tableId ?: null, 'owner_id' => $ownerId ?: null,
            ];
        }
        // 每次连接都同步 table_id / owner_id，避免后加入的房主无法踢人
        if ($tableId > 0) {
            $roomState[$room]['table_id'] = $tableId;
        }
        if ($ownerId > 0) {
            $roomState[$room]['owner_id'] = $ownerId;
        }

        $assignedSeat = null;
        
        // 优先检查：如果用户ID匹配之前座位上的玩家ID，自动恢复到原座位（断线重连）
        // 这个检查不依赖于 role，确保断线重连时能恢复座位
        // 使用严格比较，确保 uid 不为 0 且匹配
        if ($uid > 0) {
            // 使用 == 比较，允许类型转换（字符串数字 == 整数）
            if (($roomState[$room]['p1_id'] ?? null) != null && intval($roomState[$room]['p1_id']) == intval($uid)) {
                // p1座位是当前用户，恢复座位（即使玩家名不同也恢复）
                $assignedSeat = 1;
                $role = 'player'; // 强制设置为玩家角色
                $wsLog("用户 {$user} (uid={$uid}) 断线重连，恢复到座位1 (p1_id=" . ($roomState[$room]['p1_id'] ?? 'null') . ")");
            } elseif (($roomState[$room]['p2_id'] ?? null) != null && intval($roomState[$room]['p2_id']) == intval($uid)) {
                // p2座位是当前用户，恢复座位（即使玩家名不同也恢复）
                $assignedSeat = 2;
                $role = 'player'; // 强制设置为玩家角色
                $wsLog("用户 {$user} (uid={$uid}) 断线重连，恢复到座位2 (p2_id=" . ($roomState[$room]['p2_id'] ?? 'null') . ")");
            }
        }
        
        // 如果没有自动恢复，且 role 是 player，按原有逻辑分配座位
        if ($assignedSeat === null && $role === 'player') {
            if ($seat === 1 && !$roomState[$room]['p1']) $assignedSeat = 1;
            elseif ($seat === 2 && !$roomState[$room]['p2']) $assignedSeat = 2;
            elseif (!$roomState[$room]['p1']) $assignedSeat = 1;
            elseif (!$roomState[$room]['p2']) $assignedSeat = 2;
            else $role = 'spectator';
        }

        if ($role === 'spectator') {
            $roomState[$room]['spectators']++;
            if (!in_array($user, $roomState[$room]['spectators_list'])) $roomState[$room]['spectators_list'][] = $user;
        } else {
            if ($assignedSeat === 1) {
                $roomState[$room]['p1'] = $user;
                // 如果 p1_id 已存在（对局中断线重连），保留原值；否则设置新值
                if (($roomState[$room]['p1_id'] ?? null) === null) {
                    $roomState[$room]['p1_id'] = $uid ?: null;
                } elseif ($uid > 0) {
                    // 如果提供了新的 uid，更新它（确保用户ID正确）
                    $roomState[$room]['p1_id'] = $uid;
                }
            } elseif ($assignedSeat === 2) {
                $roomState[$room]['p2'] = $user;
                // 如果 p2_id 已存在（对局中断线重连），保留原值；否则设置新值
                if (($roomState[$room]['p2_id'] ?? null) === null) {
                    $roomState[$room]['p2_id'] = $uid ?: null;
                } elseif ($uid > 0) {
                    // 如果提供了新的 uid，更新它（确保用户ID正确）
                    $roomState[$room]['p2_id'] = $uid;
                }
            }
        }

        $connection->room = $room;
        $connection->user = $user;
        $connection->uid = $uid;
        $connection->role = $role;
        $connection->seat = $assignedSeat;
        
        // 如果成功恢复了座位，发送座位分配消息
        if ($assignedSeat !== null) {
            $connection->send(json_encode([
                'type' => 'seat_assigned',
                'seat' => $assignedSeat,
                'reconnected' => true, // 标记为断线重连恢复
            ], JSON_UNESCAPED_UNICODE));
        }

        $userConnections[$room][$user] = $connection;
        $connection->send(json_encode(['type' => 'system', 'text' => "欢迎 {$user} 进入 {$room}（{$role}" . ($assignedSeat ? "#{$assignedSeat}" : '') . "）"], JSON_UNESCAPED_UNICODE));
        $broadcastRoom($room, json_encode(['type' => 'system', 'text' => "{$user} 加入了房间"], JSON_UNESCAPED_UNICODE));

        $broadcastRoom($room, json_encode([
            'type' => 'room_update',
            'room' => $room,
            'players' => ['p1' => $roomState[$room]['p1'], 'p2' => $roomState[$room]['p2']],
            'spectators' => $roomState[$room]['spectators'],
            'spectators_list' => $roomState[$room]['spectators_list'],
            'status' => $roomState[$room]['status'],
            'last_result' => $roomState[$room]['last_result'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
    };
};

$worker->onMessage = function (TcpConnection $connection, $data) use (&$roomState, &$userConnections, $broadcastRoom, $startDuel, $postJson, $clearTimers, $countDice, $settleDuel, $startRound, $wsLog) {
    $room = $connection->room ?? 'global';
    $raw = trim((string)$data);
    if ($raw === '') return;
    $decoded = json_decode($raw, true);
    $isJson = is_array($decoded);
    // 文本兜底：非 JSON 也当作聊天
    if (!$isJson) {
        $text = trim($raw);
        if ($text !== '') {
            $broadcastRoom($room, json_encode([
                'type' => 'message',
                'user' => $connection->user ?? 'guest',
                'text' => htmlspecialchars($text, ENT_QUOTES),
                'time' => date('H:i:s'),
            ], JSON_UNESCAPED_UNICODE));
        }
        return;
    }

    // 同步状态（用于断线重连）
    if (($decoded['type'] ?? '') === 'sync_state') {
        $st = $roomState[$room] ?? null;
        if (!$st) {
            $connection->send(json_encode([
                'type' => 'sync_state',
                'room' => $room,
                'status' => 'idle',
                'players' => ['p1' => null, 'p2' => null],
                'spectators' => 0,
            ], JSON_UNESCAPED_UNICODE));
            return;
        }
        
        $seat = $connection->seat ?? null;
        $uid = $connection->uid ?? null;
        $isPlayer = in_array($seat, [1, 2], true);
        
        // 构建完整状态
        $state = [
            'type' => 'sync_state',
            'room' => $room,
            'status' => $st['status'] ?? 'idle',
            'players' => [
                'p1' => $st['p1'] ?? null,
                'p2' => $st['p2'] ?? null,
            ],
            'p1_id' => $st['p1_id'] ?? null,
            'p2_id' => $st['p2_id'] ?? null,
            'spectators' => $st['spectators'] ?? 0,
            'spectators_list' => $st['spectators_list'] ?? [],
            'is_ai' => $st['is_ai'] ?? ['p1' => false, 'p2' => false],
            'last_result' => $st['last_result'] ?? null,
        ];
        
        // 如果在对局中，返回详细状态
        if (in_array($st['status'] ?? 'idle', ['selecting_hidden', 'dueling', 'playing', 'decision'])) {
            $state['duel_id'] = $st['duel_id'] ?? null;
            $state['starter'] = $st['starter'] ?? 'p1';
            $state['round'] = $st['round'] ?? 0;
            $state['turn'] = $st['turn'] ?? null;
            $state['hidden_selected'] = $st['hidden_selected'] ?? ['p1' => null, 'p2' => null];
            $state['dice_log'] = $st['dice_log'] ?? ['p1' => [], 'p2' => []];
            $state['pool_amount'] = $st['pool_amount'] ?? 0;
            $state['decisions'] = $st['decisions'] ?? ['p1' => null, 'p2' => null];
            $state['rolls'] = $st['rolls'] ?? []; // 添加 rolls 状态，用于判断是否已投掷
            
            // 如果正在选择暗骰，返回可用序号
            if (($st['status'] ?? '') === 'selecting_hidden') {
                $selected = $st['hidden_selected'] ?? ['p1' => null, 'p2' => null];
                $available = [];
                for ($i = 1; $i <= 10; $i++) {
                    if (!in_array($i, array_filter($selected))) {
                        $available[] = $i;
                    }
                }
                $state['available_indexes'] = $available;
                $state['selected_indexes'] = $selected;
                $state['starter_timeout'] = 5; // 默认5秒
            }
            
            // 如果正在对局中，返回当前回合信息
            if (($st['status'] ?? '') === 'dueling') {
                $state['expected_roll'] = $st['expected_roll'] ?? [];
            }
            
            // 如果正在投掷阶段（playing），且轮到当前玩家，发送 roll_request 消息恢复投掷提示
            if (($st['status'] ?? '') === 'playing' && isset($st['turn'])) {
                $currentTurn = $st['turn'];
                $currentSeat = $connection->seat ?? null;
                $currentPk = $currentSeat === 1 ? 'p1' : ($currentSeat === 2 ? 'p2' : null);
                
                // 如果座位未恢复，尝试根据用户ID匹配座位（断线重连场景）
                if (!$currentPk && ($connection->uid ?? 0) > 0) {
                    $uid = $connection->uid;
                    if (($st['p1_id'] ?? null) == $uid) {
                        $currentPk = 'p1';
                        $currentSeat = 1;
                        $connection->seat = 1;
                        $connection->role = 'player';
                        // 更新房间状态
                        $st['p1'] = $connection->user ?? 'guest';
                        $st['p1_id'] = $uid;
                        $roomState[$room]['p1'] = $connection->user ?? 'guest';
                        $roomState[$room]['p1_id'] = $uid;
                        $wsLog("sync_state: 用户 {$connection->user} (uid={$uid}) 通过用户ID匹配恢复座位1");
                    } elseif (($st['p2_id'] ?? null) == $uid) {
                        $currentPk = 'p2';
                        $currentSeat = 2;
                        $connection->seat = 2;
                        $connection->role = 'player';
                        // 更新房间状态
                        $st['p2'] = $connection->user ?? 'guest';
                        $st['p2_id'] = $uid;
                        $roomState[$room]['p2'] = $connection->user ?? 'guest';
                        $roomState[$room]['p2_id'] = $uid;
                        $wsLog("sync_state: 用户 {$connection->user} (uid={$uid}) 通过用户ID匹配恢复座位2");
                    }
                }
                
                $rolls = $st['rolls'] ?? [];
                
                // 如果轮到当前玩家，且还没有投掷，发送 roll_request 消息
                if ($currentPk === $currentTurn && !isset($rolls[$currentPk])) {
                    // 延迟一点发送，确保 sync_state 消息先到达
                    Timer::add(0.1, function () use ($connection, $room, $st, $currentPk, $currentTurn) {
                        $connection->send(json_encode([
                            'type' => 'roll_request',
                            'room' => $room,
                            'player' => $st[$currentPk] ?? '',
                            'turn' => $currentTurn,
                            'timeout' => 30,
                            'round' => $st['round'] ?? 1,
                        ], JSON_UNESCAPED_UNICODE));
                    }, [], false);
                }
            }
        }
        
        $connection->send(json_encode($state, JSON_UNESCAPED_UNICODE));
        return;
    }

    // 开始
    if (($decoded['type'] ?? '') === 'start') {
        $seat = $connection->seat ?? null;
        if (!in_array($seat, [1, 2], true)) return;
        if (($roomState[$room]['status'] ?? 'idle') !== 'idle') return;
        if (empty($roomState[$room]['p1']) || empty($roomState[$room]['p2'])) return;
        $readyKey = $seat === 1 ? 'p1' : 'p2';
        
        // 检查余额是否足够（仅对真人玩家，AI不需要检查）
        // 需要支持：开局扣款（1次）+ 四次跟注扣款（4次）= 5倍下注金额
        if (!($roomState[$room]['is_ai'][$readyKey] ?? false)) {
            $uid = $roomState[$room]["{$readyKey}_id"] ?? null;
            if ($uid && str_starts_with($room, 'mars-table-')) {
                $tableId = intval(substr($room, strlen('mars-table-')));
                
                // 获取桌子下注金额
                $betAmount = $roomState[$room]['bet_amount'] ?? 0;
                if ($betAmount <= 0) {
                    $tableResp = $postJson([
                        'action' => 'get_game_tables',
                    ]);
                    if (($tableResp['ret'] ?? 1) === 0 && isset($tableResp['data'])) {
                        foreach ($tableResp['data'] as $table) {
                            if (intval($table['id'] ?? 0) === $tableId) {
                                $betAmount = intval($table['bet_amount'] ?? 0);
                                $roomState[$room]['bet_amount'] = $betAmount;
                                break;
                            }
                        }
                    }
                }
                
                // 查询用户当前余额
                $checkResp = $postJson([
                    'action' => 'get_user_bonus',
                    'user_id' => $uid,
                ]);
                $currentBalance = ($checkResp['ret'] ?? 1) === 0 ? floatval($checkResp['data']['bonus'] ?? 0) : 0;
                
                // 需要5倍下注金额：开局1次 + 跟注4次
                $requiredAmount = $betAmount * 5;
                
                // 如果余额不足，返回错误
                if ($betAmount > 0 && $currentBalance < $requiredAmount) {
                    $connection->send(json_encode([
                        'type' => 'error',
                        'text' => '余额不足，无法开始对局。需要 ' . number_format($requiredAmount, 1) . ' 魔力值（开局 ' . number_format($betAmount, 1) . ' + 四次跟注 ' . number_format($betAmount * 4, 1) . '），当前余额：' . number_format($currentBalance, 1),
                    ], JSON_UNESCAPED_UNICODE));
                    return;
                }
            }
        }
        
        $roomState[$room]['ready'][$readyKey] = true;
        // 广播玩家已准备的消息
        $broadcastRoom($room, json_encode([
            'type' => 'system',
            'text' => ($connection->user ?? 'guest') . ' 已准备，等待对方开始',
        ], JSON_UNESCAPED_UNICODE));
        
        // 如果另一方是AI，自动让AI也准备
        $otherKey = $readyKey === 'p1' ? 'p2' : 'p1';
        if (($roomState[$room]['is_ai'][$otherKey] ?? false) && !($roomState[$room]['ready'][$otherKey] ?? false)) {
            // AI延迟1-3秒后自动准备（模拟真人思考）
            $delay = random_int(1, 3);
            Timer::add($delay, function () use (&$roomState, $room, $broadcastRoom, $otherKey, $startDuel) {
                if (($roomState[$room]['status'] ?? 'idle') !== 'idle') return;
                if (($roomState[$room]['ready'][$otherKey] ?? false)) return;
                
                $roomState[$room]['ready'][$otherKey] = true;
                $broadcastRoom($room, json_encode([
                    'type' => 'system',
                    'text' => ($roomState[$room][$otherKey] ?? 'AI玩家') . ' 已准备，等待对方开始',
                ], JSON_UNESCAPED_UNICODE));
                
                // 检查是否双方都已准备
                if (($roomState[$room]['ready']['p1'] ?? false) && ($roomState[$room]['ready']['p2'] ?? false)) {
                    $starter = $roomState[$room]['starter'] ?? $otherKey;
                    $roomState[$room]['ready'] = ['p1' => false, 'p2' => false];
                    $roomState[$room]['starter'] = $starter;
                    $startDuel($room, $starter);
                }
            }, [], false);
        }
        
        if (($roomState[$room]['ready']['p1'] ?? false) && ($roomState[$room]['ready']['p2'] ?? false)) {
            $starter = $roomState[$room]['starter'] ?? $readyKey;
            $roomState[$room]['ready'] = ['p1' => false, 'p2' => false];
            $roomState[$room]['starter'] = $starter;
            $startDuel($room, $starter);
        }
        return;
    }

    // 选择暗骰序号
    if (($decoded['type'] ?? '') === 'hidden_select') {
        $seat = $connection->seat ?? null;
        $pk = $seat === 1 ? 'p1' : ($seat === 2 ? 'p2' : null);
        
        // 如果座位未恢复，尝试根据用户ID匹配座位（断线重连场景）
        if (!$pk && ($connection->uid ?? 0) > 0) {
            $uid = $connection->uid;
            if ($roomState[$room]['p1_id'] == $uid) {
                $pk = 'p1';
                $seat = 1;
                $connection->seat = 1;
                $connection->role = 'player';
                // 更新房间状态
                $roomState[$room]['p1'] = $connection->user ?? 'guest';
                $roomState[$room]['p1_id'] = $uid;
                $wsLog("hidden_select: 用户 {$connection->user} (uid={$uid}) 通过用户ID匹配恢复座位1");
                // 发送座位恢复消息
                $connection->send(json_encode([
                    'type' => 'seat_assigned',
                    'seat' => 1,
                    'reconnected' => true,
                ], JSON_UNESCAPED_UNICODE));
            } elseif ($roomState[$room]['p2_id'] == $uid) {
                $pk = 'p2';
                $seat = 2;
                $connection->seat = 2;
                $connection->role = 'player';
                // 更新房间状态
                $roomState[$room]['p2'] = $connection->user ?? 'guest';
                $roomState[$room]['p2_id'] = $uid;
                $wsLog("hidden_select: 用户 {$connection->user} (uid={$uid}) 通过用户ID匹配恢复座位2");
                // 发送座位恢复消息
                $connection->send(json_encode([
                    'type' => 'seat_assigned',
                    'seat' => 2,
                    'reconnected' => true,
                ], JSON_UNESCAPED_UNICODE));
            }
        }
        
        if (!$pk) {
            $connection->send(json_encode(['type' => 'error', 'text' => '您不在座位上，无法选择暗骰'], JSON_UNESCAPED_UNICODE));
            return;
        }
        if (!isset($roomState[$room]['hidden_pool'])) return;
        $idx = intval($decoded['index'] ?? 0);
        if ($idx < 1 || $idx > 10) {
            $connection->send(json_encode(['type' => 'error', 'text' => '序号必须 1-10'], JSON_UNESCAPED_UNICODE));
            return;
        }
        if (($roomState[$room]['hidden_selected'][$pk] ?? null) !== null) {
            $connection->send(json_encode(['type' => 'error', 'text' => '已选过暗骰'], JSON_UNESCAPED_UNICODE));
            return;
        }
        $other = $pk === 'p1' ? 'p2' : 'p1';
        if (($roomState[$room]['hidden_selected'][$other] ?? null) === $idx) {
            $connection->send(json_encode(['type' => 'error', 'text' => '该序号已被对手选择'], JSON_UNESCAPED_UNICODE));
            return;
        }
        $roomState[$room]['hidden_selected'][$pk] = $idx;
        $val = $roomState[$room]['hidden_pool'][$idx - 1] ?? random_int(1, 6);
        $roomState[$room]['hidden_val'][$pk] = $val;
        $roomState[$room]['dice_log'][$pk][] = ['type' => 'hidden', 'val' => $val, 'idx' => $idx];
        
        // 取消先手玩家的倒计时（如果已选择且是先手玩家）
        if ($pk === ($roomState[$room]['starter'] ?? 'p1') && isset($roomState[$room]['timers'])) {
            // 只清除先手玩家的倒计时定时器（最后一个添加的）
            if (!empty($roomState[$room]['timers'])) {
                $lastTimer = array_pop($roomState[$room]['timers']);
                Timer::del($lastTimer);
            }
        }
        
        $connection->send(json_encode([
            'type' => 'hidden_select_ok',
            'index' => $idx,
            'player_key' => $pk,
        ], JSON_UNESCAPED_UNICODE));
        
        // 广播选择结果，让另一方知道哪些序号已被选择
        $broadcastRoom($room, json_encode([
            'type' => 'hidden_select_update',
            'selected_indexes' => [
                'p1' => $roomState[$room]['hidden_selected']['p1'] ?? null,
                'p2' => $roomState[$room]['hidden_selected']['p2'] ?? null,
            ],
            'player' => $connection->user ?? 'guest',
            'player_key' => $pk,
            'auto' => false,
        ], JSON_UNESCAPED_UNICODE));

        if (($roomState[$room]['hidden_selected']['p1'] ?? null) !== null && ($roomState[$room]['hidden_selected']['p2'] ?? null) !== null) {
            $startRound($room);
        }
        return;
    }

    // 明骰投掷
    if (($decoded['type'] ?? '') === 'roll') {
        $turn = $roomState[$room]['turn'] ?? null;
        $seat = $connection->seat ?? null;
        $pk = $seat === 1 ? 'p1' : ($seat === 2 ? 'p2' : null);
        if (!$pk || $turn !== $pk) return;
        $roll = [random_int(1, 6)]; // 改为一个骰子
        $sum = $roll[0]; // 总和就是单个骰子的值
        $roomState[$room]['dice_log'][$pk][] = ['type' => 'open', 'val' => $sum, 'roll' => $roll];
        $roomState[$room]['rolls'][$pk] = $roll;
        $broadcastRoom($room, json_encode([
            'type' => 'roll_done',
            'room' => $room,
            'player' => $connection->user ?? 'guest',
            'turn' => $pk,
            'roll' => $roll,
            'auto' => false,
        ], JSON_UNESCAPED_UNICODE));

        $other = $pk === 'p1' ? 'p2' : 'p1';
        if (!isset($roomState[$room]['rolls'][$other])) {
            $roomState[$room]['turn'] = $other;
            $broadcastRoom($room, json_encode([
                'type' => 'roll_request',
                'room' => $room,
                'player' => $roomState[$room][$other] ?? '',
                'turn' => $other,
                'timeout' => 30,
            ], JSON_UNESCAPED_UNICODE));
            
            // AI自动投掷（延迟2-5秒）
            if (($roomState[$room]['is_ai'][$other] ?? false)) {
                $delay = random_int(2, 5);
                Timer::add($delay, function () use (&$roomState, $room, $broadcastRoom, $other) {
                    if (($roomState[$room]['status'] ?? '') !== 'playing') return;
                    if (($roomState[$room]['turn'] ?? null) !== $other) return;
                    if (isset($roomState[$room]['rolls'][$other])) return;
                    
                    $roll = [random_int(1, 6)];
                    $sum = $roll[0];
                    $roomState[$room]['dice_log'][$other][] = ['type' => 'open', 'val' => $sum, 'roll' => $roll];
                    $roomState[$room]['rolls'][$other] = $roll;
                    
                    $broadcastRoom($room, json_encode([
                        'type' => 'roll_done',
                        'room' => $room,
                        'player' => $roomState[$room][$other] ?? 'AI玩家',
                        'turn' => $other,
                        'roll' => $roll,
                        'auto' => false,
                    ], JSON_UNESCAPED_UNICODE));
                    
                    // 进入决策阶段
                    $roomState[$room]['status'] = 'decision';
                    $roomState[$room]['decisions'] = ['p1' => null, 'p2' => null];
                    $broadcastRoom($room, json_encode([
                        'type' => 'decision_request',
                        'room' => $room,
                        'timeout' => 15,
                    ], JSON_UNESCAPED_UNICODE));
                }, [], false);
            }
        } else {
            $roomState[$room]['status'] = 'decision';
            $roomState[$room]['decisions'] = ['p1' => null, 'p2' => null];
            $broadcastRoom($room, json_encode([
                'type' => 'decision_request',
                'room' => $room,
                'timeout' => 15,
            ], JSON_UNESCAPED_UNICODE));
            
            // AI智能决策（延迟3-8秒，模拟思考）
            foreach (['p1', 'p2'] as $pk) {
                if (($roomState[$room]['is_ai'][$pk] ?? false)) {
                    $delay = random_int(3, 8);
                    Timer::add($delay, function () use (&$roomState, $room, $broadcastRoom, $settleDuel, $countDice, $startRound, &$clearTimers, $pk, $postJson, $wsLog) {
                        if (($roomState[$room]['status'] ?? '') !== 'decision') return;
                        if (($roomState[$room]['decisions'][$pk] ?? null) !== null) return; // 已决策
                        
                        // AI智能判断：基于明牌计算
                        $myDiceLog = $roomState[$room]['dice_log'][$pk] ?? [];
                        $otherKey = $pk === 'p1' ? 'p2' : 'p1';
                        $otherDiceLog = $roomState[$room]['dice_log'][$otherKey] ?? [];
                        
                        // 计算明牌总和（只计算open类型的骰子）
                        $myOpenSum = 0;
                        $otherOpenSum = 0;
                        foreach ($myDiceLog as $d) {
                            if (($d['type'] ?? '') === 'open') {
                                $myOpenSum += ($d['val'] ?? 0);
                            }
                        }
                        foreach ($otherDiceLog as $d) {
                            if (($d['type'] ?? '') === 'open') {
                                $otherOpenSum += ($d['val'] ?? 0);
                            }
                        }
                        
                        // 计算骰子数量
                        $myCount = count($myDiceLog);
                        $otherCount = count($otherDiceLog);
                        
                        // 智能决策逻辑（理智判断，基于分数）：
                        // 1. 已投掷5次，必须跟注到最后
                        // 2. 分数领先或持平，跟注
                        // 3. 稍微落后（落后3分以内），跟注（还有机会）
                        // 4. 明显落后（落后4-6分）且已投掷4次或以上，考虑放弃
                        // 5. 严重落后（落后7分以上）且已投掷3次或以上，放弃
                        // 6. 极度落后（落后10分以上），无论投掷次数，放弃
                        $choice = 'call'; // 默认跟注
                        
                        // 计算分数差距
                        $scoreDiff = $myOpenSum - $otherOpenSum;
                        
                        if ($myCount >= 5) {
                            // 已投掷5次，必须跟注到最后
                            $choice = 'call';
                        } elseif ($scoreDiff >= 0) {
                            // 分数领先或持平，跟注
                            $choice = 'call';
                        } elseif ($scoreDiff >= -3) {
                            // 稍微落后（落后3分以内），还有机会，跟注
                            $choice = 'call';
                        } elseif ($scoreDiff >= -6 && $myCount >= 4) {
                            // 明显落后（落后4-6分）且已投掷4次或以上，考虑放弃
                            $choice = 'fold';
                        } elseif ($scoreDiff >= -9 && $myCount >= 3) {
                            // 严重落后（落后7-9分）且已投掷3次或以上，放弃
                            $choice = 'fold';
                        } elseif ($scoreDiff < -9) {
                            // 极度落后（落后10分以上），无论投掷次数，放弃
                            $choice = 'fold';
                        } elseif ($myCount >= 4 && $otherCount < $myCount) {
                            // 已投掷多次但对手投掷较少，可能对手在等待，跟注
                            $choice = 'call';
                        } else {
                            // 其他情况（落后4-6分但投掷次数较少），还有机会，跟注
                            $choice = 'call';
                        }
                        
                        $roomState[$room]['decisions'][$pk] = $choice;
                        
                        // 如果AI选择跟注，需要扣款并加入奖池
                        if ($choice === 'call' && str_starts_with($room, 'mars-table-')) {
                            $uid = $roomState[$room]["{$pk}_id"] ?? null;
                            $tableId = intval(substr($room, strlen('mars-table-')));
                            
                            // 获取桌子下注金额（从房间状态中获取，如果不存在则查询）
                            $betAmount = $roomState[$room]['bet_amount'] ?? 0;
                            if ($betAmount <= 0) {
                                // 如果房间状态中没有，查询API获取
                                $tableResp = $postJson([
                                    'action' => 'get_game_tables',
                                ]);
                                if (($tableResp['ret'] ?? 1) === 0 && isset($tableResp['data'])) {
                                    foreach ($tableResp['data'] as $table) {
                                        if (intval($table['id'] ?? 0) === $tableId) {
                                            $betAmount = intval($table['bet_amount'] ?? 0);
                                            $roomState[$room]['bet_amount'] = $betAmount; // 缓存到房间状态
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            // AI玩家跟注扣款（不需要检查余额，直接扣款）
                            if ($betAmount > 0 && $uid) {
                                $uname = $roomState[$room][$pk] ?? 'AI玩家';
                                $duelId = $roomState[$room]['duel_id'] ?? '';
                                $wsLog("AI跟注扣款开始: pk={$pk}, uid={$uid}, betAmount={$betAmount}");
                                $resp = $postJson([
                                    'action' => 'mars_duel_bet',
                                    'user_id' => $uid,
                                    'table_id' => $tableId,
                                    'duel_id' => $duelId,
                                ]);
                                $wsLog("AI跟注扣款响应: " . json_encode($resp, JSON_UNESCAPED_UNICODE));
                                if (($resp['ret'] ?? 1) === 0) {
                                    // 扣款成功，将跟注金额加入奖池
                                    $roomState[$room]['pool_amount'] += $betAmount;
                                    $wsLog("AI跟注扣款成功，当前奖池: " . $roomState[$room]['pool_amount']);
                                    $broadcastRoom($room, json_encode([
                                        'type' => 'system',
                                        'text' => "{$uname} 跟注，奖池增加 " . number_format($betAmount, 1) . " 魔力值",
                                    ], JSON_UNESCAPED_UNICODE));
                                    // 广播奖池更新
                                    $broadcastRoom($room, json_encode([
                                        'type' => 'pool_update',
                                        'pool_amount' => $roomState[$room]['pool_amount'],
                                    ], JSON_UNESCAPED_UNICODE));
                                } else {
                                    // AI扣款失败，转为放弃
                                    $roomState[$room]['decisions'][$pk] = 'fold';
                                    $msg = $resp['msg'] ?? '扣款失败';
                                    $wsLog("AI跟注扣款失败: {$msg}，转为放弃");
                                    $broadcastRoom($room, json_encode([
                                        'type' => 'system',
                                        'text' => "{$uname} 跟注扣款失败：{$msg}，自动选择放弃",
                                    ], JSON_UNESCAPED_UNICODE));
                                }
                            }
                        }
                        
                        // 检查是否双方都已决策
                        $p1d = $roomState[$room]['decisions']['p1'] ?? null;
                        $p2d = $roomState[$room]['decisions']['p2'] ?? null;
                        if ($p1d !== null && $p2d !== null) {
                            // 清除所有定时器，避免重复执行
                            $clearTimers($room);
                            if ($p1d === 'fold' && $p2d === 'call') {
                                $settleDuel($room, 'p2', true);
                            } elseif ($p2d === 'fold' && $p1d === 'call') {
                                $settleDuel($room, 'p1', true);
                            } elseif ($p1d === 'fold' && $p2d === 'fold') {
                                $settleDuel($room, null, true);
                            } else {
                                $c1 = $countDice($room, 'p1');
                                $c2 = $countDice($room, 'p2');
                                if ($c1 >= 5 && $c2 >= 5) {
                                    $settleDuel($room, null, false);
                                } else {
                                    // 进入下一轮：轮流投掷，不是总是先手
                                    $roomState[$room]['rolls'] = [];
                                    // 如果上一轮是先手先投，这一轮应该是后手先投
                                    $currentTurn = $roomState[$room]['turn'] ?? $roomState[$room]['starter'] ?? 'p1';
                                    $nextTurn = ($currentTurn === 'p1') ? 'p2' : 'p1';
                                    $roomState[$room]['turn'] = $nextTurn;
                                    $roomState[$room]['status'] = 'playing';
                                    $broadcastRoom($room, json_encode([
                                        'type' => 'roll_request',
                                        'room' => $room,
                                        'player' => $roomState[$room][$nextTurn] ?? '',
                                        'turn' => $nextTurn,
                                        'timeout' => 30,
                                        'round' => $roomState[$room]['round'] ?? 1,
                                    ], JSON_UNESCAPED_UNICODE));
                                    
                                    // AI自动投掷（延迟2-5秒）
                                    if (($roomState[$room]['is_ai'][$nextTurn] ?? false)) {
                                        $delay = random_int(2, 5);
                                        Timer::add($delay, function () use (&$roomState, $room, $broadcastRoom, $nextTurn) {
                                            if (($roomState[$room]['status'] ?? '') !== 'playing') return;
                                            if (($roomState[$room]['turn'] ?? null) !== $nextTurn) return;
                                            if (isset($roomState[$room]['rolls'][$nextTurn])) return;
                                            
                                            $roll = [random_int(1, 6)];
                                            $sum = $roll[0];
                                            $roomState[$room]['dice_log'][$nextTurn][] = ['type' => 'open', 'val' => $sum, 'roll' => $roll];
                                            $roomState[$room]['rolls'][$nextTurn] = $roll;
                                            
                                            $broadcastRoom($room, json_encode([
                                                'type' => 'roll_done',
                                                'room' => $room,
                                                'player' => $roomState[$room][$nextTurn] ?? 'AI玩家',
                                                'turn' => $nextTurn,
                                                'roll' => $roll,
                                                'auto' => false,
                                            ], JSON_UNESCAPED_UNICODE));
                                            
                                            // 切换到下一个玩家或进入决策阶段
                                            $other = $nextTurn === 'p1' ? 'p2' : 'p1';
                                            if (!isset($roomState[$room]['rolls'][$other])) {
                                                $roomState[$room]['turn'] = $other;
                                                $broadcastRoom($room, json_encode([
                                                    'type' => 'roll_request',
                                                    'room' => $room,
                                                    'player' => $roomState[$room][$other] ?? '',
                                                    'turn' => $other,
                                                    'timeout' => 30,
                                                ], JSON_UNESCAPED_UNICODE));
                                            } else {
                                                $roomState[$room]['status'] = 'decision';
                                                $roomState[$room]['decisions'] = ['p1' => null, 'p2' => null];
                                                $broadcastRoom($room, json_encode([
                                                    'type' => 'decision_request',
                                                    'room' => $room,
                                                    'timeout' => 15,
                                                ], JSON_UNESCAPED_UNICODE));
                                            }
                                        }, [], false);
                                    }
                                }
                            }
                        }
                    }, [], false);
                }
            }
            
            $t = Timer::add(15, function () use (&$roomState, $room, $countDice, $settleDuel, $broadcastRoom, $postJson, $wsLog) {
                // 检查是否已经处理过（避免重复执行）
                if (($roomState[$room]['status'] ?? '') !== 'decision') return;
                
                // 超时自动跟注，需要扣款
                foreach (['p1', 'p2'] as $pk) {
                    if (($roomState[$room]['decisions'][$pk] ?? null) === null) {
                        $roomState[$room]['decisions'][$pk] = 'call';
                        
                        // 超时自动跟注，需要扣款并加入奖池
                        if (str_starts_with($room, 'mars-table-')) {
                            $uid = $roomState[$room]["{$pk}_id"] ?? null;
                            $tableId = intval(substr($room, strlen('mars-table-')));
                            
                            // 获取桌子下注金额
                            $betAmount = $roomState[$room]['bet_amount'] ?? 0;
                            if ($betAmount <= 0) {
                                $tableResp = $postJson([
                                    'action' => 'get_game_tables',
                                ]);
                                if (($tableResp['ret'] ?? 1) === 0 && isset($tableResp['data'])) {
                                    foreach ($tableResp['data'] as $table) {
                                        if (intval($table['id'] ?? 0) === $tableId) {
                                            $betAmount = intval($table['bet_amount'] ?? 0);
                                            $roomState[$room]['bet_amount'] = $betAmount;
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            // 超时自动跟注扣款（AI和真人玩家都需要）
                            if ($betAmount > 0 && $uid) {
                                $uname = $roomState[$room][$pk] ?? 'guest';
                                $duelId = $roomState[$room]['duel_id'] ?? '';
                                $isAi = $roomState[$room]['is_ai'][$pk] ?? false;
                                $wsLog("超时自动跟注扣款: pk={$pk}, uid={$uid}, betAmount={$betAmount}, isAi=" . ($isAi ? 'true' : 'false'));
                                
                                // 对于真人玩家，检查余额
                                $canCall = true;
                                if (!$isAi) {
                                    $checkResp = $postJson([
                                        'action' => 'get_user_bonus',
                                        'user_id' => $uid,
                                    ]);
                                    $currentBalance = ($checkResp['ret'] ?? 1) === 0 ? floatval($checkResp['data']['bonus'] ?? 0) : 0;
                                    if ($betAmount > 0 && $currentBalance < $betAmount) {
                                        $canCall = false;
                                        $roomState[$room]['decisions'][$pk] = 'fold';
                                        $wsLog("超时自动跟注：余额不足，转为放弃");
                                        $broadcastRoom($room, json_encode([
                                            'type' => 'system',
                                            'text' => "{$uname} 超时自动跟注，但余额不足，自动选择放弃",
                                        ], JSON_UNESCAPED_UNICODE));
                                    }
                                }
                                
                                if ($canCall) {
                                    $resp = $postJson([
                                        'action' => 'mars_duel_bet',
                                        'user_id' => $uid,
                                        'table_id' => $tableId,
                                        'duel_id' => $duelId,
                                    ]);
                                    $wsLog("超时自动跟注扣款响应: " . json_encode($resp, JSON_UNESCAPED_UNICODE));
                                    if (($resp['ret'] ?? 1) === 0) {
                                        // 扣款成功，将跟注金额加入奖池
                                        $roomState[$room]['pool_amount'] += $betAmount;
                                        $wsLog("超时自动跟注扣款成功，当前奖池: " . $roomState[$room]['pool_amount']);
                                        $broadcastRoom($room, json_encode([
                                            'type' => 'system',
                                            'text' => "{$uname} 超时自动跟注，奖池增加 " . number_format($betAmount, 1) . " 魔力值",
                                        ], JSON_UNESCAPED_UNICODE));
                                        // 广播奖池更新
                                        $broadcastRoom($room, json_encode([
                                            'type' => 'pool_update',
                                            'pool_amount' => $roomState[$room]['pool_amount'],
                                        ], JSON_UNESCAPED_UNICODE));
                                    } else {
                                        // 扣款失败，转为放弃
                                        $roomState[$room]['decisions'][$pk] = 'fold';
                                        $msg = $resp['msg'] ?? '扣款失败';
                                        $wsLog("超时自动跟注扣款失败: {$msg}，转为放弃");
                                        $broadcastRoom($room, json_encode([
                                            'type' => 'system',
                                            'text' => "{$uname} 超时自动跟注，但扣款失败：{$msg}，自动选择放弃",
                                        ], JSON_UNESCAPED_UNICODE));
                                    }
                                }
                            }
                        }
                    }
                }
                $p1d = $roomState[$room]['decisions']['p1'];
                $p2d = $roomState[$room]['decisions']['p2'];
                if ($p1d === 'fold' && $p2d === 'call') {
                    $settleDuel($room, 'p2', true);
                } elseif ($p2d === 'fold' && $p1d === 'call') {
                    $settleDuel($room, 'p1', true);
                } elseif ($p1d === 'fold' && $p2d === 'fold') {
                    $settleDuel($room, null, true);
                } else {
                    $c1 = $countDice($room, 'p1');
                    $c2 = $countDice($room, 'p2');
                    if ($c1 >= 5 && $c2 >= 5) {
                        $settleDuel($room, null, false);
                    } else {
                        // 进入下一轮：轮流投掷
                        $roomState[$room]['rolls'] = [];
                        $currentTurn = $roomState[$room]['turn'] ?? $roomState[$room]['starter'] ?? 'p1';
                        $nextTurn = ($currentTurn === 'p1') ? 'p2' : 'p1';
                        $roomState[$room]['turn'] = $nextTurn;
                        $roomState[$room]['status'] = 'playing';
                        $broadcastRoom($room, json_encode([
                            'type' => 'roll_request',
                            'room' => $room,
                            'player' => $roomState[$room][$nextTurn] ?? '',
                            'turn' => $nextTurn,
                            'timeout' => 30,
                            'round' => $roomState[$room]['round'] ?? 1,
                        ], JSON_UNESCAPED_UNICODE));
                        
                        // AI自动投掷（延迟2-5秒）
                        if (($roomState[$room]['is_ai'][$nextTurn] ?? false)) {
                            $delay = random_int(2, 5);
                            Timer::add($delay, function () use (&$roomState, $room, $broadcastRoom, $nextTurn) {
                                if (($roomState[$room]['status'] ?? '') !== 'playing') return;
                                if (($roomState[$room]['turn'] ?? null) !== $nextTurn) return;
                                if (isset($roomState[$room]['rolls'][$nextTurn])) return;
                                
                                $roll = [random_int(1, 6)];
                                $sum = $roll[0];
                                $roomState[$room]['dice_log'][$nextTurn][] = ['type' => 'open', 'val' => $sum, 'roll' => $roll];
                                $roomState[$room]['rolls'][$nextTurn] = $roll;
                                
                                $broadcastRoom($room, json_encode([
                                    'type' => 'roll_done',
                                    'room' => $room,
                                    'player' => $roomState[$room][$nextTurn] ?? 'AI玩家',
                                    'turn' => $nextTurn,
                                    'roll' => $roll,
                                    'auto' => false,
                                ], JSON_UNESCAPED_UNICODE));
                                
                                // 切换到下一个玩家或进入决策阶段
                                $other = $nextTurn === 'p1' ? 'p2' : 'p1';
                                if (!isset($roomState[$room]['rolls'][$other])) {
                                    $roomState[$room]['turn'] = $other;
                                    $broadcastRoom($room, json_encode([
                                        'type' => 'roll_request',
                                        'room' => $room,
                                        'player' => $roomState[$room][$other] ?? '',
                                        'turn' => $other,
                                        'timeout' => 30,
                                    ], JSON_UNESCAPED_UNICODE));
                                    
                                    // 如果另一个玩家也是AI，自动投掷
                                    if (($roomState[$room]['is_ai'][$other] ?? false)) {
                                        $delay2 = random_int(2, 5);
                                        Timer::add($delay2, function () use (&$roomState, $room, $broadcastRoom, $other) {
                                            if (($roomState[$room]['status'] ?? '') !== 'playing') return;
                                            if (($roomState[$room]['turn'] ?? null) !== $other) return;
                                            if (isset($roomState[$room]['rolls'][$other])) return;
                                            
                                            $roll = [random_int(1, 6)];
                                            $sum = $roll[0];
                                            $roomState[$room]['dice_log'][$other][] = ['type' => 'open', 'val' => $sum, 'roll' => $roll];
                                            $roomState[$room]['rolls'][$other] = $roll;
                                            
                                            $broadcastRoom($room, json_encode([
                                                'type' => 'roll_done',
                                                'room' => $room,
                                                'player' => $roomState[$room][$other] ?? 'AI玩家',
                                                'turn' => $other,
                                                'roll' => $roll,
                                                'auto' => false,
                                            ], JSON_UNESCAPED_UNICODE));
                                            
                                            // 双方都已投掷，进入决策阶段
                                            $roomState[$room]['status'] = 'decision';
                                            $roomState[$room]['decisions'] = ['p1' => null, 'p2' => null];
                                            $broadcastRoom($room, json_encode([
                                                'type' => 'decision_request',
                                                'room' => $room,
                                                'timeout' => 15,
                                            ], JSON_UNESCAPED_UNICODE));
                                        }, [], false);
                                    }
                                } else {
                                    $roomState[$room]['status'] = 'decision';
                                    $roomState[$room]['decisions'] = ['p1' => null, 'p2' => null];
                                    $broadcastRoom($room, json_encode([
                                        'type' => 'decision_request',
                                        'room' => $room,
                                        'timeout' => 15,
                                    ], JSON_UNESCAPED_UNICODE));
                                }
                            }, [], false);
                        }
                    }
                }
            }, [], false);
            $roomState[$room]['timers'][] = $t;
        }
        return;
    }

    // 跟注/弃权
    if (($decoded['type'] ?? '') === 'decision') {
        $seat = $connection->seat ?? null;
        $pk = $seat === 1 ? 'p1' : ($seat === 2 ? 'p2' : null);
        if (!$pk) {
            $wsLog("decision: 无效的座位 seat={$seat}");
            return;
        }
        if (($roomState[$room]['status'] ?? '') !== 'decision') {
            $wsLog("decision: 状态不是decision，当前状态=" . ($roomState[$room]['status'] ?? 'null'));
            return;
        }
        $choice = $decoded['choice'] ?? 'call';
        if (!in_array($choice, ['call', 'fold'], true)) {
            $wsLog("decision: 无效的选择 choice={$choice}");
            return;
        }
        $wsLog("decision: pk={$pk}, choice={$choice}, room={$room}");
        
        // 如果选择跟注，需要扣款并加入奖池
        if ($choice === 'call' && str_starts_with($room, 'mars-table-')) {
            $uid = $roomState[$room]["{$pk}_id"] ?? null;
            $tableId = intval(substr($room, strlen('mars-table-')));
            $wsLog("跟注处理开始: pk={$pk}, uid={$uid}, tableId={$tableId}, room={$room}");
            
            // 获取桌子下注金额（从房间状态中获取，如果不存在则查询）
            $betAmount = $roomState[$room]['bet_amount'] ?? 0;
            $wsLog("初始betAmount: {$betAmount}");
            if ($betAmount <= 0) {
                // 如果房间状态中没有，查询API获取
                $tableResp = $postJson([
                    'action' => 'get_game_tables',
                ]);
                if (($tableResp['ret'] ?? 1) === 0 && isset($tableResp['data'])) {
                    foreach ($tableResp['data'] as $table) {
                        if (intval($table['id'] ?? 0) === $tableId) {
                            $betAmount = intval($table['bet_amount'] ?? 0);
                            $roomState[$room]['bet_amount'] = $betAmount; // 缓存到房间状态
                            $wsLog("从API获取betAmount: {$betAmount}");
                            break;
                        }
                    }
                }
            }
            
            // 对于真人玩家，检查余额是否足够
            $canCall = true; // 是否可以跟注
            $isAi = $roomState[$room]['is_ai'][$pk] ?? false;
            $wsLog("isAi: " . ($isAi ? 'true' : 'false'));
            if (!$isAi) {
                // 查询用户当前余额
                $checkResp = $postJson([
                    'action' => 'get_user_bonus',
                    'user_id' => $uid,
                ]);
                $currentBalance = ($checkResp['ret'] ?? 1) === 0 ? floatval($checkResp['data']['bonus'] ?? 0) : 0;
                $wsLog("余额检查: currentBalance={$currentBalance}, betAmount={$betAmount}");
                
                // 如果余额不足（小于下注金额），自动转为放弃
                if ($betAmount > 0 && $currentBalance < $betAmount) {
                    $choice = 'fold';
                    $canCall = false;
                    $uname = $roomState[$room][$pk] ?? 'guest';
                    $wsLog("余额不足，转为放弃");
                    $broadcastRoom($room, json_encode([
                        'type' => 'system',
                        'text' => "{$uname} 余额不足，无法跟注，自动选择放弃",
                    ], JSON_UNESCAPED_UNICODE));
                    $connection->send(json_encode([
                        'type' => 'error',
                        'text' => '余额不足，无法跟注，已自动选择放弃',
                    ], JSON_UNESCAPED_UNICODE));
                }
            }
            
            // 如果选择跟注，执行扣款并加入奖池（真人玩家需要余额足够，AI玩家直接扣款）
            $wsLog("扣款条件检查: choice={$choice}, canCall=" . ($canCall ? 'true' : 'false') . ", betAmount={$betAmount}, uid={$uid}");
            if ($choice === 'call' && $canCall && $betAmount > 0 && $uid) {
                $wsLog("跟注扣款开始: pk={$pk}, uid={$uid}, betAmount={$betAmount}, canCall=" . ($canCall ? 'true' : 'false'));
                $uname = $roomState[$room][$pk] ?? 'guest';
                $duelId = $roomState[$room]['duel_id'] ?? '';
                $wsLog("准备调用mars_duel_bet: user_id={$uid}, table_id={$tableId}, duel_id={$duelId}");
                $resp = $postJson([
                    'action' => 'mars_duel_bet',
                    'user_id' => $uid,
                    'table_id' => $tableId,
                    'duel_id' => $duelId,
                ]);
                $wsLog("跟注扣款响应: " . json_encode($resp, JSON_UNESCAPED_UNICODE));
                if (($resp['ret'] ?? 1) !== 0) {
                    // 扣款失败，自动转为放弃
                    $choice = 'fold';
                    $msg = $resp['msg'] ?? '扣款失败';
                    $wsLog("跟注扣款失败: {$msg}");
                    $broadcastRoom($room, json_encode([
                        'type' => 'system',
                        'text' => "{$uname} 跟注扣款失败：{$msg}，自动选择放弃",
                    ], JSON_UNESCAPED_UNICODE));
                    $connection->send(json_encode([
                        'type' => 'error',
                        'text' => '跟注扣款失败，已自动选择放弃',
                    ], JSON_UNESCAPED_UNICODE));
                } else {
                    // 扣款成功，将跟注金额加入奖池
                    $roomState[$room]['pool_amount'] += $betAmount;
                    $wsLog("跟注扣款成功，当前奖池: " . $roomState[$room]['pool_amount']);
                    $broadcastRoom($room, json_encode([
                        'type' => 'system',
                        'text' => "{$uname} 跟注，奖池增加 " . number_format($betAmount, 1) . " 魔力值",
                    ], JSON_UNESCAPED_UNICODE));
                    // 广播奖池更新
                    $broadcastRoom($room, json_encode([
                        'type' => 'pool_update',
                        'pool_amount' => $roomState[$room]['pool_amount'],
                    ], JSON_UNESCAPED_UNICODE));
                }
            } else {
                $wsLog("跟注扣款条件不满足: choice={$choice}, canCall=" . ($canCall ? 'true' : 'false') . ", betAmount={$betAmount}, uid={$uid}");
            }
        } else {
            $wsLog("跟注条件不满足: choice={$choice}, isMarsTable=" . (str_starts_with($room, 'mars-table-') ? 'true' : 'false'));
        }
        
        $roomState[$room]['decisions'][$pk] = $choice;
        
        // 清除决策超时定时器（避免重复执行）
        $clearTimers($room);

        $p1d = $roomState[$room]['decisions']['p1'] ?? null;
        $p2d = $roomState[$room]['decisions']['p2'] ?? null;
        if ($p1d !== null && $p2d !== null) {
            if ($p1d === 'fold' && $p2d === 'call') {
                $settleDuel($room, 'p2', true);
            } elseif ($p2d === 'fold' && $p1d === 'call') {
                $settleDuel($room, 'p1', true);
            } elseif ($p1d === 'fold' && $p2d === 'fold') {
                $settleDuel($room, null, true);
            } else {
                $c1 = $countDice($room, 'p1');
                $c2 = $countDice($room, 'p2');
                if ($c1 >= 5 && $c2 >= 5) {
                    $settleDuel($room, null, false);
                } else {
                    // 进入下一轮：轮流投掷
                    $roomState[$room]['rolls'] = [];
                    $currentTurn = $roomState[$room]['turn'] ?? $roomState[$room]['starter'] ?? 'p1';
                    $nextTurn = ($currentTurn === 'p1') ? 'p2' : 'p1';
                    $roomState[$room]['turn'] = $nextTurn;
                    $roomState[$room]['status'] = 'playing';
                    $broadcastRoom($room, json_encode([
                        'type' => 'roll_request',
                        'room' => $room,
                        'player' => $roomState[$room][$nextTurn] ?? '',
                        'turn' => $nextTurn,
                        'timeout' => 30,
                        'round' => $roomState[$room]['round'] ?? 1,
                    ], JSON_UNESCAPED_UNICODE));
                    
                    // AI自动投掷（延迟2-5秒）
                    if (($roomState[$room]['is_ai'][$nextTurn] ?? false)) {
                        $delay = random_int(2, 5);
                        Timer::add($delay, function () use (&$roomState, $room, $broadcastRoom, $nextTurn) {
                            if (($roomState[$room]['status'] ?? '') !== 'playing') return;
                            if (($roomState[$room]['turn'] ?? null) !== $nextTurn) return;
                            if (isset($roomState[$room]['rolls'][$nextTurn])) return;
                            
                            $roll = [random_int(1, 6)];
                            $sum = $roll[0];
                            $roomState[$room]['dice_log'][$nextTurn][] = ['type' => 'open', 'val' => $sum, 'roll' => $roll];
                            $roomState[$room]['rolls'][$nextTurn] = $roll;
                            
                            $broadcastRoom($room, json_encode([
                                'type' => 'roll_done',
                                'room' => $room,
                                'player' => $roomState[$room][$nextTurn] ?? 'AI玩家',
                                'turn' => $nextTurn,
                                'roll' => $roll,
                                'auto' => false,
                            ], JSON_UNESCAPED_UNICODE));
                            
                            // 切换到下一个玩家或进入决策阶段
                            $other = $nextTurn === 'p1' ? 'p2' : 'p1';
                            if (!isset($roomState[$room]['rolls'][$other])) {
                                $roomState[$room]['turn'] = $other;
                                $broadcastRoom($room, json_encode([
                                    'type' => 'roll_request',
                                    'room' => $room,
                                    'player' => $roomState[$room][$other] ?? '',
                                    'turn' => $other,
                                    'timeout' => 30,
                                ], JSON_UNESCAPED_UNICODE));
                                
                                // 如果另一个玩家也是AI，自动投掷
                                if (($roomState[$room]['is_ai'][$other] ?? false)) {
                                    $delay2 = random_int(2, 5);
                                    Timer::add($delay2, function () use (&$roomState, $room, $broadcastRoom, $other) {
                                        if (($roomState[$room]['status'] ?? '') !== 'playing') return;
                                        if (($roomState[$room]['turn'] ?? null) !== $other) return;
                                        if (isset($roomState[$room]['rolls'][$other])) return;
                                        
                                        $roll = [random_int(1, 6)];
                                        $sum = $roll[0];
                                        $roomState[$room]['dice_log'][$other][] = ['type' => 'open', 'val' => $sum, 'roll' => $roll];
                                        $roomState[$room]['rolls'][$other] = $roll;
                                        
                                        $broadcastRoom($room, json_encode([
                                            'type' => 'roll_done',
                                            'room' => $room,
                                            'player' => $roomState[$room][$other] ?? 'AI玩家',
                                            'turn' => $other,
                                            'roll' => $roll,
                                            'auto' => false,
                                        ], JSON_UNESCAPED_UNICODE));
                                        
                                        // 双方都已投掷，进入决策阶段
                                        $roomState[$room]['status'] = 'decision';
                                        $roomState[$room]['decisions'] = ['p1' => null, 'p2' => null];
                                        $broadcastRoom($room, json_encode([
                                            'type' => 'decision_request',
                                            'room' => $room,
                                            'timeout' => 15,
                                        ], JSON_UNESCAPED_UNICODE));
                                    }, [], false);
                                }
                            } else {
                                $roomState[$room]['status'] = 'decision';
                                $roomState[$room]['decisions'] = ['p1' => null, 'p2' => null];
                                $broadcastRoom($room, json_encode([
                                    'type' => 'decision_request',
                                    'room' => $room,
                                    'timeout' => 15,
                                ], JSON_UNESCAPED_UNICODE));
                            }
                        }, [], false);
                    }
                }
            }
        }
        return;
    }

    // 占位/换座
    if (($decoded['type'] ?? '') === 'switch_seat') {
        $targetSeat = intval($decoded['seat'] ?? 0);
        if (!in_array($targetSeat, [1, 2], true)) {
            $connection->send(json_encode(['type' => 'error', 'text' => '座位无效'], JSON_UNESCAPED_UNICODE));
            return;
        }
        $user = $connection->user ?? 'guest';
        $uid = $connection->uid ?? null;
        $currentSeat = $connection->seat ?? null;
        $status = $roomState[$room]['status'] ?? 'idle';

        // 游戏进行中不允许换座
        if ($status !== 'idle' && $status !== 'selecting_hidden') {
            $connection->send(json_encode(['type' => 'error', 'text' => '对局进行中，暂不可换座'], JSON_UNESCAPED_UNICODE));
            return;
        }

        // 目标座位是否被他人占用
        if ($targetSeat === 1 && ($roomState[$room]['p1'] ?? null) !== null && ($roomState[$room]['p1'] ?? null) !== $user) {
            $connection->send(json_encode(['type' => 'error', 'text' => '该座位已被占用'], JSON_UNESCAPED_UNICODE));
            return;
        }
        if ($targetSeat === 2 && ($roomState[$room]['p2'] ?? null) !== null && ($roomState[$room]['p2'] ?? null) !== $user) {
            $connection->send(json_encode(['type' => 'error', 'text' => '该座位已被占用'], JSON_UNESCAPED_UNICODE));
            return;
        }

        // 如果原本就在目标座位
        if ($currentSeat === $targetSeat) {
            return;
        }

        // 从当前座位移除
        if ($currentSeat === 1 && ($roomState[$room]['p1'] ?? null) === $user) {
            $roomState[$room]['p1'] = null;
            $roomState[$room]['p1_id'] = null;
        } elseif ($currentSeat === 2 && ($roomState[$room]['p2'] ?? null) === $user) {
            $roomState[$room]['p2'] = null;
            $roomState[$room]['p2_id'] = null;
        }

        // 如果之前是观众，从观众列表移除并减少计数
        if ($connection->role === 'spectator') {
            $roomState[$room]['spectators'] = max(0, ($roomState[$room]['spectators'] ?? 0) - 1);
            $roomState[$room]['spectators_list'] = array_values(array_filter($roomState[$room]['spectators_list'] ?? [], fn($u) => $u !== $user));
        }

        // 放入目标座位
        if ($targetSeat === 1) {
            $roomState[$room]['p1'] = $user;
            $roomState[$room]['p1_id'] = $uid;
        } else {
            $roomState[$room]['p2'] = $user;
            $roomState[$room]['p2_id'] = $uid;
        }

        // 更新连接角色/座位
        $connection->role = 'player';
        $connection->seat = $targetSeat;

        // 重置状态为 idle
        $roomState[$room]['status'] = 'idle';
        $roomState[$room]['turn'] = null;
        $roomState[$room]['ready']['p1'] = $roomState[$room]['ready']['p1'] ?? false;
        $roomState[$room]['ready']['p2'] = $roomState[$room]['ready']['p2'] ?? false;
        $roomState[$room]['last_result'] = null;
        $roomState[$room]['duel_settled'] = false;

        $broadcastRoom($room, json_encode([
            'type' => 'system',
            'text' => "{$user} 切换到座位 {$targetSeat}",
        ], JSON_UNESCAPED_UNICODE));

        // 广播房间更新
        $broadcastRoom($room, json_encode([
            'type' => 'room_update',
            'room' => $room,
            'players' => [
                'p1' => $roomState[$room]['p1'] ?? null,
                'p2' => $roomState[$room]['p2'] ?? null,
            ],
            'spectators' => $roomState[$room]['spectators'] ?? 0,
            'spectators_list' => $roomState[$room]['spectators_list'] ?? [],
            'status' => $roomState[$room]['status'] ?? 'idle',
            'last_result' => $roomState[$room]['last_result'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
        return;
    }

    // 召唤AI玩家
    if (($decoded['type'] ?? '') === 'summon_ai') {
        $summonerSeat = $connection->seat ?? null;
        if (!in_array($summonerSeat, [1, 2], true)) {
            $connection->send(json_encode(['type' => 'error', 'text' => '只有玩家可以召唤AI'], JSON_UNESCAPED_UNICODE));
            return;
        }
        $targetSeat = $summonerSeat === 1 ? 2 : 1;
        
        // 检查目标座位是否已被占用
        if (($roomState[$room][$targetSeat === 1 ? 'p1' : 'p2'] ?? null) !== null) {
            $connection->send(json_encode(['type' => 'error', 'text' => '该座位已被占用'], JSON_UNESCAPED_UNICODE));
            return;
        }
        
        // 检查游戏状态
        $status = $roomState[$room]['status'] ?? 'idle';
        if ($status !== 'idle' && $status !== 'selecting_hidden') {
            $connection->send(json_encode(['type' => 'error', 'text' => '对局进行中，无法召唤AI'], JSON_UNESCAPED_UNICODE));
            return;
        }
        
        // 创建AI玩家
        $aiName = 'AI玩家';
        $aiUid = 2; // 默认AI用户ID
        $aiKey = $targetSeat === 1 ? 'p1' : 'p2';
        
        $roomState[$room][$aiKey] = $aiName;
        $roomState[$room]["{$aiKey}_id"] = $aiUid;
        $roomState[$room]['is_ai'][$aiKey] = true; // 标记为AI
        
        // 重置状态
        $roomState[$room]['status'] = 'idle';
        $roomState[$room]['ready'][$aiKey] = false;
        
        $broadcastRoom($room, json_encode([
            'type' => 'system',
            'text' => "AI玩家已加入座位 {$targetSeat}",
        ], JSON_UNESCAPED_UNICODE));
        
        // 广播房间更新
        $broadcastRoom($room, json_encode([
            'type' => 'room_update',
            'room' => $room,
            'players' => [
                'p1' => $roomState[$room]['p1'] ?? null,
                'p2' => $roomState[$room]['p2'] ?? null,
            ],
            'spectators' => $roomState[$room]['spectators'] ?? 0,
            'spectators_list' => $roomState[$room]['spectators_list'] ?? [],
            'status' => $roomState[$room]['status'] ?? 'idle',
            'last_result' => $roomState[$room]['last_result'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
        return;
    }
    
    // 踢掉AI玩家
    if (($decoded['type'] ?? '') === 'kick_ai') {
        $kickerSeat = $connection->seat ?? null;
        if (!in_array($kickerSeat, [1, 2], true)) {
            $connection->send(json_encode(['type' => 'error', 'text' => '只有玩家可以踢掉AI'], JSON_UNESCAPED_UNICODE));
            return;
        }
        $targetSeat = $kickerSeat === 1 ? 2 : 1;
        $targetKey = $targetSeat === 1 ? 'p1' : 'p2';
        
        // 检查目标座位是否是AI
        if (!($roomState[$room]['is_ai'][$targetKey] ?? false)) {
            $connection->send(json_encode(['type' => 'error', 'text' => '该座位不是AI玩家'], JSON_UNESCAPED_UNICODE));
            return;
        }
        
        // 检查游戏状态：只有在idle状态（一局结束后）才能踢掉AI
        $status = $roomState[$room]['status'] ?? 'idle';
        if ($status !== 'idle') {
            $connection->send(json_encode(['type' => 'error', 'text' => '对局进行中，无法踢掉AI，请等待本局结束后再操作'], JSON_UNESCAPED_UNICODE));
            return;
        }
        
        // 移除AI
        $roomState[$room][$targetKey] = null;
        $roomState[$room]["{$targetKey}_id"] = null;
        unset($roomState[$room]['is_ai'][$targetKey]);
        $roomState[$room]['ready'][$targetKey] = false;
        
        // 重置状态
        $roomState[$room]['status'] = 'idle';
        
        $broadcastRoom($room, json_encode([
            'type' => 'system',
            'text' => "AI玩家已被移除",
        ], JSON_UNESCAPED_UNICODE));
        
        // 广播房间更新
        $broadcastRoom($room, json_encode([
            'type' => 'room_update',
            'room' => $room,
            'players' => [
                'p1' => $roomState[$room]['p1'] ?? null,
                'p2' => $roomState[$room]['p2'] ?? null,
            ],
            'spectators' => $roomState[$room]['spectators'] ?? 0,
            'spectators_list' => $roomState[$room]['spectators_list'] ?? [],
            'status' => $roomState[$room]['status'] ?? 'idle',
            'last_result' => $roomState[$room]['last_result'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
        return;
    }

    // 房主踢人
    if (($decoded['type'] ?? '') === 'kick_player') {
        $kickerUid = $connection->uid ?? 0;
        $ownerId = $roomState[$room]['owner_id'] ?? null;
        $targetSeat = intval($decoded['seat'] ?? 0);
        if (!$ownerId || $kickerUid != $ownerId) {
            $connection->send(json_encode(['type' => 'error', 'text' => '只有房主可以踢人'], JSON_UNESCAPED_UNICODE));
            return;
        }
        if (!in_array($targetSeat, [1, 2], true)) {
            $connection->send(json_encode(['type' => 'error', 'text' => '无效的座位'], JSON_UNESCAPED_UNICODE));
            return;
        }
        $pk = $targetSeat === 1 ? 'p1' : 'p2';
        $kickedUser = $roomState[$room][$pk] ?? null;
        if (!$kickedUser) {
            $connection->send(json_encode(['type' => 'error', 'text' => '该座位没有玩家'], JSON_UNESCAPED_UNICODE));
            return;
        }
        // 清座
        $roomState[$room][$pk] = null;
        $roomState[$room]["{$pk}_id"] = null;
        // 加入观众
        if (!in_array($kickedUser, $roomState[$room]['spectators_list'] ?? [])) {
            $roomState[$room]['spectators_list'][] = $kickedUser;
        }
        $roomState[$room]['spectators'] = ($roomState[$room]['spectators'] ?? 0) + 1;
        $roomState[$room]['status'] = 'idle';
        $roomState[$room]['last_result'] = null;
        $roomState[$room]['duel_settled'] = false;

        // 如果被踢玩家在线，更新其角色
        if (isset($userConnections[$room][$kickedUser])) {
            $kconn = $userConnections[$room][$kickedUser];
            $kconn->role = 'spectator';
            $kconn->seat = null;
        }

        $broadcastRoom($room, json_encode([
            'type' => 'system',
            'text' => "{$kickedUser} 被房主踢到观众席位",
        ], JSON_UNESCAPED_UNICODE));
        $broadcastRoom($room, json_encode([
            'type' => 'room_update',
            'room' => $room,
            'players' => [
                'p1' => $roomState[$room]['p1'] ?? null,
                'p2' => $roomState[$room]['p2'] ?? null,
            ],
            'spectators' => $roomState[$room]['spectators'] ?? 0,
            'spectators_list' => $roomState[$room]['spectators_list'] ?? [],
            'status' => $roomState[$room]['status'] ?? 'idle',
            'last_result' => $roomState[$room]['last_result'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
        return;
    }

    // 离开座位 -> 观众
    if (($decoded['type'] ?? '') === 'leave_seat') {
        $seat = $connection->seat ?? null;
        $user = $connection->user ?? 'guest';
        $pk = ($seat === 1) ? 'p1' : (($seat === 2) ? 'p2' : null);
        if (!$pk || ($roomState[$room][$pk] ?? null) !== $user) {
            // 即便不是玩家，也允许切换为观众
        } else {
            $roomState[$room][$pk] = null;
            $roomState[$room]["{$pk}_id"] = null;
        }
        $connection->role = 'spectator';
        $connection->seat = null;
        $roomState[$room]['spectators'] = ($roomState[$room]['spectators'] ?? 0) + 1;
        if (!in_array($user, $roomState[$room]['spectators_list'] ?? [])) {
            $roomState[$room]['spectators_list'][] = $user;
        }
        $roomState[$room]['status'] = 'idle';
        $roomState[$room]['last_result'] = null;
        $roomState[$room]['duel_settled'] = false;

        $broadcastRoom($room, json_encode([
            'type' => 'system',
            'text' => "{$user} 离开座位，切换到观众",
        ], JSON_UNESCAPED_UNICODE));
        $broadcastRoom($room, json_encode([
            'type' => 'room_update',
            'room' => $room,
            'players' => [
                'p1' => $roomState[$room]['p1'] ?? null,
                'p2' => $roomState[$room]['p2'] ?? null,
            ],
            'spectators' => $roomState[$room]['spectators'] ?? 0,
            'spectators_list' => $roomState[$room]['spectators_list'] ?? [],
            'status' => $roomState[$room]['status'] ?? 'idle',
            'last_result' => $roomState[$room]['last_result'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
        return;
    }
    // 普通聊天
    if (($decoded['type'] ?? '') === 'message') {
        $text = trim($decoded['text'] ?? '');
        if ($text !== '') {
            $broadcastRoom($room, json_encode([
                'type' => 'message',
                'user' => $connection->user ?? 'guest',
                'text' => htmlspecialchars($text, ENT_QUOTES),
                'time' => date('H:i:s'),
            ], JSON_UNESCAPED_UNICODE));
        }
        return;
    }

    // cheer
    if (($decoded['type'] ?? '') === 'cheer') {
        $emoji = $decoded['emoji'] ?? '👏';
        $broadcastRoom($room, json_encode([
            'type' => 'cheer',
            'user' => $connection->user ?? 'guest',
            'emoji' => $emoji,
            'time' => date('H:i:s'),
        ], JSON_UNESCAPED_UNICODE));
        return;
    }

    // 大厅/房间状态请求
    if (($decoded['type'] ?? '') === 'room_state_request') {
        if (($connection->room ?? 'global') === 'global') {
            $roomsSnapshot = [];
            foreach ($roomState as $rKey => $st) {
                $roomsSnapshot[] = [
                    'room' => $rKey,
                    'players' => ['p1' => $st['p1'] ?? null, 'p2' => $st['p2'] ?? null],
                    'spectators' => $st['spectators'] ?? 0,
                    'spectators_list' => $st['spectators_list'] ?? [],
                ];
            }
            $connection->send(json_encode([
                'type' => 'room_state',
                'rooms' => $roomsSnapshot,
            ], JSON_UNESCAPED_UNICODE));
        }
        return;
    }

    // 兜底
    $broadcastRoom($room, json_encode([
        'type' => 'system',
        'text' => '收到未识别指令',
    ], JSON_UNESCAPED_UNICODE));
};

$worker->onClose = function (TcpConnection $connection) use (&$roomState, &$userConnections, $broadcastRoom) {
    $room = $connection->room ?? 'global';
    $user = $connection->user ?? 'guest';
    $role = $connection->role ?? 'spectator';
    $seat = $connection->seat ?? null;
    unset($userConnections[$room][$user]);

    if (isset($roomState[$room])) {
        if ($role === 'player') {
            $status = $roomState[$room]['status'] ?? 'idle';
            // 如果在对局中（selecting_hidden, dueling, playing, decision），保留 p1_id 和 p2_id，只清空玩家名
            // 这样断线重连时可以根据用户ID恢复座位
            $isInDuel = in_array($status, ['selecting_hidden', 'dueling', 'playing', 'decision']);
            
            if ($seat === 1 && ($roomState[$room]['p1'] ?? null) === $user) {
                $roomState[$room]['p1'] = null;
                // 只有在空闲状态时才清空 p1_id，对局中保留以便重连恢复
                if (!$isInDuel) {
                    $roomState[$room]['p1_id'] = null;
                }
            }
            if ($seat === 2 && ($roomState[$room]['p2'] ?? null) === $user) {
                $roomState[$room]['p2'] = null;
                // 只有在空闲状态时才清空 p2_id，对局中保留以便重连恢复
                if (!$isInDuel) {
                    $roomState[$room]['p2_id'] = null;
                }
            }
        } else {
            $roomState[$room]['spectators'] = max(0, ($roomState[$room]['spectators'] ?? 1) - 1);
            $roomState[$room]['spectators_list'] = array_values(array_filter($roomState[$room]['spectators_list'] ?? [], fn($u) => $u !== $user));
        }
    }

    $broadcastRoom($room, json_encode([
        'type' => 'room_update',
        'room' => $room,
        'players' => [
            'p1' => $roomState[$room]['p1'] ?? null,
            'p2' => $roomState[$room]['p2'] ?? null,
        ],
        'spectators' => $roomState[$room]['spectators'] ?? 0,
        'spectators_list' => $roomState[$room]['spectators_list'] ?? [],
        'status' => $roomState[$room]['status'] ?? 'idle',
        'last_result' => $roomState[$room]['last_result'] ?? null,
    ], JSON_UNESCAPED_UNICODE));
};

Worker::runAll();

