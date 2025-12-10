<?php
// Simple Workerman chat server (broadcast to all connections).
// Requirements: composer require workerman/workerman

use Workerman\Connection\TcpConnection;
use Workerman\Worker;
use Workerman\Timer;

require_once __DIR__ . '/vendor/autoload.php';

// 允许的房间（可按需调整/扩展），支持前缀 mars-table-*
$allowedRooms = [
    'global',          // 兼容旧版 chat_demo
    'mars-alpha',      // 火星幸运局房间 A
    'mars-beta',       // 火星幸运局房间 B
];

$isAllowedRoom = function (string $room) use (&$allowedRooms): bool {
    if (in_array($room, $allowedRooms, true)) {
        return true;
    }
    // 动态房间：mars-table-<id>
    if (str_starts_with($room, 'mars-table-')) {
        return true;
    }
    return false;
};

// 记录房间内座位/观众数
$roomState = [];
$userConnections = []; // 用户名 => 连接对象映射（按房间）

// 日志文件（与 Workerman stdout/log 同步）
$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logDate = date('Y-m-d');
$wsLogFile = $logDir . "/workerman_chat_server_{$logDate}.log";
$wsLog = function (string $msg) use (&$wsLogFile) {
    error_log(date('Y-m-d H:i:s') . ' ' . $msg . "\n", 3, $wsLogFile);
};

/**
 * 清理房间定时器
 */
$clearTimers = function (string $room) use (&$roomState) {
    if (!isset($roomState[$room]['timers'])) return;
    foreach ($roomState[$room]['timers'] as $t) {
        Timer::del($t);
    }
    $roomState[$room]['timers'] = [];
};

// 结算/扣款 API
$settleApi = getenv('MARS_DUEL_API') ?: 'http://127.0.0.1:8000/ajax.php';
$settleToken = getenv('MARS_DUEL_TOKEN') ?: '';

/**
 * 简单 HTTP POST 请求
 */
