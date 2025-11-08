<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$params = $_POST['params'] ?? [];

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
        
        // 1. 用户验证
        if (!$CURUSER || $CURUSER['id'] != $userId) {
            throw new \InvalidArgumentException('用户验证失败');
        }
        
        // 2. Token验证（防止简单的接口调用）
        $expectedToken = md5($userId . $score . $comboMax . $duration . date('Y-m-d') . $CURUSER['passhash']);
        if ($gameToken !== $expectedToken) {
            // 记录可疑行为
            write_log("游戏作弊尝试 - 用户ID: {$userId}, IP: {$ipAddress}, 分数: {$score}", 'mod');
            throw new \InvalidArgumentException('游戏数据验证失败');
        }
        
        // 3. 分数合理性检查（现在有负分怪物，允许负分但有下限）
        // 最坏情况：全部接到黑洞 = 60秒 * (1000ms/400ms) * (-50) ≈ -7500
        $minTheoreticalScore = -10000; // 留余量
        // 最好情况：全部接太阳+满连击 = 60秒 * (1000ms/400ms) * 50 * 1.1 ≈ 8250
        $maxTheoreticalScore = 10000; // 留余量
        
        if ($score < $minTheoreticalScore || $score > $maxTheoreticalScore) {
            write_log("游戏作弊尝试 - 用户ID: {$userId}, 分数异常: {$score}", 'mod');
            throw new \InvalidArgumentException('分数超出合理范围');
        }
        
        // 4. 时长检查（游戏固定60秒，允许±3秒误差）
        if ($duration < 57 || $duration > 63) {
            write_log("游戏作弊尝试 - 用户ID: {$userId}, 时长异常: {$duration}秒", 'mod');
            throw new \InvalidArgumentException('游戏时长异常');
        }
        
        // 5. 连击数检查（有怪物会清零连击，理论最大连击约50-60）
        if ($comboMax > 80) {
            write_log("游戏作弊尝试 - 用户ID: {$userId}, 连击数过高: {$comboMax}", 'mod');
            throw new \InvalidArgumentException('连击数异常');
        }
        
        // 6. 分数与连击的合理性交叉验证
        // 如果分数很高但连击很低，可能是作弊
        if ($score > 5000 && $comboMax < 20) {
            write_log("游戏作弊尝试 - 用户ID: {$userId}, 高分({$score})但低连击({$comboMax})", 'mod');
            throw new \InvalidArgumentException('游戏数据异常');
        }
        
        // 7. 检查提交频率（30秒内只能提交一次）
        $lastSubmit = \App\Models\MeteorGameScore::where('user_id', $userId)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->first();
            
        if ($lastSubmit) {
            throw new \InvalidArgumentException('提交过于频繁，请等待30秒');
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
        
        // 保存分数
        \App\Models\MeteorGameScore::create([
            'user_id' => $userId,
            'score' => $score,
            'combo_max' => $comboMax,
            'duration' => $duration,
            'ip_address' => $ipAddress,
        ]);
        
        exit(json_encode(['success' => true, 'message' => '分数提交成功！']));
        
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
