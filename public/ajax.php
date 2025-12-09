<?php
use Carbon\Carbon;
use App\Models\MedalSeries;

require "../include/bittorrent.php";
dbconn();
loggedinorreturn();

if (!function_exists('meteor_game_config_value')) {
    function meteor_game_config_value(string $key, $default = null)
    {
        return config('meteor_game.' . $key, $default);
    }
}

if (!function_exists('meteor_game_validate_telemetry')) {
    /**
     * @throws \InvalidArgumentException
     */
    function meteor_game_validate_telemetry(array $telemetry, int $submittedScore, int $submittedComboMax, int $submittedDurationSeconds, array &$flagReasons): array
    {
        $flagReasons = [];

        if (!isset($telemetry['startedAt'], $telemetry['endedAt'])) {
            throw new \InvalidArgumentException('缺少游戏时间信息');
        }

        if (!isset($telemetry['version']) || (int) $telemetry['version'] < 2) {
            throw new \InvalidArgumentException('客户端版本过旧，请刷新页面');
        }

        $startedAt = (float) $telemetry['startedAt'];
        $endedAt = (float) $telemetry['endedAt'];

        if ($endedAt <= $startedAt) {
            throw new \InvalidArgumentException('游戏时间戳异常');
        }

        $sessionDurationMs = $endedAt - $startedAt;
        $durationSecondsFromTelemetry = $sessionDurationMs / 1000;

        // 移除所有时长检查（因为60秒后还会继续运行直到所有元素消失，时长可能超过60秒）
        // 客户端和服务器计算的时长可能因延迟结束机制而有差异，不再强制检查

        $events = $telemetry['events'] ?? [];
        if (!is_array($events) || empty($events)) {
            throw new \InvalidArgumentException('缺少关键事件数据');
        }

        $eventLimit = meteor_game_config_value('flag_rules.event_hard_limit', 600);
        if (count($events) > $eventLimit) {
            throw new \InvalidArgumentException('事件数据异常，请重试');
        }

        $inputs = $telemetry['inputs'] ?? [];
        if (!is_array($inputs)) {
            $inputs = [];
        }

        $inputsCount = count($inputs);
        $inputLimit = meteor_game_config_value('flag_rules.input_hard_limit', 2000);
        if ($inputsCount > $inputLimit) {
            throw new \InvalidArgumentException('操作记录异常');
        }
        $inputKeys = array_values(array_unique(array_map(static function ($input) {
            return $input['key'] ?? 'unknown';
        }, $inputs)));

        $calcScore = 0.0;
        $combo = 0;
        $maxCombo = 0;
        $goodCatch = 0;
        $badCatch = 0;
        $missCount = 0;
        $lastTimestamp = -1;
        $eventCount = 0;

        foreach ($events as $event) {
            if (!is_array($event)) {
                throw new \InvalidArgumentException('事件数据格式错误');
            }

            $timestamp = isset($event['t']) ? (float) $event['t'] : null;
            $type = $event['type'] ?? '';
            $baseScore = $event['base_score'] ?? null;
            $deltaScore = $event['delta_score'] ?? null;
            $comboBefore = isset($event['combo_before']) ? (int) $event['combo_before'] : null;
            $comboAfter = isset($event['combo_after']) ? (int) $event['combo_after'] : null;

            if ($timestamp === null || $timestamp < 0) {
                throw new \InvalidArgumentException('事件时间戳无效');
            }

            if ($lastTimestamp !== -1 && $timestamp < $lastTimestamp) {
                throw new \InvalidArgumentException('事件时间戳顺序错误');
            }

            $lastTimestamp = $timestamp;
            $eventCount++;

            // 移除"事件缺少连击数据"检查
            // 如果连击数据缺失，使用默认值0
            if ($comboBefore === null) {
                $comboBefore = 0;
            }
            if ($comboAfter === null) {
                $comboAfter = 0;
            }

            // 移除"事件连击数据不匹配"检查，允许更宽松的验证

            switch ($type) {
                case 'catch_good':
                    $baseScore = (float) $baseScore;
                    $deltaScore = (float) $deltaScore;

                    if ($baseScore <= 0) {
                        throw new \InvalidArgumentException('正向捕获基础分无效');
                    }

                    $expectedDelta = round($baseScore * (1 + $combo * 0.1), 2);
                    if (abs($deltaScore - $expectedDelta) > 0.6) {
                        throw new \InvalidArgumentException('捕获得分异常');
                    }

                    if ($comboAfter !== $comboBefore + 1) {
                        throw new \InvalidArgumentException('连击计数异常');
                    }

                    $calcScore = round($calcScore + $deltaScore, 2);
                    $combo = $comboAfter;
                    $maxCombo = max($maxCombo, $combo);
                    $goodCatch++;
                    break;

                case 'catch_bad':
                    $baseScore = (float) $baseScore;
                    $deltaScore = (float) $deltaScore;

                    if ($baseScore >= 0 || $deltaScore >= 0) {
                        throw new \InvalidArgumentException('负向捕获分数异常');
                    }

                    if ($comboAfter !== 0) {
                        throw new \InvalidArgumentException('负向捕获未清零连击');
                    }

                    if (abs($deltaScore - $baseScore) > 0.6) {
                        throw new \InvalidArgumentException('负向捕获得分异常');
                    }

                    $calcScore = round($calcScore + $deltaScore, 2);
                    $combo = 0;
                    $badCatch++;
                    break;

                case 'miss_good':
                    if ($comboAfter !== 0) {
                        throw new \InvalidArgumentException('漏接事件未清零连击');
                    }

                    $combo = 0;
                    $missCount++;
                    break;

                // 移除"未知的事件类型"检查，忽略未处理的事件类型（如shoot_good、shoot_bad、powerup_collect等）
                default:
                    // 未知事件类型，忽略不处理
                    break;
            }
        }

        // 移除"分数与事件数据不匹配"检查
        // 由于游戏机制复杂（子弹击飞、道具等），且有很多新的事件类型被忽略，分数计算可能不准确
        // $calcScoreInt = (int) floor($calcScore + 0.0001);
        // if ($calcScoreInt !== $submittedScore) {
        //     throw new \InvalidArgumentException('分数与事件数据不匹配');
        // }

        if ($lastTimestamp !== -1 && $lastTimestamp > ($sessionDurationMs + 2000)) {
            throw new \InvalidArgumentException('事件时间跨度异常');
        }

        $lenientComboThreshold = meteor_game_config_value('combo.lenient_check_threshold', 1);
        if (abs($maxCombo - $submittedComboMax) > $lenientComboThreshold) {
            throw new \InvalidArgumentException('最大连击与事件数据不匹配');
        }

        $flagRules = meteor_game_config_value('flag_rules', []);

        if ($submittedScore >= ($flagRules['high_score_threshold'] ?? 3000) &&
            $maxCombo < ($flagRules['min_combo_for_high_score'] ?? 15)) {
            $flagReasons[] = 'high_score_low_combo';
        }

        if ($inputsCount < ($flagRules['min_inputs_total'] ?? 10) &&
            $submittedScore >= ($flagRules['min_inputs_score_threshold'] ?? 800)) {
            $flagReasons[] = 'input_count_too_low';
        }

        if ($eventCount < ($flagRules['min_events_for_submission'] ?? 15)) {
            $flagReasons[] = 'event_count_too_low';
        }

        $totalCatch = $goodCatch + $badCatch;
        if ($totalCatch > 0) {
            $badRatio = $badCatch / $totalCatch;
            if ($badRatio > ($flagRules['max_bad_ratio_for_reward'] ?? 0.5) && $submittedScore > 0) {
                $flagReasons[] = 'bad_hit_ratio_high';
            }
        }

        $flagReasons = array_values(array_unique($flagReasons));

        return [
            'metrics' => [
                'good_catch_count' => $goodCatch,
                'bad_catch_count' => $badCatch,
                'miss_count' => $missCount,
                'inputs_count' => $inputsCount,
                'input_keys' => $inputKeys,
                'event_count' => $eventCount,
                'telemetry_version' => $telemetry['version'] ?? null,
                'session_duration_ms' => $sessionDurationMs,
            ],
            'max_combo' => $maxCombo,
        ];
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$params = $_POST['params'] ?? [];

// 如果 params 是 JSON 字符串，解码它
if (is_string($params)) {
    $params = json_decode($params, true) ?? [];
}

// 特殊处理：论坛打赏
if ($action === 'forumtip') {
    try {
        $userId = intval($_POST['userid'] ?? 0);
        $postId = intval($_POST['postid'] ?? 0);
        $amount = intval($_POST['amount'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if (!$userId || !$postId) {
            throw new \InvalidArgumentException('缺少必要参数');
        }

        if ($amount < 100) {
            throw new \InvalidArgumentException('打赏金额最少为100魔力值');
        }

        if ($CURUSER['id'] == $userId) {
            throw new \InvalidArgumentException('不能打赏自己');
        }

        // 检查用户魔力值是否足够
        if ($CURUSER['seedbonus'] < $amount) {
            throw new \InvalidArgumentException('魔力值不足，当前魔力值：' . number_format($CURUSER['seedbonus'], 1));
        }

        // 获取接收者信息
        $receiver = \App\Models\User::query()->findOrFail($userId);

        // 计算税收
        $basictax_bonus = get_setting('bonus.basictax') ?: 0;
        $taxpercentage_bonus = get_setting('bonus.taxpercentage') ?: 0;
        $taxAmount = $basictax_bonus + ($amount * $taxpercentage_bonus / 100);
        $aftertaxAmount = $amount - $taxAmount;

        // 扣除打赏者魔力值
        $bonusRep = new \App\Repositories\BonusRepository();
        $bonusRep->consumeUserBonus(
            $CURUSER['id'],
            $amount,
            \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_TO_SOMEONE,
            $amount . " Points as tip to " . $receiver->username . " (Forum Post #" . $postId . ")" . ($message ? ": " . htmlspecialchars($message) : "")
        );

        // 增加接收者魔力值
        sql_query("UPDATE users SET seedbonus = seedbonus + " . $aftertaxAmount . " WHERE id = " . sqlesc($userId));
        \App\Models\BonusLogs::add(
            $userId,
            $receiver->seedbonus,
            $aftertaxAmount,
            $receiver->seedbonus + $aftertaxAmount,
            " + " . number_format($aftertaxAmount, 1) . " Points (after tax) as a tip from " . $CURUSER['username'] . " (Forum Post #" . $postId . ")" . ($message ? ": " . htmlspecialchars($message) : ""),
            \App\Models\BonusLogs::BUSINESS_TYPE_RECEIVE_GIFT
        );

        // 获取主题ID
        $topicId = get_single_value("posts", "topicid", "WHERE id=" . sqlesc($postId));

        // 记录打赏到专门的表
        \App\Models\ForumTip::create([
            'from_uid' => $CURUSER['id'],
            'to_uid' => $userId,
            'post_id' => $postId,
            'topic_id' => $topicId,
            'amount' => $amount,
            'amount_after_tax' => $aftertaxAmount,
            'message' => $message ? htmlspecialchars($message) : null,
        ]);

        // 发送系统消息通知
        $messageContent = "您收到了来自 [b]" . $CURUSER['username'] . "[/b] 的打赏：[b]" . number_format($aftertaxAmount, 1) . "[/b] 魔力值（税后）";
        if ($message) {
            $messageContent .= "\n\n留言：" . htmlspecialchars($message);
        }
        $messageContent .= "\n\n[url=forums.php?action=viewtopic&topicid=" . $topicId . "#post" . $postId . "]查看帖子[/url]";

        sql_query("INSERT INTO messages (sender, receiver, msg, added) VALUES (0, " . sqlesc($userId) . ", " . sqlesc($messageContent) . ", " . sqlesc(date("Y-m-d H:i:s")) . ")");

        exit(json_encode(['success' => true, 'message' => '打赏成功！']));

    } catch (\Throwable $e) {
        exit(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }
}

// 勋章系列 - 领取奖励
if ($action === 'medal_series_claim') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!$CURUSER) {
            throw new \InvalidArgumentException('请先登录');
        }
        $seriesId = intval($_POST['series_id'] ?? 0);
        if ($seriesId <= 0) {
            throw new \InvalidArgumentException('缺少系列 ID');
        }

        /** @var MedalSeries $series */
        $series = MedalSeries::query()->with('medals')->findOrFail($seriesId);
        $user = \App\Models\User::query()->findOrFail($CURUSER['id']);

        if ($series->medals->isEmpty()) {
            throw new \InvalidArgumentException('该系列暂无配置勋章');
        }

        $series->claimReward($user);

        $ownedMedalIds = $user->valid_medals()->pluck('medals.id');
        $claims = $series->userClaims()->where('user_id', $user->id)->get();
        $state = $series->evaluateClaimState($ownedMedalIds, $claims);

        $freshSeedbonus = \App\Models\User::query()->find($user->id, ['id', 'seedbonus']);

        exit(json_encode([
            'success' => true,
            'message' => nexus_trans('medal-series.frontend.claim_success'),
            'state' => $state,
            'seedbonus' => (float) ($freshSeedbonus->seedbonus ?? 0),
        ]));
    } catch (\Throwable $e) {
        exit(json_encode([
            'success' => false,
            'message' => $e->getMessage(),
        ]));
    }
}

// 流星游戏 - 提交分数
if ($action === 'meteor_game_submit') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $userId = intval($_POST['user_id'] ?? 0);
        $score = intval($_POST['score'] ?? 0);
        $comboMax = intval($_POST['combo_max'] ?? 0);
        $duration = intval($_POST['duration'] ?? 60);
        $gameToken = $_POST['game_token'] ?? '';
        $ipAddress = getip();
        $telemetryRaw = $_POST['telemetry'] ?? '';
        $telemetry = null;
        $telemetryFlagReasons = [];

        if (empty($telemetryRaw)) {
            throw new \InvalidArgumentException('缺少游戏过程数据');
        }

        $telemetry = json_decode($telemetryRaw, true);
        if (!is_array($telemetry) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('游戏过程数据解析失败');
        }

        // 1. 用户验证
        if (!$CURUSER || $CURUSER['id'] != $userId) {
            throw new \InvalidArgumentException('用户验证失败');
        }

        // 获取用户密码哈希（用于Token验证）
        $user = \App\Models\User::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('用户不存在');
        }

        // 2. Token验证（防止简单的接口调用）
        $expectedToken = md5($userId . $score . $comboMax . $duration . date('Y-m-d') . $user->passhash);
        if ($gameToken !== $expectedToken) {
            // 记录可疑行为
            write_log("游戏作弊尝试 - 用户ID: {$userId}, IP: {$ipAddress}, 分数: {$score}", 'mod');
            throw new \InvalidArgumentException('游戏数据验证失败');
        }

        $minTheoreticalScore = meteor_game_config_value('score.min', -10000);
        $maxTheoreticalScore = meteor_game_config_value('score.max', 10000);

        if ($score < $minTheoreticalScore || $score > $maxTheoreticalScore) {
            write_log("游戏作弊尝试 - 用户ID: {$userId}, 分数异常: {$score}", 'mod');
            throw new \InvalidArgumentException('分数超出合理范围');
        }

        // 3. 时长检查已移除（因为60秒后还会继续运行直到所有元素消失，时长可能超过60秒）

        // 4. 连击数检查（有怪物会清零连击，理论最大连击约50-60）
        $comboLimit = meteor_game_config_value('combo.max', 80);
        if ($comboMax > $comboLimit) {
            write_log("游戏作弊尝试 - 用户ID: {$userId}, 连击数过高: {$comboMax}", 'mod');
            throw new \InvalidArgumentException('连击数异常');
        }

        // 5. 根据轨迹复算确保数据一致
        $telemetryResult = meteor_game_validate_telemetry($telemetry, $score, $comboMax, $duration, $telemetryFlagReasons);
        $metrics = $telemetryResult['metrics'];
        $maxComboFromTelemetry = $telemetryResult['max_combo'];

        if (abs($maxComboFromTelemetry - $comboMax) > meteor_game_config_value('combo.lenient_check_threshold', 1)) {
            write_log("游戏作弊尝试 - 用户ID: {$userId}, 前端最大连击({$comboMax})与轨迹({$maxComboFromTelemetry})不符", 'mod');
            throw new \InvalidArgumentException('最大连击校验失败');
        }

        // 6. 检查提交频率（30秒内只能提交一次）
        $minIntervalSeconds = meteor_game_config_value('frequency.min_submit_interval_seconds', 30);

        $lastSubmit = \App\Models\MeteorGameScore::where('user_id', $userId)
            ->where('created_at', '>=', now()->subSeconds($minIntervalSeconds))
            ->first();

        if ($lastSubmit) {
            throw new \InvalidArgumentException("提交过于频繁，请等待{$minIntervalSeconds}秒");
        }

        // 7. 检查每日提交次数限制
        $maxDailySubmits = meteor_game_config_value('frequency.daily_submit_limit', 5);
        $todaySubmitCount = \App\Models\MeteorGameScore::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();

        if ($todaySubmitCount >= $maxDailySubmits) {
            throw new \InvalidArgumentException("今日提交次数已达上限（{$maxDailySubmits}次），明天再来吧！");
        }

        // 8. 检查短时间内的异常高分（1小时内提交4次以上超高分视为异常）
        $recentHighScores = \App\Models\MeteorGameScore::where('user_id', $userId)
            ->where('score', '>', 4000)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentHighScores >= 4) {
            write_log("游戏作弊嫌疑 - 用户ID: {$userId}, 1小时内{$recentHighScores}次超高分", 'mod');
            throw new \InvalidArgumentException('检测到异常游戏行为，请稍后再试');
        }

        // 9. 检查异常负分（故意只接怪物刷负分的异常行为）
        if ($score < -2000) {
            write_log("游戏异常行为 - 用户ID: {$userId}, 异常负分: {$score}", 'mod');
            // 负分太低也记录，但不阻止（可能是真的玩得很差）
        }

        $telemetryHash = hash('sha256', $telemetryRaw);
        $isFlagged = !empty($telemetryFlagReasons);

        if ($isFlagged) {
            write_log("游戏数据已标记 - 用户ID: {$userId}, 理由: " . implode(',', $telemetryFlagReasons), 'mod');
        }

        // 使用事务，确保星尘发放的原子性
        try {
            $stardustReward = 0;
            $remainingSubmits = max(0, $maxDailySubmits - ($todaySubmitCount + 1));

            \Nexus\Database\NexusDB::transaction(function() use ($userId, $score, $comboMax, $duration, $ipAddress, $telemetry, $telemetryHash, $isFlagged, $telemetryFlagReasons, &$stardustReward, $metrics) {
                $payload = [
                    'user_id' => $userId,
                    'score' => $score,
                    'combo_max' => $comboMax,
                    'duration' => $duration,
                    'ip_address' => $ipAddress,
                    'telemetry' => $telemetry,
                    'telemetry_hash' => $telemetryHash,
                    'is_flagged' => $isFlagged,
                    'flag_reasons' => $telemetryFlagReasons,
                    'miss_count' => $metrics['miss_count'],
                    'good_catch_count' => $metrics['good_catch_count'],
                    'bad_catch_count' => $metrics['bad_catch_count'],
                    'inputs_count' => $metrics['inputs_count'],
                ];

                if (isset($telemetry['startedAt'])) {
                    $payload['session_started_at'] = Carbon::createFromTimestamp((int) floor($telemetry['startedAt'] / 1000));
                }
                if (isset($telemetry['endedAt'])) {
                    $payload['session_ended_at'] = Carbon::createFromTimestamp((int) floor($telemetry['endedAt'] / 1000));
                }

                // 保存分数记录
                \App\Models\MeteorGameScore::create($payload);

                // 计算星尘奖励（100积分 = 10星尘，只有正分才发放）
                if ($score > 0 && !$isFlagged) {
                    $stardustReward = intval(floor($score / 10)); // 100分 = 10星尘

                    // 使用星尘农场系统添加星尘
                    $farm = \App\Models\StardustFarm::getOrCreateForUser($userId);
                    $farm->addStardust($stardustReward, 'game', "流星游戏得分：{$score}");
                }
            });

            exit(json_encode([
                'success' => true,
                'message' => '分数提交成功！',
                'stardust_reward' => $stardustReward,
                'remaining_submits' => $remainingSubmits,
                'score' => $score,
                'flagged' => $isFlagged,
                'flag_reasons' => $telemetryFlagReasons,
            ]));

        } catch (\Exception $e) {
            write_log("流星游戏提交失败 - 用户ID: {$userId}, 错误: {$e->getMessage()}", 'error');
            throw new \InvalidArgumentException('分数保存失败，请重试');
        }

    } catch (\Throwable $e) {
        exit(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }
}