$postJson = function (array $payload) use ($settleApi, $settleToken, $wsLog) {

    $payload['token'] = $settleToken;
    
    // 记录请求信息
    $requestInfo = [
        'url' => $settleApi,
        'action' => $payload['action'] ?? 'unknown',
        'payload' => $payload,
    ];
    $wsLog("Mars Duel API Request: " . json_encode($requestInfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($payload),
            'timeout' => 5,
        ],
    ];
    $context = stream_context_create($options);
    $resp = @file_get_contents($settleApi, false, $context);
    
    // 记录完整响应（原始字符串）
    $respRaw = $resp;
    $respHex = '';
    if ($resp !== false) {
        // 打印响应的十六进制表示（用于调试特殊字符）
        $respHex = bin2hex(substr($resp, 0, 100));
    }
    $wsLog("Mars Duel API Response Raw: length=" . ($resp !== false ? strlen($resp) : 0) . ", hex(100)=" . $respHex);
    $wsLog("Mars Duel API Response Full: " . ($resp !== false ? $resp : 'false'));
    
    if ($resp === false) {
        $error = error_get_last();
        $errorMsg = $error ? $error['message'] : 'unknown error';
        $wsLog("Mars Duel API Request Failed: " . $errorMsg);
        return ['ret' => 1, 'msg' => 'request failed: ' . $errorMsg];
    }
    
    // 尝试解析 JSON
    $respTrimmed = trim($resp);
    if (empty($respTrimmed)) {
        $wsLog("Mars Duel API Response Empty");
        return ['ret' => 1, 'msg' => 'empty response'];
    }
    
    $data = json_decode($respTrimmed, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        // JSON 解析失败，记录完整错误信息
        $jsonError = json_last_error_msg();
        $respLength = strlen($respTrimmed);
        $respType = gettype($respTrimmed);
        
        // 打印完整的响应内容（包括所有字符）
        $wsLog("Mars Duel API JSON Parse Error Details:");
        $wsLog("  - Error: {$jsonError}");
        $wsLog("  - Length: {$respLength}");
        $wsLog("  - Type: {$respType}");
        $wsLog("  - Full Response: " . var_export($respTrimmed, true));
        $wsLog("  - Response Hex: " . bin2hex($respTrimmed));
        $wsLog("  - Response Preview (200): " . mb_substr($respTrimmed, 0, 200));
        
        return ['ret' => 1, 'msg' => 'invalid json: ' . $jsonError . ' (response length: ' . $respLength . ', preview: ' . mb_substr($respTrimmed, 0, 200) . ')'];
    }
    
    // 记录解析成功的数据
    $wsLog("Mars Duel API Response Parsed: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    return $data;
};

$worker = new Worker('websocket://0.0.0.0:2346');

// 将 Workerman 日志与业务日志统一到同一文件
Worker::$stdoutFile = $wsLogFile; // 标准输出重定向到日志文件
Worker::$logFile = $wsLogFile; // Workerman 日志文件
$worker->count = 1;
$worker->name = 'chat-demo';

/**
 * 按房间广播
 */
$broadcastRoom = function (string $room, string $payload) use (&$roomState, &$broadcastRoom, &$worker) {
    foreach ($worker->connections as $client) {
        if (($client->room ?? 'global') === $room) {
            $client->send($payload);
        }
    }
};

/**
 * 开始一局对战（当房间有两名玩家且不在进行中）
 */
$startDuel = function (string $room, string $starterTurn = 'p1') use (&$roomState, &$broadcastRoom, $clearTimers, $postJson) {
    $state = $roomState[$room] ?? null;
    if (!$state) {
        return;
    }
    if (($state['p1'] ?? null) && ($state['p2'] ?? null) && ($state['status'] ?? 'idle') === 'idle') {
        // 为本局生成唯一 ID，便于追溯
        $duelId = $roomState[$room]['duel_id'] ?? bin2hex(random_bytes(16));
        $roomState[$room]['duel_id'] = $duelId;
        $roomState[$room]['duel_settled'] = false;

        // 开局前扣款：要求房间名 mars-table-<id> 才执行
        $tableId = null;
        if (str_starts_with($room, 'mars-table-')) {
            $tableId = intval(substr($room, strlen('mars-table-')));
        }

        if ($tableId) {
            $playersNeedPay = [
                ['uid_key' => 'p1_id', 'name_key' => 'p1'],
                ['uid_key' => 'p2_id', 'name_key' => 'p2'],
            ];
            error_log("Mars Duel StartDuel: room={$room}, tableId={$tableId}, p1={$state['p1']}({$roomState[$room]['p1_id']}), p2={$state['p2']}({$roomState[$room]['p2_id']})");
            
            foreach ($playersNeedPay as $p) {
                $uid = $roomState[$room][$p['uid_key']] ?? null;
                $uname = $roomState[$room][$p['name_key']] ?? '';
                error_log("Mars Duel Bet Processing: player={$uname}({$uid}), seat={$p['name_key']}");
                
                if (!$uid) {
                    error_log("Mars Duel Bet Failed: {$uname} missing user ID");
                    $broadcastRoom($room, json_encode(['type' => 'system', 'text' => "{$uname} 未提供用户ID，无法扣款，已取消本局"], JSON_UNESCAPED_UNICODE));
                    return;
                }
                
                error_log("Mars Duel Bet Calling API: user_id={$uid}, table_id={$tableId}");
                $resp = $postJson([
                    'action' => 'mars_duel_bet',
                    'user_id' => $uid,
                    'table_id' => $tableId,
                    'duel_id' => $duelId,
                ]);
                
                error_log("Mars Duel Bet API Response: " . json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                
                if (($resp['ret'] ?? 1) !== 0) {
                    $msg = $resp['msg'] ?? '扣款失败';
                    // 记录详细错误信息用于调试
                    $errorDetail = isset($resp['msg']) ? $resp['msg'] : 'unknown error';
                    error_log("Mars Duel Bet Failed: user={$uname}({$uid}), table={$tableId}, error={$errorDetail}, full_response=" . json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    $broadcastRoom($room, json_encode(['type' => 'system', 'text' => "{$uname} 扣款失败：{$msg}，本局取消"], JSON_UNESCAPED_UNICODE));
                    return;
                }
                
                error_log("Mars Duel Bet Success: user={$uname}({$uid})");
            }
            error_log("Mars Duel Bet All Players Success");
        }

        $roomState[$room]['status'] = 'playing';
        $roomState[$room]['turn'] = $starterTurn; // p1/p2
        $roomState[$room]['rolls'] = [];
        $clearTimers($room);

        $broadcastRoom($room, json_encode(['type' => 'system', 'text' => "本局开始！{$state['p1']} vs {$state['p2']}（先手：" . ($starterTurn === 'p1' ? $state['p1'] : $state['p2']) . "）"], JSON_UNESCAPED_UNICODE));

        $askRoll = function (string $room, string $playerKey, string $playerName) use (&$roomState, &$broadcastRoom, $clearTimers, &$askRoll, $postJson) {
            $roomState[$room]['turn'] = $playerKey;
            $roomState[$room]['timers'] = $roomState[$room]['timers'] ?? [];
            $broadcastRoom($room, json_encode([
                'type' => 'roll_request',
                'room' => $room,
                'player' => $playerName,
                'turn' => $playerKey,
                'timeout' => 30,
            ], JSON_UNESCAPED_UNICODE));
            $t = Timer::add(30, function () use (&$roomState, &$broadcastRoom, $room, $playerKey, $playerName, $askRoll, $clearTimers, $postJson) {
                $roll = [random_int(1, 6), random_int(1, 6)];
                $roomState[$room]['rolls'][$playerKey] = $roll;
                $broadcastRoom($room, json_encode([
                    'type' => 'roll_done',
                    'room' => $room,
                    'player' => $playerName,
                    'turn' => $playerKey,
                    'roll' => $roll,
                    'auto' => true,
                ], JSON_UNESCAPED_UNICODE));
                
                // 检查另一个玩家是否已投掷
                $otherKey = $playerKey === 'p1' ? 'p2' : 'p1';
                $otherRolled = isset($roomState[$room]['rolls'][$otherKey]);
                
                if (!$otherRolled) {
                    // 另一个玩家还未投掷，请求其投掷
                    $askRoll($room, $otherKey, $roomState[$room][$otherKey]);
                } else {
                    // 两个玩家都已投掷，结算
                    $clearTimers($room);
                    $r1 = $roomState[$room]['rolls']['p1'] ?? [random_int(1, 6), random_int(1, 6)];
                    $r2 = $roomState[$room]['rolls']['p2'] ?? [random_int(1, 6), random_int(1, 6)];
                    $s1 = array_sum($r1);
                    $s2 = array_sum($r2);
                    $winner = $s1 > $s2 ? $roomState[$room]['p1'] : ($s2 > $s1 ? $roomState[$room]['p2'] : null);
                    $result = [
                        'type' => 'duel_result',
                        'room' => $room,
                        'p1' => $roomState[$room]['p1'],
                        'p2' => $roomState[$room]['p2'],
                        'p1_roll' => $r1,
                        'p2_roll' => $r2,
                        'p1_sum' => $s1,
                        'p2_sum' => $s2,
                        'winner' => $winner,
                        'duel_id' => $roomState[$room]['duel_id'] ?? null,
                        'time' => date('H:i:s'),
                    ];
                    $roomState[$room]['last_result'] = $result;
                    $roomState[$room]['status'] = 'idle';
                    $roomState[$room]['turn'] = null;
                    $broadcastRoom($room, json_encode($result, JSON_UNESCAPED_UNICODE));
                    $broadcastRoom($room, json_encode([
                        'type' => 'room_update',
                        'room' => $room,
                        'players' => [
                            'p1' => $roomState[$room]['p1'],
                            'p2' => $roomState[$room]['p2'],
                        ],
                        'spectators' => $roomState[$room]['spectators'],
                        'spectators_list' => $roomState[$room]['spectators_list'] ?? [],
                        'status' => $roomState[$room]['status'],
                        'last_result' => $roomState[$room]['last_result'] ?? null,
                    ], JSON_UNESCAPED_UNICODE));

                    // 结算：调用内部接口（防重复）
                    if (
                        (($roomState[$room]['duel_settled'] ?? false) === false) &&
                        ($roomState[$room]['p1_id'] ?? null) &&
                        ($roomState[$room]['p2_id'] ?? null) &&
                        isset($result['winner']) &&
                        str_starts_with($room, 'mars-table-')
                    ) {
                        $tableId = intval(substr($room, strlen('mars-table-')));
                        $winnerId = null;
                        if ($result['winner'] === ($roomState[$room]['p1'] ?? '')) {
                            $winnerId = $roomState[$room]['p1_id'];
                        } elseif ($result['winner'] === ($roomState[$room]['p2'] ?? '')) {
                            $winnerId = $roomState[$room]['p2_id'];
                        } else {
                            $winnerId = 0; // 平局
                        }
                        $roomState[$room]['duel_settled'] = true;
                        $settleResult = $postJson([
                            'action' => 'mars_duel_settle',
                            'table_id' => $tableId,
                            'p1_user_id' => $roomState[$room]['p1_id'],
                            'p2_user_id' => $roomState[$room]['p2_id'],
                            'winner_user_id' => $winnerId,
                            'duel_id' => $roomState[$room]['duel_id'] ?? null,
                        ]);
                        // 将结算结果添加到 result 中
                        if (isset($settleResult['data'])) {
                            $result['winner_bonus'] = $settleResult['data']['winner_bonus'] ?? 0;
                            $result['p1_bonus'] = $settleResult['data']['p1_bonus'] ?? 0;
                            $result['p2_bonus'] = $settleResult['data']['p2_bonus'] ?? 0;
                        }
                    }
                }
            }, [], false);
            $roomState[$room]['timers'][] = $t;
        };

        $first = $starterTurn;
        $second = $starterTurn === 'p1' ? 'p2' : 'p1';
        $askRoll($room, $first, $roomState[$room][$first]);
    }
};

$worker->onConnect = function (TcpConnection $connection) use (&$roomState, $allowedRooms, $broadcastRoom, $startDuel, $isAllowedRoom) {
    // 兼容新版 Workerman：onWebSocketConnect 可接收 Request 参数
    $connection->onWebSocketConnect = function (TcpConnection $connection, $request = null) use (&$roomState, $allowedRooms, $broadcastRoom, $startDuel, $isAllowedRoom) {
        // 读取 query 参数：优先 Request，再回退 $_SERVER/$_GET
        $params = [];
        if ($request) {
            // Workerman\Http\Request
            if (method_exists($request, 'get')) {
                $params = $request->get() ?: [];
            }
            if (empty($params) && method_exists($request, 'queryString')) {
                $qs = $request->queryString();
                if ($qs) {
                    parse_str($qs, $params);
                }
            }
            if (empty($params) && method_exists($request, 'uri')) {
                $uri = $request->uri();
                $qs = parse_url($uri, PHP_URL_QUERY);
                if ($qs) {
                    parse_str($qs, $params);
                }
            }
        }
        if (empty($params) && !empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $params);
        }
        if (empty($params)) {
            $params = $_GET ?: [];
        }

        $user = $params['user'] ?? ('guest-' . substr(md5($connection->id . microtime(true)), 0, 6));
        $user = urldecode($user);
        $uid = intval($params['uid'] ?? 0);
        $room = $params['room'] ?? 'global';
        if (!$isAllowedRoom($room)) {
            $room = 'global';
        }
        $role = $params['role'] ?? 'spectator'; // player / spectator
        $seat = intval($params['seat'] ?? 0);
        $tableId = intval($params['table_id'] ?? 0);
        $ownerId = intval($params['owner_id'] ?? 0);

        // 初始化房间状态
        if (!isset($roomState[$room])) {
            $roomState[$room] = [
                'p1' => null,
                'p2' => null,
                'spectators' => 0,
                'spectators_list' => [],
                'status' => 'idle',
                'last_result' => null,
                'player_last_activity' => [], // 记录玩家最后活动时间
                'table_id' => null,
                'owner_id' => null, // 桌子房主 ID
            ];
        }
        
        // 如果是 mars-table-* 房间，保存 table_id 和 owner_id
        if ($tableId > 0 && str_starts_with($room, 'mars-table-')) {
            $roomState[$room]['table_id'] = $tableId;
            // 如果提供了 owner_id，保存它（优先使用连接时传递的值）
            if ($ownerId > 0) {
                $roomState[$room]['owner_id'] = $ownerId;
            } elseif ($roomState[$room]['owner_id'] === null) {
                // 如果没有提供 owner_id，通过 HTTP API 获取（备用方案）
                $apiUrl = getenv('MARS_DUEL_API') ?: 'http://127.0.0.1:8000/ajax.php';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $apiUrl . '?action=get_game_tables');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                $response = curl_exec($ch);
                curl_close($ch);
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['data']) && is_array($data['data'])) {
                        foreach ($data['data'] as $table) {
                            if (isset($table['id']) && $table['id'] == $tableId) {
                                $roomState[$room]['owner_id'] = intval($table['owner_id'] ?? 0);
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        // 记录玩家活动时间
        if ($role === 'player' && $assignedSeat) {
            $playerKey = $assignedSeat === 1 ? 'p1' : 'p2';
            $roomState[$room]['player_last_activity'][$playerKey] = time();
        }

        // 座位分配
        $assignedSeat = null;
        if ($role === 'player') {
            if ($seat === 1 && $roomState[$room]['p1'] === null) {
                $assignedSeat = 1;
            } elseif ($seat === 2 && $roomState[$room]['p2'] === null) {
                $assignedSeat = 2;
            } elseif ($roomState[$room]['p1'] === null) {
                $assignedSeat = 1;
            } elseif ($roomState[$room]['p2'] === null) {
                $assignedSeat = 2;
            } else {
                // 座位满，降级为观众
                $role = 'spectator';
            }
        }

        if ($role !== 'player') {
            $roomState[$room]['spectators']++;
            if (!in_array($user, $roomState[$room]['spectators_list'])) {
                $roomState[$room]['spectators_list'][] = $user;
            }
            $broadcastRoom($room, json_encode([
                'type' => 'system',
                'text' => "{$user} 以观众身份加入房间",
            ], JSON_UNESCAPED_UNICODE));
        } else {
            // 如果用户之前在观众列表中，移除
            $key = array_search($user, $roomState[$room]['spectators_list']);
            if ($key !== false) {
                unset($roomState[$room]['spectators_list'][$key]);
                $roomState[$room]['spectators_list'] = array_values($roomState[$room]['spectators_list']);
                if ($roomState[$room]['spectators'] > 0) {
                    $roomState[$room]['spectators']--;
                }
            }
            if ($assignedSeat === 1) {
                $roomState[$room]['p1'] = $user;
                $roomState[$room]['p1_id'] = $uid ?: null;
                $roomState[$room]['player_last_activity']['p1'] = time();
                $broadcastRoom($room, json_encode([
                    'type' => 'system',
                    'text' => "{$user} 占座 玩家一",
                ], JSON_UNESCAPED_UNICODE));
            } elseif ($assignedSeat === 2) {
                $roomState[$room]['p2'] = $user;
                $roomState[$room]['p2_id'] = $uid ?: null;
                $roomState[$room]['player_last_activity']['p2'] = time();
                $broadcastRoom($room, json_encode([
                    'type' => 'system',
                    'text' => "{$user} 占座 玩家二",
                ], JSON_UNESCAPED_UNICODE));
            }
            // 单播座位分配，前端可同步 currentSeat
            if ($assignedSeat) {
                $connection->send(json_encode([
                    'type' => 'seat_assigned',
                    'room' => $room,
                    'seat' => $assignedSeat,
                ], JSON_UNESCAPED_UNICODE));
            }
        }

        $connection->user = $user;
        $connection->uid = $uid;
        $connection->room = $room;
        $connection->role = $role;
        $connection->seat = $assignedSeat;
        
        // 维护用户连接映射（用于超时踢人）
        if (!isset($userConnections[$room])) {
            $userConnections[$room] = [];
        }
        $userConnections[$room][$user] = $connection;

        // 个人欢迎
        $connection->send(json_encode([
            'type' => 'system',
            'text' => "欢迎 {$user} 进入 {$room}（{$role}" . ($assignedSeat ? " #{$assignedSeat}" : '') . "）",
        ], JSON_UNESCAPED_UNICODE));

        // 房间广播加入
        $broadcastRoom($room, json_encode([
            'type' => 'system',
            'text' => "{$user} 加入了房间",
        ], JSON_UNESCAPED_UNICODE));

        // 推送房间状态
        $roomUpdate = [
            'type' => 'room_update',
            'room' => $room,
            'players' => [
                'p1' => $roomState[$room]['p1'],
                'p2' => $roomState[$room]['p2'],
            ],
            'player_ids' => [
                'p1' => $roomState[$room]['p1_id'] ?? null,
                'p2' => $roomState[$room]['p2_id'] ?? null,
            ],
            'spectators' => $roomState[$room]['spectators'],
            'spectators_list' => $roomState[$room]['spectators_list'] ?? [],
            'status' => $roomState[$room]['status'],
            'last_result' => $roomState[$room]['last_result'] ?? null,
        ];
        $payloadUpdate = json_encode($roomUpdate, JSON_UNESCAPED_UNICODE);
        $broadcastRoom($room, $payloadUpdate);
        // 也给当前连接单播一份，避免广播过滤异常导致自己收不到
        $connection->send($payloadUpdate);

        // 启动超时检测定时器（每60秒检查一次，5分钟不操作自动踢到观众）
        if (!isset($roomState[$room]['timeout_check_timer'])) {
            $roomState[$room]['timeout_check_timer'] = Timer::add(60, function () use (&$roomState, &$userConnections, $broadcastRoom, $room) {
                $timeoutSeconds = 300; // 5分钟超时
                $now = time();
                $kicked = false;
                
                // 检查玩家一
                if (!empty($roomState[$room]['p1']) && isset($roomState[$room]['player_last_activity']['p1'])) {
                    $lastActivity = $roomState[$room]['player_last_activity']['p1'];
                    if (($now - $lastActivity) > $timeoutSeconds) {
                        $user = $roomState[$room]['p1'];
                        $roomState[$room]['p1'] = null;
                        $roomState[$room]['p1_id'] = null;
                        unset($roomState[$room]['player_last_activity']['p1']);
                        // 更新连接状态
                        if (isset($userConnections[$room][$user])) {
                            $userConnections[$room][$user]->role = 'spectator';
                            $userConnections[$room][$user]->seat = null;
                        }
                        // 添加到观众列表
                        if (!in_array($user, $roomState[$room]['spectators_list'] ?? [])) {
                            $roomState[$room]['spectators_list'][] = $user;
                        }
                        $roomState[$room]['spectators'] = ($roomState[$room]['spectators'] ?? 0) + 1;
                        $roomState[$room]['status'] = 'idle';
                        $broadcastRoom($room, json_encode([
                            'type' => 'system',
                            'text' => "{$user} 因长时间未操作，已自动切换到观众席位",
                        ], JSON_UNESCAPED_UNICODE));
                        $kicked = true;
                    }
                }
                
                // 检查玩家二
                if (!empty($roomState[$room]['p2']) && isset($roomState[$room]['player_last_activity']['p2'])) {
                    $lastActivity = $roomState[$room]['player_last_activity']['p2'];
                    if (($now - $lastActivity) > $timeoutSeconds) {
                        $user = $roomState[$room]['p2'];
                        $roomState[$room]['p2'] = null;
                        $roomState[$room]['p2_id'] = null;
                        unset($roomState[$room]['player_last_activity']['p2']);
                        // 更新连接状态
                        if (isset($userConnections[$room][$user])) {
                            $userConnections[$room][$user]->role = 'spectator';
                            $userConnections[$room][$user]->seat = null;
                        }
                        // 添加到观众列表
                        if (!in_array($user, $roomState[$room]['spectators_list'] ?? [])) {
                            $roomState[$room]['spectators_list'][] = $user;
                        }
                        $roomState[$room]['spectators'] = ($roomState[$room]['spectators'] ?? 0) + 1;
                        $roomState[$room]['status'] = 'idle';
                        $broadcastRoom($room, json_encode([
                            'type' => 'system',
                            'text' => "{$user} 因长时间未操作，已自动切换到观众席位",
                        ], JSON_UNESCAPED_UNICODE));
                        $kicked = true;
                    }
                }
                
                // 如果有玩家被踢，广播房间更新
                if ($kicked) {
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
                }
            }, [], true); // 永久定时器
        }

        // 不再自动开局，需要玩家主动点击"开始对局"按钮
    };
};

$worker->onMessage = function (TcpConnection $connection, $data) use (&$roomState, &$userConnections, $broadcastRoom, $startDuel, $postJson) {
    $room = $connection->room ?? 'global';
    $raw = trim((string)$data);
    if ($raw === '') {
        return;
    }
    // 解析 JSON 指令
    $isJson = false;
    $decoded = null;
    if ($raw[0] === '{') {
        $decoded = json_decode($raw, true);
        $isJson = is_array($decoded);
    }

    // 兜底处理：即便 JSON 解析失败，但包含 room_state_request 字样，也返回房间状态（防止请求被当作普通消息广播）
    if (!$isJson && str_contains($raw, '"type":"room_state_request"')) {
        if (($connection->room ?? 'global') === 'global') {
            $roomsSnapshot = [];
            foreach ($roomState as $rKey => $state) {
                $roomsSnapshot[] = [
                    'room' => $rKey,
                    'players' => [
                        'p1' => $state['p1'] ?? null,
                        'p2' => $state['p2'] ?? null,
                    ],
                    'spectators' => $state['spectators'] ?? 0,
                    'spectators_list' => $state['spectators_list'] ?? [],
                ];
            }
            $connection->send(json_encode([
                'type' => 'room_state',
                'rooms' => $roomsSnapshot,
            ], JSON_UNESCAPED_UNICODE));
        }
        return;
    }

    // 大厅请求房间在线状态（仅 global 房间处理）
    if ($isJson && ($decoded['type'] ?? '') === 'room_state_request') {
        if (($connection->room ?? 'global') === 'global') {
            $roomsSnapshot = [];
            foreach ($roomState as $rKey => $state) {
                $roomsSnapshot[] = [
                    'room' => $rKey,
                    'players' => [
                        'p1' => $state['p1'] ?? null,
                        'p2' => $state['p2'] ?? null,
                    ],
                    'spectators' => $state['spectators'] ?? 0,
                    'spectators_list' => $state['spectators_list'] ?? [],
                ];
            }
            $connection->send(json_encode([
                'type' => 'room_state',
                'rooms' => $roomsSnapshot,
            ], JSON_UNESCAPED_UNICODE));
        }
        return;
    }

    if ($isJson && ($decoded['type'] ?? '') === 'start') {
        $seat = $connection->seat ?? null;
        // 只有玩家（seat 1 或 2）才能发送 start 消息
        if ($seat !== 1 && $seat !== 2) {
            $connection->send(json_encode([
                'type' => 'error',
                'text' => '只有玩家可以开始对局',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }
        // 检查房间状态，确保不在进行中
        if (($roomState[$room]['status'] ?? 'idle') !== 'idle') {
            $connection->send(json_encode([
                'type' => 'error',
                'text' => '游戏正在进行中，无法开始新对局',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }
        // 检查两个座位是否都有玩家
        if (empty($roomState[$room]['p1']) || empty($roomState[$room]['p2'])) {
            $connection->send(json_encode([
                'type' => 'error',
                'text' => '需要两名玩家才能开始对局',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }
        $readyKey = $seat === 1 ? 'p1' : 'p2';
        // 更新玩家活动时间
        $roomState[$room]['player_last_activity'][$readyKey] = time();
        $roomState[$room]['ready'][$readyKey] = true;
        // 首个点击的人作为先手
        if (empty($roomState[$room]['starter'])) {
            $roomState[$room]['starter'] = $readyKey;
        }
        $broadcastRoom($room, json_encode([
            'type' => 'system',
            'text' => ($connection->user ?? 'guest') . ' 已准备，等待另一位玩家确认',
        ], JSON_UNESCAPED_UNICODE));
        // 只有当两个玩家都点击了"开始对局"按钮时，才开始游戏
        if (($roomState[$room]['ready']['p1'] ?? false) && ($roomState[$room]['ready']['p2'] ?? false)) {
            $starterTurn = $roomState[$room]['starter'] ?? 'p1';
            $roomState[$room]['ready'] = ['p1' => false, 'p2' => false];
            $roomState[$room]['starter'] = null;
            $startDuel($room, $starterTurn);
        }
        return;
    }

    if ($isJson && ($decoded['type'] ?? '') === 'leave_seat') {
        // 玩家主动离开座位，切换到观众
        $seat = $connection->seat ?? null;
        $user = $connection->user ?? 'guest';
        if ($seat === 1 && ($roomState[$room]['p1'] ?? null) === $user) {
            $roomState[$room]['p1'] = null;
            $roomState[$room]['p1_id'] = null;
            unset($roomState[$room]['player_last_activity']['p1']);
            $connection->role = 'spectator';
            $connection->seat = null;
            // 添加到观众列表
            if (!in_array($user, $roomState[$room]['spectators_list'] ?? [])) {
                $roomState[$room]['spectators_list'][] = $user;
            }
            $roomState[$room]['spectators'] = ($roomState[$room]['spectators'] ?? 0) + 1;
            $roomState[$room]['status'] = 'idle';
            $broadcastRoom($room, json_encode([
                'type' => 'system',
                'text' => "{$user} 离开座位，切换到观众",
            ], JSON_UNESCAPED_UNICODE));
        } elseif ($seat === 2 && ($roomState[$room]['p2'] ?? null) === $user) {
            $roomState[$room]['p2'] = null;
            $roomState[$room]['p2_id'] = null;
            unset($roomState[$room]['player_last_activity']['p2']);
            $connection->role = 'spectator';
            $connection->seat = null;
            // 添加到观众列表
            if (!in_array($user, $roomState[$room]['spectators_list'] ?? [])) {
                $roomState[$room]['spectators_list'][] = $user;
            }
            $roomState[$room]['spectators'] = ($roomState[$room]['spectators'] ?? 0) + 1;
            $roomState[$room]['status'] = 'idle';
            $broadcastRoom($room, json_encode([
                'type' => 'system',
                'text' => "{$user} 离开座位，切换到观众",
            ], JSON_UNESCAPED_UNICODE));
        }
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

    if ($isJson && ($decoded['type'] ?? '') === 'kick_player') {
        // 房主踢人功能
        $kickerUid = $connection->uid ?? 0;
        $targetSeat = intval($decoded['seat'] ?? 0);
        
        // 检查是否是房主
        $ownerId = $roomState[$room]['owner_id'] ?? null;
        if (!$ownerId || $kickerUid != $ownerId) {
            $connection->send(json_encode([
                'type' => 'error',
                'text' => '只有房主可以踢人',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }
        
        // 检查目标座位
        if ($targetSeat !== 1 && $targetSeat !== 2) {
            $connection->send(json_encode([
                'type' => 'error',
                'text' => '无效的座位',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }
        
        $playerKey = $targetSeat === 1 ? 'p1' : 'p2';
        $kickedUser = $roomState[$room][$playerKey] ?? null;
        
        if (!$kickedUser) {
            $connection->send(json_encode([
                'type' => 'error',
                'text' => '该座位没有玩家',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }
        
        // 将被踢玩家移到观众席位
        $roomState[$room][$playerKey] = null;
        $roomState[$room][$playerKey . '_id'] = null;
        unset($roomState[$room]['player_last_activity'][$playerKey]);
        
        // 更新被踢玩家的连接状态
        if (isset($userConnections[$room][$kickedUser])) {
            $kickedConnection = $userConnections[$room][$kickedUser];
            $kickedConnection->role = 'spectator';
            $kickedConnection->seat = null;
        }
        
        // 添加到观众列表
        if (!in_array($kickedUser, $roomState[$room]['spectators_list'] ?? [])) {
            $roomState[$room]['spectators_list'][] = $kickedUser;
        }
        $roomState[$room]['spectators'] = ($roomState[$room]['spectators'] ?? 0) + 1;
        $roomState[$room]['status'] = 'idle';
        
        $broadcastRoom($room, json_encode([
            'type' => 'system',
            'text' => "{$kickedUser} 被房主踢到观众席位",
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

    if ($isJson && ($decoded['type'] ?? '') === 'roll') {
        $turn = $roomState[$room]['turn'] ?? null;
        $seat = $connection->seat ?? null;
        $userName = $connection->user ?? 'guest';
        if ($turn === 'p1' && $seat === 1 || $turn === 'p2' && $seat === 2) {
            // 更新玩家活动时间
            if ($seat === 1) {
                $roomState[$room]['player_last_activity']['p1'] = time();
            } elseif ($seat === 2) {
                $roomState[$room]['player_last_activity']['p2'] = time();
            }
            // 接收用户投掷（选项仅作展示，不影响数值）
            $roll = [random_int(1, 6), random_int(1, 6)];
            $roomState[$room]['rolls'][$turn] = $roll;
            $broadcastRoom($room, json_encode([
                'type' => 'roll_done',
                'room' => $room,
                'player' => $userName,
                'turn' => $turn,
                'roll' => $roll,
                'options' => $decoded,
                'auto' => false,
            ], JSON_UNESCAPED_UNICODE));
            // 继续流程：检查另一个玩家是否已投掷
            if (isset($roomState[$room]['timers'])) {
                foreach ($roomState[$room]['timers'] as $t) Timer::del($t);
                $roomState[$room]['timers'] = [];
            }
            
            $otherKey = $turn === 'p1' ? 'p2' : 'p1';
            $otherRolled = isset($roomState[$room]['rolls'][$otherKey]);
            
            if (!$otherRolled) {
                // 另一个玩家还未投掷，请求其投掷
                $roomState[$room]['turn'] = $otherKey;
                $roomState[$room]['timers'] = $roomState[$room]['timers'] ?? [];
                $broadcastRoom($room, json_encode([
                    'type' => 'roll_request',
                    'room' => $room,
                    'player' => $roomState[$room][$otherKey],
                    'turn' => $otherKey,
                    'timeout' => 30,
                ], JSON_UNESCAPED_UNICODE));
                $t2 = Timer::add(30, function () use (&$roomState, $broadcastRoom, $room, $otherKey, $postJson) {
                    $roll = [random_int(1, 6), random_int(1, 6)];
                    $roomState[$room]['rolls'][$otherKey] = $roll;
                    $broadcastRoom($room, json_encode([
                        'type' => 'roll_done',
                        'room' => $room,
                        'player' => $roomState[$room][$otherKey],
                        'turn' => $otherKey,
                        'roll' => $roll,
                        'auto' => true,
                    ], JSON_UNESCAPED_UNICODE));
                    // 两个玩家都已投掷，结算
                    $r1 = $roomState[$room]['rolls']['p1'] ?? [random_int(1, 6), random_int(1, 6)];
                    $r2 = $roomState[$room]['rolls']['p2'] ?? [random_int(1, 6), random_int(1, 6)];
                    $s1 = array_sum($r1);
                    $s2 = array_sum($r2);
                    $winner = $s1 > $s2 ? $roomState[$room]['p1'] : ($s2 > $s1 ? $roomState[$room]['p2'] : null);
                    $result = [
                        'type' => 'duel_result',
                        'room' => $room,
                        'p1' => $roomState[$room]['p1'],
                        'p2' => $roomState[$room]['p2'],
                        'p1_roll' => $r1,
                        'p2_roll' => $r2,
                        'p1_sum' => $s1,
                        'p2_sum' => $s2,
                        'winner' => $winner,
                        'time' => date('H:i:s'),
                    ];
                    $roomState[$room]['last_result'] = $result;
                    $roomState[$room]['status'] = 'idle';
                    $roomState[$room]['turn'] = null;
                    $broadcastRoom($room, json_encode($result, JSON_UNESCAPED_UNICODE));
                    $broadcastRoom($room, json_encode([
                        'type' => 'room_update',
                        'room' => $room,
                        'players' => [
                            'p1' => $roomState[$room]['p1'],
                            'p2' => $roomState[$room]['p2'],
                        ],
                        'spectators' => $roomState[$room]['spectators'],
                        'spectators_list' => $roomState[$room]['spectators_list'] ?? [],
                        'status' => $roomState[$room]['status'],
                        'last_result' => $roomState[$room]['last_result'] ?? null,
                    ], JSON_UNESCAPED_UNICODE));
                    
                    // 结算（防重复，通过 duel_settled 标记）
                    if (
                        (($roomState[$room]['duel_settled'] ?? false) === false) &&
                        ($roomState[$room]['p1_id'] ?? null) &&
                        ($roomState[$room]['p2_id'] ?? null) &&
                        isset($result['winner']) &&
                        str_starts_with($room, 'mars-table-')
                    ) {
                        $tableId = intval(substr($room, strlen('mars-table-')));
                        $winnerId = null;
                        if ($result['winner'] === ($roomState[$room]['p1'] ?? '')) {
                            $winnerId = $roomState[$room]['p1_id'];
                        } elseif ($result['winner'] === ($roomState[$room]['p2'] ?? '')) {
                            $winnerId = $roomState[$room]['p2_id'];
                        } else {
                            $winnerId = 0; // 平局
                        }
                        $settleResult = $postJson([
                            'action' => 'mars_duel_settle',
                            'table_id' => $tableId,
                            'p1_user_id' => $roomState[$room]['p1_id'],
                            'p2_user_id' => $roomState[$room]['p2_id'],
                            'winner_user_id' => $winnerId,
                            'duel_id' => $roomState[$room]['duel_id'] ?? null,
                        ]);
                        $roomState[$room]['duel_settled'] = true;
                        // 将结算结果添加到 result 中
                        if (isset($settleResult['data'])) {
                            $result['winner_bonus'] = $settleResult['data']['winner_bonus'] ?? 0;
                            $result['p1_bonus'] = $settleResult['data']['p1_bonus'] ?? 0;
                            $result['p2_bonus'] = $settleResult['data']['p2_bonus'] ?? 0;
                        }
                    }
                }, [], false);
                $roomState[$room]['timers'][] = $t2;
            } else {
                // 两个玩家都已投掷，结算
                $r1 = $roomState[$room]['rolls']['p1'] ?? [random_int(1, 6), random_int(1, 6)];
                $r2 = $roomState[$room]['rolls']['p2'] ?? [random_int(1, 6), random_int(1, 6)];
                $s1 = array_sum($r1);
                $s2 = array_sum($r2);
                $winner = $s1 > $s2 ? $roomState[$room]['p1'] : ($s2 > $s1 ? $roomState[$room]['p2'] : null);
                $result = [
                    'type' => 'duel_result',
                    'room' => $room,
                    'p1' => $roomState[$room]['p1'],
                    'p2' => $roomState[$room]['p2'],
                    'p1_roll' => $r1,
                    'p2_roll' => $r2,
                    'p1_sum' => $s1,
                    'p2_sum' => $s2,
                    'winner' => $winner,
                    'time' => date('H:i:s'),
                ];
                // 结算：调用内部接口（在广播结果前结算，以便将结果包含在消息中），防重复
                if (
                    (($roomState[$room]['duel_settled'] ?? false) === false) &&
                    ($roomState[$room]['p1_id'] ?? null) &&
                    ($roomState[$room]['p2_id'] ?? null) &&
                    isset($result['winner']) &&
                    str_starts_with($room, 'mars-table-')
                ) {
                    $tableId = intval(substr($room, strlen('mars-table-')));
                    $winnerId = null;
                    if ($result['winner'] === ($roomState[$room]['p1'] ?? '')) {
                        $winnerId = $roomState[$room]['p1_id'];
                    } elseif ($result['winner'] === ($roomState[$room]['p2'] ?? '')) {
                        $winnerId = $roomState[$room]['p2_id'];
                    } else {
                        $winnerId = 0; // 平局
                    }
                    $settleResult = $postJson([
                        'action' => 'mars_duel_settle',
                        'table_id' => $tableId,
                        'p1_user_id' => $roomState[$room]['p1_id'],
                        'p2_user_id' => $roomState[$room]['p2_id'],
                        'winner_user_id' => $winnerId,
                        'duel_id' => $roomState[$room]['duel_id'] ?? null,
                    ]);
                    $roomState[$room]['duel_settled'] = true;
                    // 将结算结果添加到 result 中
                    if (isset($settleResult['data'])) {
                        $result['winner_bonus'] = $settleResult['data']['winner_bonus'] ?? 0;
                        $result['p1_bonus'] = $settleResult['data']['p1_bonus'] ?? 0;
                        $result['p2_bonus'] = $settleResult['data']['p2_bonus'] ?? 0;
                    }
                }
                $roomState[$room]['last_result'] = $result;
                $roomState[$room]['status'] = 'idle';
                $roomState[$room]['turn'] = null;
                $broadcastRoom($room, json_encode($result, JSON_UNESCAPED_UNICODE));
                $broadcastRoom($room, json_encode([
                    'type' => 'room_update',
                    'room' => $room,
                    'players' => [
                        'p1' => $roomState[$room]['p1'],
                        'p2' => $roomState[$room]['p2'],
                    ],
                    'spectators' => $roomState[$room]['spectators'],
                    'spectators_list' => $roomState[$room]['spectators_list'] ?? [],
                    'status' => $roomState[$room]['status'],
                    'last_result' => $roomState[$room]['last_result'] ?? null,
                ], JSON_UNESCAPED_UNICODE));

            }
        }
        return;
    }

    if ($isJson && ($decoded['type'] ?? '') === 'cheer') {
        // 更新玩家活动时间（如果是玩家）
        $seat = $connection->seat ?? null;
        if ($seat === 1) {
            $roomState[$room]['player_last_activity']['p1'] = time();
        } elseif ($seat === 2) {
            $roomState[$room]['player_last_activity']['p2'] = time();
        }
        $emoji = $decoded['emoji'] ?? '👏';
        $broadcastRoom($room, json_encode([
            'type' => 'cheer',
            'user' => $connection->user ?? 'guest',
            'emoji' => $emoji,
            'time' => date('H:i:s'),
        ], JSON_UNESCAPED_UNICODE));
        return;
    }
    
    if ($isJson && ($decoded['type'] ?? '') === 'rematch') {
        // 更新玩家活动时间（如果是玩家）
        $seat = $connection->seat ?? null;
        if ($seat === 1) {
            $roomState[$room]['player_last_activity']['p1'] = time();
        } elseif ($seat === 2) {
            $roomState[$room]['player_last_activity']['p2'] = time();
        }
        // rematch 处理逻辑（如果存在）
        return;
    }

    // 普通聊天
    // 更新玩家活动时间（如果是玩家）
    $seat = $connection->seat ?? null;
    if ($seat === 1) {
        $roomState[$room]['player_last_activity']['p1'] = time();
    } elseif ($seat === 2) {
        $roomState[$room]['player_last_activity']['p2'] = time();
    }
    $payload = json_encode([
        'type' => 'message',
        'user' => $connection->user ?? 'guest',
        'text' => $raw,
        'time' => date('H:i:s'),
        'room' => $room,
    ], JSON_UNESCAPED_UNICODE);
    $broadcastRoom($room, $payload);
};

$worker->onClose = function (TcpConnection $connection) use (&$roomState, &$userConnections, $broadcastRoom) {
    $room = $connection->room ?? 'global';
    $user = $connection->user ?? 'guest';
    $role = $connection->role ?? 'spectator';
    $seat = $connection->seat ?? null;
    
    // 从连接映射中移除
    if (isset($userConnections[$room][$user])) {
        unset($userConnections[$room][$user]);
    }

        if (isset($roomState[$room])) {
        if ($role === 'player') {
            if ($seat === 1 && $roomState[$room]['p1'] === $user) {
                $roomState[$room]['p1'] = null;
                    $roomState[$room]['p1_id'] = null;
            }
            if ($seat === 2 && $roomState[$room]['p2'] === $user) {
                $roomState[$room]['p2'] = null;
                    $roomState[$room]['p2_id'] = null;
            }
        } else {
            if ($roomState[$room]['spectators'] > 0) {
                $roomState[$room]['spectators']--;
            }
            // 从观众列表中移除
            $key = array_search($user, $roomState[$room]['spectators_list']);
            if ($key !== false) {
                unset($roomState[$room]['spectators_list'][$key]);
                $roomState[$room]['spectators_list'] = array_values($roomState[$room]['spectators_list']);
            }
        }
        // 若有人离席，重置为 idle
        $roomState[$room]['status'] = 'idle';
    }

    $broadcastRoom($room, json_encode([
        'type' => 'system',
        'text' => "{$user} 离开了房间",
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
};

if (!defined('GLOBAL_START')) {
    Worker::runAll();
}