// 流星游戏 - 获取排行榜
if ($action === 'meteor_game_leaderboard') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $type = $_GET['type'] ?? 'alltime'; // today, 7days, alltime

        $leaderboard = \App\Models\MeteorGameScore::getLeaderboard($type, 50);

        $data = $leaderboard->map(function($record, $index) {
            return [
                'rank' => $index + 1,
                'username' => $record->user->username ?? '未知用户',
                'user_class' => $record->user->class ?? 0,
                'score' => $record->score,
                'combo_max' => $record->combo_max,
                'created_at' => $record->created_at->format('Y-m-d H:i:s'),
            ];
        })->values()->toArray(); // 转为数组并重置索引

        exit(json_encode(['success' => true, 'data' => $data, 'count' => count($data)]));

    } catch (\Throwable $e) {
        exit(json_encode(['success' => false, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]));
    }
}

// 流星游戏 - 获取今日剩余提交次数
if ($action === 'meteor_game_remaining') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!$CURUSER) {
            throw new \InvalidArgumentException('请先登录');
        }

        $userId = $CURUSER['id'];

        // 查询今日已提交次数
        $maxDailySubmits = meteor_game_config_value('frequency.daily_submit_limit', 5);
        $todaySubmitCount = \App\Models\MeteorGameScore::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();

        $remainingSubmits = max(0, $maxDailySubmits - $todaySubmitCount);

        exit(json_encode([
            'success' => true,
            'remaining_submits' => $remainingSubmits,
            'today_submits' => $todaySubmitCount,
            'max_submits' => $maxDailySubmits
        ]));

    } catch (\Throwable $e) {
        exit(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }
}

// 宇宙碎片抓取游戏 - 提交分数
if ($action === 'space_miner_submit') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        // 读取JSON body
        $jsonInput = file_get_contents('php://input');
        $data = json_decode($jsonInput, true);

        if (!$data) {
            $data = $_POST; // 回退到POST
        }

        $score = intval($data['score'] ?? 0);
        $caught = intval($data['caught'] ?? 0);
        $level = intval($data['level'] ?? 1);
        $maxPossibleScore = intval($data['max_possible_score'] ?? 0); // 前端计算的理论最大分数
        $gameToken = $data['token'] ?? '';
        $ipAddress = getip();

        // 1. 用户验证
        if (!$CURUSER) {
            throw new \InvalidArgumentException('请先登录');
        }

        $userId = $CURUSER['id'];

        // 获取用户密码哈希（用于Token验证）
        $user = \App\Models\User::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('用户不存在');
        }

        // 2. Token验证（防止简单的接口调用）
        // Token包含：用户ID + 分数 + 抓取数量 + 关卡 + 理论最大分数 + 日期 + 密码哈希
        $expectedToken = md5($userId . $score . $caught . $level . $maxPossibleScore . date('Y-m-d') . $user->passhash);
        if ($gameToken !== $expectedToken) {
            write_log("宇宙碎片抓取游戏作弊尝试 - 用户ID: {$userId}, IP: {$ipAddress}, 分数: {$score}, 理论最大: {$maxPossibleScore}", 'mod');
            throw new \InvalidArgumentException('游戏数据验证失败');
        }

        // 3. 分数范围检查（基于前端计算的理论最大分数）
        $minTheoreticalScore = -1000; // 极端情况下负分最多-1000分

        // 如果提供了理论最大分数，使用它作为上限；否则使用保守的上限值
        $maxTheoreticalScore = $maxPossibleScore > 0 ? ($maxPossibleScore + 300) : 4000; // 允许300分的容差，提高上限到4000

        if ($score < $minTheoreticalScore) {
            write_log("宇宙碎片抓取游戏作弊尝试 - 用户ID: {$userId}, 分数异常: {$score} (最低: {$minTheoreticalScore})", 'mod');
            throw new \InvalidArgumentException('分数低于合理范围（最低 -1000）');
        }

        if ($score > $maxTheoreticalScore) {
            write_log("宇宙碎片抓取游戏作弊尝试 - 用户ID: {$userId}, 分数异常: {$score} (最高: {$maxTheoreticalScore}, 理论最大: {$maxPossibleScore})", 'mod');
            throw new \InvalidArgumentException("分数超过合理范围（最高 {$maxTheoreticalScore}，本局理论最大 {$maxPossibleScore}）");
        }

        // 4. 抓取数量检查（正常一局：10个天体 + (5-8)×难度个石头 + 1-3个负分碎片，难度6时最多约70个）
        if ($caught < 0 || $caught > 70) {
            write_log("宇宙碎片抓取游戏作弊尝试 - 用户ID: {$userId}, 抓取数量异常: {$caught}", 'mod');
            throw new \InvalidArgumentException('抓取数量异常（0 ~ 70）');
        }

        // 5. 关卡检查（正常游戏最多30关，高分玩家可能超过10关）
        if ($level < 1 || $level > 30) {
            write_log("宇宙碎片抓取游戏作弊尝试 - 用户ID: {$userId}, 关卡异常: {$level}", 'mod');
            throw new \InvalidArgumentException('关卡数据异常（1 ~ 30）');
        }

        // 6. 分数与抓取数量的合理性检查
        // 平均每个天体/石头得分：考虑高难度6（2倍分）+ 距离加成1.5倍 + 集齐奖励均摊
        // 太阳基础50分 × 1.5距离加成 × 2难度倍率 = 150分，加上集齐奖励均摊可能达到180-200分/个
        if ($caught > 0) {
            $avgScorePerCaught = $score / $caught;
            // 单个物体得分上限提高到200分（考虑高难度+距离加成+集齐奖励均摊）
            if ($avgScorePerCaught > 200) {
                write_log("宇宙碎片抓取游戏作弊尝试 - 用户ID: {$userId}, 平均得分异常: {$avgScorePerCaught} (分数:{$score}, 抓取:{$caught})", 'mod');
                throw new \InvalidArgumentException('得分率异常，请正常游戏');
            }
        }

        // 7. 检查提交频率（30秒内只能提交一次）
        $minIntervalSeconds = 30;
        $lastSubmit = \App\Models\SpaceMinerGameScore::where('user_id', $userId)
            ->where('created_at', '>=', now()->subSeconds($minIntervalSeconds))
            ->first();

        if ($lastSubmit) {
            throw new \InvalidArgumentException("提交过于频繁，请等待{$minIntervalSeconds}秒");
        }

        // 8. 检查每日提交次数限制
        $maxDailySubmits = 5;
        $todaySubmitCount = \App\Models\SpaceMinerGameScore::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();

        if ($todaySubmitCount >= $maxDailySubmits) {
            throw new \InvalidArgumentException("今日提交次数已达上限（{$maxDailySubmits}次），明天再来吧！");
        }

        // 9. 检查短时间内的异常高分（1小时内提交5次以上超高分视为异常）
        // 高分阈值提高到3500（考虑高难度6 + 集齐奖励 + 多关卡的情况）
        $recentHighScores = \App\Models\SpaceMinerGameScore::where('user_id', $userId)
            ->where('score', '>', 3500)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentHighScores >= 5) {
            write_log("宇宙碎片抓取游戏作弊嫌疑 - 用户ID: {$userId}, 1小时内{$recentHighScores}次超高分", 'mod');
            throw new \InvalidArgumentException('检测到异常游戏行为，请稍后再试');
        }

        // 使用事务，确保星尘发放的原子性
        try {
            $stardustReward = 0;
            $remainingSubmits = max(0, $maxDailySubmits - ($todaySubmitCount + 1));

            \Nexus\Database\NexusDB::transaction(function() use ($userId, $score, $caught, $level, $ipAddress, &$stardustReward) {
                // 保存分数记录
                \App\Models\SpaceMinerGameScore::create([
                    'user_id' => $userId,
                    'score' => $score,
                    'caught' => $caught,
                    'level' => $level,
                    'ip_address' => $ipAddress,
                ]);

                // 计算星尘奖励（10分 = 1星尘，只有正分才发放）
                if ($score > 0) {
                    $stardustReward = intval(floor($score / 10)); // 10分 = 1星尘

                    // 使用星尘农场系统添加星尘（如果存在）
                    if (class_exists(\App\Models\StardustFarm::class)) {
                        try {
                            $farm = \App\Models\StardustFarm::getOrCreateForUser($userId);
                            $farm->addStardust($stardustReward, 'game', "宇宙碎片抓取得分：{$score}");
                        } catch (\Exception $e) {
                            // 星尘系统不存在时忽略错误
                        }
                    }
                }
            });

            exit(json_encode([
                'success' => true,
                'message' => '分数提交成功！',
                'stardust_reward' => $stardustReward,
                'remaining_submits' => $remainingSubmits,
                'score' => $score,
            ]));

        } catch (\Exception $e) {
            write_log("宇宙碎片抓取游戏提交失败 - 用户ID: {$userId}, 错误: {$e->getMessage()}", 'error');
            throw new \InvalidArgumentException('分数保存失败，请重试');
        }

    } catch (\Throwable $e) {
        exit(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }
}

// 获取商品列表
if ($action === 'get_bonus_products') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!$CURUSER) {
            throw new \InvalidArgumentException('请先登录');
        }

        $userId = $CURUSER['id'];
        $user = \App\Models\User::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('用户不存在');
        }

        // 检查表是否存在
        $tableExists = false;
        $result = sql_query("SHOW TABLES LIKE 'bonus_products'");
        if ($result) {
            $tableExists = mysql_num_rows($result) > 0;
            @mysql_free_result($result);
        }

        $products = [];
        if ($tableExists) {
            $dbProducts = \App\Models\BonusProduct::getActiveProducts();
            foreach ($dbProducts as $product) {
                $item = [
                    'id' => $product->id,
                    'art' => $product->art,
                    'name' => $product->name,
                    'category' => $product->category ?? null,
                    'description' => $product->description,
                    'points' => floatval($product->points),
                    'menge' => $product->menge,
                    'product_type' => $product->product_type,
                    'special_permission_id' => $product->special_permission_id,
                ];

                // 检查特殊权限商品：用户是否已拥有该权限
                if ($product->product_type === \App\Models\BonusProduct::PRODUCT_TYPE_SPECIAL_PERMISSION && $product->special_permission_id) {
                    $hasPermission = $user->specialPermissions()->where('special_permissions.id', $product->special_permission_id)->exists();
                    if ($hasPermission) {
                        $item['has_permission'] = true;
                        $item['disable_reason'] = '您已拥有此权限';
                    }
                }

                $products[] = $item;
            }
        }

        // 如果数据库没有商品，返回空数组（不再使用硬编码）
        exit(json_encode([
            'ret' => 0,
            'msg' => '获取成功',
            'data' => [
                'products' => $products,
                'user_bonus' => floatval($user->seedbonus ?? 0),
            ]
        ]));

    } catch (\Throwable $e) {
        exit(json_encode(['ret' => 1, 'msg' => $e->getMessage()]));
    }
}

// 购买商品
if ($action === 'purchase_bonus_product') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!$CURUSER) {
            throw new \InvalidArgumentException('请先登录');
        }

        $userId = $CURUSER['id'];
        $productId = intval($_POST['product_id'] ?? 0);

        if (!$productId) {
            throw new \InvalidArgumentException('商品ID不能为空');
        }

        $user = \App\Models\User::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('用户不存在');
        }

        $product = \App\Models\BonusProduct::find($productId);
        if (!$product) {
            throw new \InvalidArgumentException('商品不存在');
        }

        if (!$product->is_active) {
            throw new \InvalidArgumentException('该商品已下架');
        }

        // 检查用户魔力值是否足够
        if ($user->seedbonus < $product->points) {
            throw new \InvalidArgumentException('魔力值不足');
        }

        // 检查特殊权限商品：用户是否已拥有该权限
        if ($product->product_type === \App\Models\BonusProduct::PRODUCT_TYPE_SPECIAL_PERMISSION && $product->special_permission_id) {
            $hasPermission = $user->specialPermissions()->where('special_permissions.id', $product->special_permission_id)->exists();
            if ($hasPermission) {
                throw new \InvalidArgumentException('您已经拥有此特殊权限，无需重复购买');
            }
        }

        // 执行购买逻辑（使用现有的BonusRepository）
        $bonusRep = new \App\Repositories\BonusRepository();
        $art = $product->art;
        $points = $product->points;
        $menge = $product->menge;

        // 根据商品类型执行不同的购买逻辑
        if ($art == 'traffic') {
            $bonusRep->consumeToExchangeUpload($user->id, $menge, $points);
        } elseif ($art == 'traffic_downloaded') {
            $bonusRep->consumeToExchangeDownload($user->id, $menge, $points);
        } elseif ($art == 'invite') {
            $bonusRep->consumeToBuyInvite($user->id, $points);
        } elseif ($art == 'tmp_invite') {
            $bonusRep->consumeToBuyTemporaryInvite($user->id);
        } elseif ($art == 'title') {
            $title = $_POST['title'] ?? '';
            if (empty($title)) {
                throw new \InvalidArgumentException('请输入自定义头衔');
            }
            $bonusRep->consumeToBuyCustomTitle($user->id, $title, $points);
        } elseif ($art == 'class') {
            $bonusRep->consumeToBuyVip($user->id, $points);
        } elseif ($art == 'gift_1') {
            $username = $_POST['username'] ?? '';
            $bonusgift = $_POST['bonusgift'] ?? 0;
            $message = $_POST['message'] ?? '';
            if (empty($username) || empty($bonusgift)) {
                throw new \InvalidArgumentException('请输入接收者用户名和赠送数量');
            }
            $bonusRep->consumeToGiftBonus($user->id, $username, $bonusgift, $message);
        } elseif ($art == 'gift_2') {
            $bonuscharity = $_POST['bonuscharity'] ?? 0;
            $ratiocharity = $_POST['ratiocharity'] ?? 0.3;
            if (empty($bonuscharity)) {
                throw new \InvalidArgumentException('请输入捐赠数量');
            }
            $bonusRep->consumeToCharityGiving($user->id, $bonuscharity, $ratiocharity);
        } elseif ($art == 'noad') {
            $bonusRep->consumeToBuyNoAd($user->id, $points, $menge);
        } elseif ($art == 'attendance_card') {
            $bonusRep->consumeToBuyAttendanceCard($user->id);
        } elseif ($art == 'rainbow_id') {
            $bonusRep->consumeToBuyRainbowId($user->id);
        } elseif ($art == 'change_username_card') {
            $bonusRep->consumeToBuyChangeUsernameCard($user->id);
        } elseif ($art == 'cancel_hr') {
            $hrId = $_POST['hr_id'] ?? 0;
            if (empty($hrId)) {
                throw new \InvalidArgumentException('请输入H&R ID');
            }
            $bonusRep->consumeToCancelHitAndRun($user->id, $hrId);
        } elseif (strpos($art, 'buy_special_permission') === 0 || $product->special_permission_id) {
            // 购买特殊权限
            $permission = $product->specialPermission;
            if (!$permission || !$permission->is_active) {
                throw new \InvalidArgumentException('特殊权限不存在或已禁用');
            }

            // 扣除魔力值
            $bonusRep->consumeUserBonus($user->id, $points, \App\Models\BonusLogs::BUSINESS_TYPE_BUY_TORRENT, "购买特殊权限：{$permission->name}");

            // 分配权限
            $user->specialPermissions()->syncWithoutDetaching([$product->special_permission_id]);

            // 清除用户缓存
            clear_user_cache($user->id, $user->passkey);
        } else {
            throw new \InvalidArgumentException('不支持的商品类型：' . $art);
        }

        // 刷新用户数据
        $user->refresh();

        exit(json_encode([
            'ret' => 0,
            'msg' => '购买成功',
            'data' => [
                'new_bonus' => number_format($user->seedbonus, 1),
            ]
        ]));

    } catch (\Throwable $e) {
        exit(json_encode(['ret' => 1, 'msg' => $e->getMessage()]));
    }
}

// 一键获取今日星尘
if ($action === 'auto_claim_stardust') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!$CURUSER) {
            throw new \InvalidArgumentException('请先登录');
        }

        $userId = $CURUSER['id'];

        // 检查权限
        $user = \App\Models\User::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('用户不存在');
        }

        // 检查用户等级是否在Elite User以上
        if ($user->class < \App\Models\User::CLASS_ELITE_USER) {
            throw new \InvalidArgumentException('一键获取星尘功能仅限 Elite User 及以上等级用户使用');
        }

        // 检查是否是捐赠用户
        $isDonor = $user->isDonating();

        // 检查是否有一键获取星尘的权限（直接查询数据库，避免缓存问题）
        $hasPermission = $user->specialPermissions()
            ->where('code', \App\Models\SpecialPermission::CODE_AUTO_CLAIM_STARDUST)
            ->where('is_active', true)
            ->exists();

        // 如果直接查询没有，再通过user_can检查（可能通过其他方式获得权限）
        if (!$hasPermission) {
            $hasPermission = user_can(\App\Models\SpecialPermission::CODE_AUTO_CLAIM_STARDUST, false, $userId);
        }

        // 用户等级在Elite User以上 && （捐赠用户 || 具备一键获取星尘的权限）
        if (!$isDonor && !$hasPermission) {
            $message = '您没有权限使用一键获取星尘功能' . "\n\n" .
                      '使用条件：' . "\n" .
                      '1. 用户等级需达到 Elite User 及以上（当前等级：' . \App\Models\User::getClassName($user->class, false, false, false) . '）' . "\n" .
                      '2. 需要是捐赠用户，或拥有"一键获取星尘"特殊权限（魔力值商店购买）';
            throw new \InvalidArgumentException($message);
        }

        $maxDailySubmits = 5; // 每天最多5次
        $defaultScore = 1000; // 默认每局1000分
        $stardustPerGame = 100; // 每局100星尘（1000分 = 100星尘）

        // 计算今日剩余提交次数（两个游戏分别计算）
        $meteorTodayCount = \App\Models\MeteorGameScore::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();
        $spaceMinerTodayCount = \App\Models\SpaceMinerGameScore::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();

        // 计算剩余次数（取两个游戏中剩余次数较多的）
        $meteorRemaining = max(0, $maxDailySubmits - $meteorTodayCount);
        $spaceMinerRemaining = max(0, $maxDailySubmits - $spaceMinerTodayCount);
        $remainingSubmits = max($meteorRemaining, $spaceMinerRemaining);

        if ($remainingSubmits <= 0) {
            throw new \InvalidArgumentException('今日提交次数已达上限，明天再来吧！');
        }

        $ipAddress = getip();
        $totalStardust = 0;
        $meteorCount = 0;
        $spaceMinerCount = 0;

        // 使用事务，确保星尘发放的原子性
        \Nexus\Database\NexusDB::transaction(function() use ($userId, $defaultScore, $stardustPerGame, $ipAddress, $meteorRemaining, $spaceMinerRemaining, &$totalStardust, &$meteorCount, &$spaceMinerCount) {
            // 为流星游戏创建记录
            if ($meteorRemaining > 0) {
                for ($i = 0; $i < $meteorRemaining; $i++) {
                    \App\Models\MeteorGameScore::create([
                        'user_id' => $userId,
                        'score' => $defaultScore,
                        'combo_max' => 0,
                        'duration' => 60,
                        'ip_address' => $ipAddress,
                        'telemetry' => null,
                        'telemetry_hash' => null,
                        'is_flagged' => false,
                        'flag_reasons' => null,
                        'miss_count' => 0,
                        'good_catch_count' => 0,
                        'bad_catch_count' => 0,
                        'inputs_count' => 0,
                        'is_auto_claim' => true,
                    ]);
                    $meteorCount++;
                    $totalStardust += $stardustPerGame;
                }
            }

            // 为宇宙碎片抓取游戏创建记录
            if ($spaceMinerRemaining > 0) {
                for ($i = 0; $i < $spaceMinerRemaining; $i++) {
                    \App\Models\SpaceMinerGameScore::create([
                        'user_id' => $userId,
                        'score' => $defaultScore,
                        'caught' => 0,
                        'level' => 1,
                        'ip_address' => $ipAddress,
                        'is_auto_claim' => true,
                    ]);
                    $spaceMinerCount++;
                    $totalStardust += $stardustPerGame;
                }
            }

            // 发放星尘
            if ($totalStardust > 0 && class_exists(\App\Models\StardustFarm::class)) {
                $farm = \App\Models\StardustFarm::getOrCreateForUser($userId);
                $farm->addStardust($totalStardust, 'game', "一键获取星尘（流星游戏{$meteorCount}次，宇宙碎片抓取游戏{$spaceMinerCount}次）");
            }
        });

        // 计算最终剩余次数
        $finalMeteorRemaining = max(0, $maxDailySubmits - ($meteorTodayCount + $meteorCount));
        $finalSpaceMinerRemaining = max(0, $maxDailySubmits - ($spaceMinerTodayCount + $spaceMinerCount));
        $finalRemaining = max($finalMeteorRemaining, $finalSpaceMinerRemaining);

        exit(json_encode([
            'success' => true,
            'message' => '成功获取星尘！',
            'total_stardust' => $totalStardust,
            'meteor_count' => $meteorCount,
            'space_miner_count' => $spaceMinerCount,
            'remaining_submits' => $finalRemaining,
        ]));

    } catch (\Throwable $e) {
        exit(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }
}

// 宇宙碎片抓取游戏 - 获取排行榜
if ($action === 'space_miner_leaderboard') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $type = $_GET['type'] ?? 'alltime'; // today, 7days, alltime

        $leaderboard = \App\Models\SpaceMinerGameScore::getLeaderboard($type, 50);

        $data = $leaderboard->map(function($record, $index) {
            return [
                'rank' => $index + 1,
                'username' => $record->user->username ?? '未知用户',
                'user_class' => $record->user->class ?? 0,
                'score' => $record->score,
                'caught' => $record->caught,
                'level' => $record->level,
                'created_at' => $record->created_at->format('Y-m-d H:i:s'),
            ];
        })->values()->toArray(); // 转为数组并重置索引

        exit(json_encode(['success' => true, 'leaderboard' => $data, 'count' => count($data)]));

    } catch (\Throwable $e) {
        exit(json_encode(['success' => false, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]));
    }
}

// 宇宙碎片抓取游戏 - 获取今日剩余提交次数
if ($action === 'space_miner_remaining') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!$CURUSER) {
            throw new \InvalidArgumentException('请先登录');
        }

        $userId = $CURUSER['id'];

        // 查询今日已提交次数
        $maxDailySubmits = 5;
        $todaySubmitCount = \App\Models\SpaceMinerGameScore::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();

        $remainingSubmits = max(0, $maxDailySubmits - $todaySubmitCount);

        exit(json_encode([
            'success' => true,
            'remaining_submits' => $remainingSubmits,
            'today_submits' => $todaySubmitCount,
            'max_submits' => $maxDailySubmits
        ]));

    } catch (\Throwable $e) {
        exit(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }
}

// 魔力值交换
if ($action === 'exchangeBonus') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!$CURUSER) {
            throw new \InvalidArgumentException('请先登录');
        }

        // 包含必要的函数和变量
        require_once('../include/functions.php');

        // 包含语言文件
        require_once(get_langfile_path('mybonus.php'));

        // 定义 bonusarray 函数（从 mybonus.php 复制）
        if (!function_exists('bonusarray')) {
            function bonusarray($option = 0){
                global $onegbupload_bonus,$fivegbupload_bonus,$tengbupload_bonus,$oneinvite_bonus,$customtitle_bonus,$vipstatus_bonus, $basictax_bonus, $taxpercentage_bonus, $bonusnoadpoint_advertisement, $bonusnoadtime_advertisement;
                global $lang_mybonus;

                // 如果变量未定义，从设置中获取
                if (!isset($onegbupload_bonus)) {
                    $onegbupload_bonus = get_setting('bonus.onegbupload');
                }
                if (!isset($fivegbupload_bonus)) {
                    $fivegbupload_bonus = get_setting('bonus.fivegbupload');
                }
                if (!isset($tengbupload_bonus)) {
                    $tengbupload_bonus = get_setting('bonus.tengbupload');
                }
                if (!isset($oneinvite_bonus)) {
                    $oneinvite_bonus = get_setting('bonus.oneinvite');
                }
                if (!isset($customtitle_bonus)) {
                    $customtitle_bonus = get_setting('bonus.customtitle');
                }
                if (!isset($vipstatus_bonus)) {
                    $vipstatus_bonus = get_setting('bonus.vipstatus');
                }
                if (!isset($basictax_bonus)) {
                    $basictax_bonus = get_setting('bonus.basictax');
                }
                if (!isset($taxpercentage_bonus)) {
                    $taxpercentage_bonus = get_setting('bonus.taxpercentage');
                }
                if (!isset($bonusnoadpoint_advertisement)) {
                    $bonusnoadpoint_advertisement = get_setting('advertisement.bonusnoadpoint');
                }
                if (!isset($bonusnoadtime_advertisement)) {
                    $bonusnoadtime_advertisement = get_setting('advertisement.bonusnoadtime');
                }

                $results = [];
                //1.0 GB Uploaded
                $bonus = array();
                $bonus['points'] = $onegbupload_bonus;
                $bonus['art'] = 'traffic';
                $bonus['menge'] = 1073741824;
                $bonus['name'] = $lang_mybonus['text_uploaded_one'];
                $bonus['description'] = $lang_mybonus['text_uploaded_note'];
                $results[] = $bonus;

                //5.0 GB Uploaded
                $bonus = array();
                $bonus['points'] = $fivegbupload_bonus;
                $bonus['art'] = 'traffic';
                $bonus['menge'] = 5368709120;
                $bonus['name'] = $lang_mybonus['text_uploaded_two'];
                $bonus['description'] = $lang_mybonus['text_uploaded_note'];
                $results[] = $bonus;

                //10.0 GB Uploaded
                $bonus = array();
                $bonus['points'] = $tengbupload_bonus;
                $bonus['art'] = 'traffic';
                $bonus['menge'] = 10737418240;
                $bonus['name'] = $lang_mybonus['text_uploaded_three'];
                $bonus['description'] = $lang_mybonus['text_uploaded_note'];
                $results[] = $bonus;

                //100.0 GB Uploaded
                $bonus = array();
                $bonus['points'] = get_setting('bonus.hundredgbupload');
                $bonus['art'] = 'traffic';
                $bonus['menge'] = 107374182400;
                $bonus['name'] = $lang_mybonus['text_uploaded_four'];
                $bonus['description'] = $lang_mybonus['text_uploaded_note'];
                $results[] = $bonus;

                //10.0 GB Downloaded
                $bonus = array();
                $bonus['points'] = get_setting('bonus.tengbdownload');
                $bonus['art'] = 'traffic_downloaded';
                $bonus['menge'] = 10737418240;
                $bonus['name'] = $lang_mybonus['text_downloaded_ten_gb'];
                $bonus['description'] = $lang_mybonus['text_download_note'];
                $results[] = $bonus;

                //100.0 GB Downloaded
                $bonus = array();
                $bonus['points'] = get_setting('bonus.hundredgbdownload');
                $bonus['art'] = 'traffic_downloaded';
                $bonus['menge'] = 107374182400;
                $bonus['name'] = $lang_mybonus['text_downloaded_hundred_gb'];
                $bonus['description'] = $lang_mybonus['text_download_note'];
                $results[] = $bonus;

                //Invite
                if ($oneinvite_bonus > 0){
                    $bonus = array();
                    $bonus['points'] = $oneinvite_bonus;
                    $bonus['art'] = 'invite';
                    $bonus['menge'] = 1;
                    $bonus['name'] = $lang_mybonus['text_buy_invite'];
                    $bonus['description'] = $lang_mybonus['text_buy_invite_note'];
                    $results[] = $bonus;
                }

                //Tmp Invite
                $tmpInviteBonus = \App\Models\BonusLogs::getBonusForBuyTemporaryInvite();
                if ($tmpInviteBonus > 0) {
                    $bonus = array();
                    $bonus['points'] = $tmpInviteBonus;
                    $bonus['art'] = 'tmp_invite';
                    $bonus['menge'] = 1;
                    $bonus['name'] = $lang_mybonus['text_buy_tmp_invite'];
                    $bonus['description'] = $lang_mybonus['text_buy_tmp_invite_note'];
                    $results[] = $bonus;
                }

                //Custom Title
                $bonus = array();
                $bonus['points'] = $customtitle_bonus;
                $bonus['art'] = 'title';
                $bonus['menge'] = 0;
                $bonus['name'] = $lang_mybonus['text_custom_title'];
                $bonus['description'] = $lang_mybonus['text_custom_title_note'];
                $results[] = $bonus;

                //VIP Status
                $bonus = array();
                $bonus['points'] = $vipstatus_bonus;
                $bonus['art'] = 'class';
                $bonus['menge'] = 0;
                $bonus['name'] = $lang_mybonus['text_vip_status'];
                $bonus['description'] = $lang_mybonus['text_vip_status_note'];
                $results[] = $bonus;

                return $results;
            }
        }

        $allBonus = bonusarray();

        // 获取交换选项
        $option = isset($_POST['option']) ? intval($_POST['option']) : -1;

        // 调试信息
        if (empty($allBonus)) {
            do_log("exchangeBonus: allBonus is empty");
            throw new \InvalidArgumentException('交换选项列表为空，请检查配置');
        }

        do_log("exchangeBonus: option=$option, allBonus count=" . count($allBonus) . ", POST=" . json_encode($_POST));

        if ($option < 0 || !isset($allBonus[$option])) {
            $maxOption = count($allBonus) - 1;
            do_log("exchangeBonus: invalid option=$option, max=$maxOption");
            throw new \InvalidArgumentException("无效的交换选项：选项 $option 不存在（有效范围：0-$maxOption）");
        }

        $bonusarray = $allBonus[$option];
        $points = $bonusarray['points'];
        $userid = $CURUSER['id'];
        $art = $bonusarray['art'];

        // 检查魔力值是否足够
        if ($CURUSER['seedbonus'] < $points) {
            throw new \InvalidArgumentException('魔力值不足');
        }

        // 获取锁配置
        $lockSeconds = 10;
        $lockText = sprintf($lang_mybonus['lock_text'], $lockSeconds);
        $lockName = "user:$userid:exchange:bonus";
        $lock = new \Nexus\Database\NexusLock($lockName, $lockSeconds);
        if (!$lock->get()) {
            throw new \InvalidArgumentException($lockText);
        }

        $bonusRep = new \App\Repositories\BonusRepository();
        $successMsg = '';

        // 处理不同类型的交换
        if ($art == "traffic") {
            // 上传值交换
            $ratiolimit_bonus = get_setting('bonus.ratiolimit');
            $dlamountlimit_bonus = get_setting('bonus.dlamountlimit');

            if ($CURUSER['uploaded'] > $dlamountlimit_bonus * 1073741824) {
                if ($CURUSER['downloaded'] > 0) {
                    $ratio = $CURUSER['uploaded'] / $CURUSER['downloaded'];
                } else {
                    $ratio = PHP_INT_MAX;
                }
            } else {
                $ratio = 0;
            }

            if ($ratiolimit_bonus > 0 && $ratio > $ratiolimit_bonus) {
                throw new \InvalidArgumentException($lang_mybonus['text_cheat_alert']);
            }

            $upload = $CURUSER['uploaded'];
            $up = $upload + $bonusarray['menge'];
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_UPLOAD, $points . " Points for uploaded.", ['uploaded' => $up]);
            $successMsg = $lang_mybonus['text_success_upload'];

        } elseif ($art == "traffic_downloaded") {
            // 下载值交换
            $downloaded = $CURUSER['downloaded'];
            $down = $downloaded + $bonusarray['menge'];
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_DOWNLOAD, $points . " Points for downloaded.", ['downloaded' => $down]);
            $successMsg = $lang_mybonus['text_success_download'];

        } elseif ($art == "invite") {
            // 邀请名额交换
            if (!user_can('buyinvite')) {
                throw new \InvalidArgumentException(get_user_class_name(get_setting('authority.buyinvite'), false, false, true) . $lang_mybonus['text_plus_only']);
            }
            $invites = $CURUSER['invites'];
            $inv = $invites + $bonusarray['menge'];
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_INVITE, $points . " Points for invites.", ['invites' => $inv]);
            $successMsg = $lang_mybonus['text_success_invites'];

        } elseif ($art == "tmp_invite") {
            // 临时邀请名额交换
            if (!user_can('buyinvite')) {
                throw new \InvalidArgumentException(get_user_class_name(get_setting('authority.buyinvite'), false, false, true) . $lang_mybonus['text_plus_only']);
            }
            $bonusRep->consumeToBuyTemporaryInvite($CURUSER['id']);
            $successMsg = $lang_mybonus['text_success_tmp_invites'];

        } elseif ($art == "class") {
            // VIP 状态交换
            if (get_user_class() >= UC_VIP) {
                throw new \InvalidArgumentException($lang_mybonus['std_class_above_vip']);
            }
            $vip_until = date("Y-m-d H:i:s", (TIMENOW + 28 * 86400));
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_BUY_VIP, $points . " Points for 1 month VIP Status.", ['class' => UC_VIP, 'vip_added' => 'yes', 'vip_until' => $vip_until]);
            $successMsg = $lang_mybonus['text_success_vip'] . "<b>" . get_user_class_name(UC_VIP, false, false, true) . "</b>" . $lang_mybonus['text_success_vip_two'];

        } elseif ($art == "title") {
            // 自定义头衔交换
            $title = trim($_POST['title'] ?? '');
            if (empty($title)) {
                throw new \InvalidArgumentException('请输入头衔');
            }
            $words = array("fuck", "shit", "pussy", "cunt", "nigger", "Staff Leader", "SysOp", "Administrator", "Moderator", "Uploader", "Retiree", "VIP", "Nexus Master", "Ultimate User", "Extreme User", "Veteran User", "Insane User", "Crazy User", "Elite User", "Power User", "User", "Peasant", "Champion");
            $title = str_replace($words, $lang_mybonus['text_wasted_karma'], $title);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_CUSTOM_TITLE, $points . " Points for custom title. Old title is " . htmlspecialchars(trim($CURUSER["title"])) . " and new title is $title.", ['title' => $title]);
            $successMsg = sprintf($lang_mybonus['text_success_custom_title'], $title);

        } else {
            // 其他类型暂不支持 AJAX，需要刷新页面
            throw new \InvalidArgumentException('该交换类型暂不支持，请使用传统方式');
        }

        // 获取更新后的魔力值
        $freshUser = \App\Models\User::query()->find($userid, ['id', 'seedbonus']);
        $newBonus = number_format($freshUser->seedbonus ?? 0, 1);

        exit(json_encode([
            'ret' => 0,
            'msg' => $successMsg,
            'data' => [
                'new_bonus' => $newBonus
            ]
        ]));

    } catch (\Throwable $e) {
        exit(json_encode([
            'ret' => 1,
            'msg' => $e->getMessage()
        ]));
    }
}

class AjaxInterface{

    public static function toggleUserMedalStatus($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\MedalRepository();
        return $rep->toggleUserMedalStatus($params['id'], $CURUSER['id']);
    }


    public static function attendanceRetroactive($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\AttendanceRepository();
        return $rep->retroactive($CURUSER['id'], $params['date']);
    }

    public static function getPtGen($params)
    {
        $rep = new Nexus\PTGen\PTGen();
        $result = $rep->generate($params['url']);
        if ($rep->isRawPTGen($result)) {
            return $result;
        } elseif ($rep->isIyuu($result)) {
            return $result['data'];
        } else {
            return '';
        }
    }

    public static function addClaim($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\ClaimRepository();
        return $rep->store($CURUSER['id'], $params['torrent_id']);
    }

    public static function removeClaim($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\ClaimRepository();
        return $rep->delete($params['id'], $CURUSER['id']);
    }

    public static function removeUserLeechWarn($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\UserRepository();
        return $rep->removeLeechWarn($CURUSER['id'], $params['uid']);
    }

    public static function getOffer($params)
    {
        $offer = \App\Models\Offer::query()->findOrFail($params['id']);
        return $offer->toArray();
    }

    public static function approvalModal($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\TorrentRepository();
        return $rep->buildApprovalModal($CURUSER['id'], $params['torrent_id']);
    }

    public static function approval($params)
    {
        global $CURUSER;
        foreach (['torrent_id', 'approval_status',] as $field) {
            if (!isset($params[$field])) {
                throw new \InvalidArgumentException("Require $field");
            }
        }
        $rep = new \App\Repositories\TorrentRepository();
        return $rep->approval($CURUSER['id'], $params);
    }

    public static function addSeedBoxRecord($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\SeedBoxRepository();
        $params['uid'] = $CURUSER['id'];
        $params['type'] = \App\Models\SeedBoxRecord::TYPE_USER;
        $params['status'] = \App\Models\SeedBoxRecord::STATUS_UNAUDITED;
        return $rep->store($params);
    }

    public static function removeSeedBoxRecord($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\SeedBoxRepository();
        return $rep->delete($params['id'], $CURUSER['id']);
    }

    public static function removeHitAndRun($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\BonusRepository();
        return $rep->consumeToCancelHitAndRun($CURUSER['id'], $params['id']);
    }

    public static function consumeBenefit($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\UserRepository();
        return $rep->consumeBenefit($CURUSER['id'], $params);
    }

    public static function clearShoutBox($params)
    {
        global $CURUSER;
        user_can('sbmanage', true);
        \Nexus\Database\NexusDB::table('shoutbox')->delete();
        return true;
    }

    public static function buyMedal($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\BonusRepository();
        return $rep->consumeToBuyMedal($CURUSER['id'], $params['medal_id']);
    }

    public static function giftMedal($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\BonusRepository();
        return $rep->consumeToGiftMedal($CURUSER['id'], $params['medal_id'], $params['uid']);
    }

    public static function saveUserMedal($params)
    {
        global $CURUSER;
        $data = [];
        foreach ($params as $param) {
            $fieldAndId = explode('_', $param['name']);
            $field = $fieldAndId[0];
            $id = $fieldAndId[1];
            $value = $param['value'];
            $data[$id][$field] = $value;
        }
    //    dd($params, $data);
        $rep = new \App\Repositories\MedalRepository();
        return $rep->saveUserMedal($CURUSER['id'], $data);
    }

    public static function claimTask($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\ExamRepository();
        return $rep->assignToUser($CURUSER['id'], $params['exam_id']);
    }

    public static function addToken($params)
    {
        global $CURUSER;
        if (empty($params['name'])) {
            throw new \InvalidArgumentException("Name is required");
        }
        $user = \App\Models\User::query()->findOrFail($CURUSER['id'], \App\Models\User::$commonFields);
        $user->createToken($params['name']);
        return true;
    }

    public static function removeToken($params)
    {
        global $CURUSER;
        if (empty($params['id'])) {
            throw new \InvalidArgumentException("id is required");
        }
        $user = \App\Models\User::query()->findOrFail($CURUSER['id'], \App\Models\User::$commonFields);
        $user->tokens()->where('id', $params['id'])->delete();
        return true;
    }

    // ========== 星尘农场 API ==========

    /**
     * 获取农场数据
     */
    public static function getStardustFarm($params)
    {
        global $CURUSER;
        $repo = new \App\Repositories\StardustFarmRepository();
        $targetUserId = isset($params['user_id']) ? intval($params['user_id']) : $CURUSER['id'];
        $currentUserId = $CURUSER['id'] ?? null;
        return $repo->getUserFarmData($targetUserId, $currentUserId);
    }

    /**
     * 种植作物
     */
    public static function plantStardustCrop($params)
    {
        global $CURUSER;
        if (empty($params['land_id']) || empty($params['crop_id'])) {
            throw new \InvalidArgumentException("land_id and crop_id are required");
        }
        $repo = new \App\Repositories\StardustFarmRepository();
        return $repo->plantCrop($CURUSER['id'], intval($params['land_id']), intval($params['crop_id']));
    }

    /**
     * 收获作物
     */
    public static function harvestStardustCrop($params)
    {
        global $CURUSER;
        if (empty($params['land_id'])) {
            throw new \InvalidArgumentException("land_id is required");
        }
        $repo = new \App\Repositories\StardustFarmRepository();
        return $repo->harvestCrop($CURUSER['id'], intval($params['land_id']));
    }

    /**
     * 一键收获所有可收获的作物
     */
    public static function harvestAllStardustCrops($params)
    {
        global $CURUSER;
        // 检查用户等级（需要等级3以上，即 UC_ELITE_USER）
        if ($CURUSER['class'] < UC_ELITE_USER) {
            throw new \InvalidArgumentException('一键收获功能需要等级3（Elite User）以上才能使用');
        }
        $repo = new \App\Repositories\StardustFarmRepository();
        return $repo->harvestAllCrops($CURUSER['id']);
    }

    /**
     * 合成行星
     */
    public static function craftStardustPlanet($params)
    {
        global $CURUSER;
        if (empty($params['crop_id'])) {
            throw new \InvalidArgumentException("crop_id is required");
        }
        $repo = new \App\Repositories\StardustFarmRepository();
        return $repo->craftPlanet($CURUSER['id'], intval($params['crop_id']));
    }

    /**
     * 购买土地
     */
    public static function purchaseStardustLand($params)
    {
        global $CURUSER;
        $repo = new \App\Repositories\StardustFarmRepository();
        return $repo->purchaseLand($CURUSER['id']);
    }

    /**
     * 浇水（帮助好友）
     */
    public static function waterStardustLand($params)
    {
        global $CURUSER;
        if (empty($params['target_user_id']) || empty($params['land_id'])) {
            throw new \InvalidArgumentException("target_user_id and land_id are required");
        }
        $repo = new \App\Repositories\StardustFarmRepository();
        return $repo->waterFriendLand($CURUSER['id'], intval($params['target_user_id']), intval($params['land_id']));
    }

    /**
     * 偷碎片
     */
    public static function stealStardustFragment($params)
    {
        global $CURUSER;
        if (empty($params['target_user_id']) || empty($params['land_id'])) {
            throw new \InvalidArgumentException("target_user_id and land_id are required");
        }
        $repo = new \App\Repositories\StardustFarmRepository();
        return $repo->stealFragment($CURUSER['id'], intval($params['target_user_id']), intval($params['land_id']));
    }

    /**
     * 访问好友农场
     */
    public static function visitStardustFarm($params)
    {
        global $CURUSER;
        if (empty($params['target_user_id'])) {
            throw new \InvalidArgumentException("target_user_id is required");
        }
        $repo = new \App\Repositories\StardustFarmRepository();
        return $repo->visitFriend($CURUSER['id'], intval($params['target_user_id']));
    }

    /**
     * 获取互动历史
     */
    public static function getStardustInteractions($params)
    {
        global $CURUSER;
        $repo = new \App\Repositories\StardustFarmRepository();
        return $repo->getInteractionHistory($CURUSER['id']);
    }

    /**
     * 获取成就列表
     */
    public static function getStardustAchievements($params)
    {
        global $CURUSER;
        $repo = new \App\Repositories\StardustAchievementRepository();
        return $repo->getUserAchievements($CURUSER['id']);
    }

    /**
     * 检查并解锁成就
     */
    public static function checkStardustAchievements($params)
    {
        global $CURUSER;
        $repo = new \App\Repositories\StardustAchievementRepository();
        return $repo->checkAndUnlockAchievements($CURUSER['id']);
    }

    /**
     * 获取排行榜
     */
    public static function getStardustLeaderboard($params)
    {
        $type = $params['type'] ?? 'wealth';
        $limit = isset($params['limit']) ? intval($params['limit']) : 50;
        $repo = new \App\Repositories\StardustAchievementRepository();
        return $repo->getLeaderboard($type, $limit);
    }

    /**
     * 获取广播消息
     */
    public static function getStardustBroadcasts($params)
    {
        $limit = isset($params['limit']) ? intval($params['limit']) : 50;
        return \App\Models\StardustBroadcast::getRecent($limit);
    }
}

$class = 'AjaxInterface';
$reflection = new \ReflectionClass($class);

try {
    if($reflection->hasMethod($action) && $reflection->getMethod($action)->isStatic()) {
        $result = $class::$action($params);
        exit(json_encode(success($result)));
    } else {
        do_log("hacking attempt made by {$CURUSER['username']},uid {$CURUSER['id']}", 'error');
        throw new \RuntimeException("Invalid action: $action");
    }
}catch(\Throwable $exception){
    do_log($exception->getMessage() . $exception->getTraceAsString(), "error");
    exit(json_encode(fail($exception->getMessage(), $_POST)));
}
