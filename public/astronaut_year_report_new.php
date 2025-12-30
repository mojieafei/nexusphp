<?php
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();
parked();

$year = 2025; // 固定为2025年
$targetUserId = isset($_GET['uid']) ? intval($_GET['uid']) : $CURUSER['id'];

// 处理许愿提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_wish') {
    $wish = isset($_POST['wish']) ? trim($_POST['wish']) : '';
    if (mb_strlen($wish) > 0 && mb_strlen($wish) <= 40) {
        try {
            \App\Models\YearWish::create([
                'user_id' => $CURUSER['id'],
                'name' => $CURUSER['username'],
                'wish' => $wish,
            ]);
            echo json_encode(['success' => true, 'message' => '许愿成功！']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => '许愿失败：' . $e->getMessage()]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => '许愿话语长度必须在1-40字之间']);
        exit;
    }
}

// 只能查看自己的报告，除非是管理员
if ($targetUserId != $CURUSER['id'] && get_user_class() < UC_MODERATOR) {
    die("错误：您只能查看自己的年度报告");
}

$user = \App\Models\User::find($targetUserId);
if (!$user) {
    die("错误：用户不存在");
}

// 计算年份的日期范围
$startDate = Carbon\Carbon::create($year, 1, 1, 0, 0, 0);
$endDate = Carbon\Carbon::create($year, 12, 31, 23, 59, 59);

// 1. 基础数据统计
$inviter = $user->inviter;
$invitedCount = \App\Models\User::where('invited_by', $targetUserId)->count();

$userInfo = [
    'username' => $user->username,
    'class' => get_user_class_name($user->class, false, false, true),
    'joined_date' => $user->added ? $user->added->format('Y-m-d') : '未知',
    'last_seen' => $user->last_access ? $user->last_access->format('Y-m-d H:i:s') : '从未',
    'inviter_name' => $inviter ? $inviter->username : '无',
    'invited_count' => $invitedCount,
];

// 2. 流量数据统计
$snatches = \App\Models\Snatch::where('userid', $targetUserId)
    ->whereBetween('completedat', [$startDate, $endDate])
    ->get();

$uploaded = $snatches->sum('uploaded');
$downloaded = $snatches->sum('downloaded');
$shareRatio = $downloaded > 0 ? round($uploaded / $downloaded, 3) : ($uploaded > 0 ? '∞' : 0);

$totalUploaded = $user->uploaded;
$totalDownloaded = $user->downloaded;
$totalShareRatio = $totalDownloaded > 0 ? round($totalUploaded / $totalDownloaded, 3) : ($totalUploaded > 0 ? '∞' : 0);

// 3. 种子数据统计
$torrentsUploaded = \App\Models\Torrent::where('owner', $targetUserId)
    ->whereBetween('added', [$startDate, $endDate])
    ->where('visible', 'yes')
    ->where('banned', 'no')
    ->count();

$torrentsSize = \App\Models\Torrent::where('owner', $targetUserId)
    ->whereBetween('added', [$startDate, $endDate])
    ->where('visible', 'yes')
    ->where('banned', 'no')
    ->sum('size');

$snatchesCount = \App\Models\Snatch::where('userid', $targetUserId)
    ->whereBetween('completedat', [$startDate, $endDate])
    ->where('finished', 'yes')
    ->count();

$seedingCount = \App\Models\Snatch::where('userid', $targetUserId)
    ->whereBetween('completedat', [$startDate, $endDate])
    ->where('finished', 'yes')
    ->where('seedtime', '>', 0)
    ->count();

// 4. 星尘农场数据统计
$farm = \App\Models\StardustFarm::where('user_id', $targetUserId)->first();
$fragmentsCount = \App\Models\StardustInventory::where('user_id', $targetUserId)
    ->where('item_type', 'fragment')
    ->sum('quantity');
$planetsCount = \App\Models\StardustInventory::where('user_id', $targetUserId)
    ->where('item_type', 'planet')
    ->sum('quantity');
$achievementsCount = \Nexus\Database\NexusDB::table('stardust_user_achievements')
    ->where('user_id', $targetUserId)
    ->count();

$farmData = [
    'current_stardust' => $farm ? $farm->stardust : 0,
    'current_level' => $farm ? $farm->level : 1,
    'current_experience' => $farm ? $farm->experience : 0,
    'land_slots' => $farm ? $farm->land_slots : 3,
    'fragments_count' => $fragmentsCount,
    'planets_count' => $planetsCount,
    'achievements_count' => $achievementsCount,
];

$transactions = \App\Models\StardustTransactionLog::where('user_id', $targetUserId)
    ->whereBetween('created_at', [$startDate, $endDate])
    ->get();

$stardustEarned = $transactions->where('type', 'earn')->sum('amount');
$stardustSpent = abs($transactions->where('type', 'spend')->sum('amount'));

$stardustBySource = $transactions->where('type', 'earn')
    ->groupBy('source')
    ->map(function($group) {
        return $group->sum('amount');
    });

// 5. 流星游戏数据统计
$gameScores = \App\Models\MeteorGameScore::where('user_id', $targetUserId)
    ->whereBetween('created_at', [$startDate, $endDate])
    ->where('is_flagged', false)
    ->get();

$gameStats = [
    'total_games' => $gameScores->count(),
    'total_score' => $gameScores->sum('score'),
    'avg_score' => $gameScores->count() > 0 ? round($gameScores->avg('score'), 0) : 0,
    'max_score' => $gameScores->max('score') ?? 0,
    'max_combo' => $gameScores->max('combo_max') ?? 0,
    'stardust_from_game' => $stardustBySource->get('game', 0),
];

// 6. 星尘农场互动数据（修复：使用whereBetween确保时间范围正确）
$interactions = \App\Models\StardustInteraction::where('from_user_id', $targetUserId)
    ->whereBetween('created_at', [$startDate, $endDate])
    ->get();

$interactionStats = [
    'total_visits' => $interactions->where('action', 'visit')->count(),
    'total_water' => $interactions->where('action', 'water')->count(),
    'total_steal' => $interactions->where('action', 'steal')->count(),
];

// 7. 论坛和评论数据（修复：使用whereBetween确保时间范围正确，并且added字段可能为null需要处理）
$forumPosts = \App\Models\Post::where('userid', $targetUserId)
    ->whereBetween('added', [$startDate, $endDate])
    ->count();

// 修复评论统计：使用whereBetween，并且处理added可能为null的情况
$comments = \App\Models\Comment::where('user', $targetUserId)
    ->whereNotNull('added')
    ->whereBetween('added', [$startDate, $endDate])
    ->count();

// 8. 签到数据统计
$attendanceLogs = \App\Models\AttendanceLog::where('uid', $targetUserId)
    ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
    ->orderBy('date', 'asc')
    ->get();

$attendance = $attendanceLogs->count();
$attendanceBonus = $attendanceLogs->sum('points');

$attendanceModel = \App\Models\Attendance::where('uid', $targetUserId)->first();
$currentContinuousDays = $attendanceModel ? $attendanceModel->days : 0;
$totalAttendanceDays = $attendanceModel ? $attendanceModel->total_days : 0;

// 计算最长连续签到天数（修复版）
$maxContinuous = 0;
$currentContinuous = 0;
$lastDate = null;
foreach ($attendanceLogs as $log) {
    $logDate = Carbon\Carbon::parse($log->date);
    if ($lastDate === null) {
        $currentContinuous = 1;
    } elseif ($logDate->diffInDays($lastDate) == 1) {
        $currentContinuous++;
    } else {
        $maxContinuous = max($maxContinuous, $currentContinuous);
        $currentContinuous = 1;
    }
    $lastDate = $logDate;
}
$maxContinuous = max($maxContinuous, $currentContinuous);

// 9. 做种时间统计
$seedTime = \App\Models\Snatch::where('userid', $targetUserId)
    ->whereBetween('completedat', [$startDate, $endDate])
    ->where('finished', 'yes')
    ->sum('seedtime');

$seedTimeDays = round($seedTime / 86400, 1);

// 10. 火星幸运局统计（修复版）
$marsDuelBets = \App\Models\BonusLogs::where('uid', $targetUserId)
    ->where('business_type', \App\Models\BonusLogs::BUSINESS_TYPE_MARS_DUEL_BET)
    ->whereBetween('created_at', [$startDate, $endDate])
    ->get();

$marsDuelWins = \App\Models\BonusLogs::where('uid', $targetUserId)
    ->where('business_type', \App\Models\BonusLogs::BUSINESS_TYPE_MARS_DUEL_WINNER)
    ->whereBetween('created_at', [$startDate, $endDate])
    ->get();

$marsDuelStats = [
    'total_bets' => $marsDuelBets->count(),
    'total_bet_amount' => abs($marsDuelBets->sum('value')), // 使用value字段而不是points
    'total_wins' => $marsDuelWins->count(),
    'total_win_amount' => $marsDuelWins->sum('value'), // 使用value字段
];

// 月度数据（修复：统计该月内完成的所有记录的上传/下载量，而不是只统计completedat在该月的）
$monthlyData = [];
for ($month = 1; $month <= 12; $month++) {
    $monthStart = Carbon\Carbon::create($year, $month, 1, 0, 0, 0);
    $monthEnd = Carbon\Carbon::create($year, $month, 1, 0, 0, 0)->endOfMonth();
    
    // 修复：统计在该月完成的所有记录，但需要计算该月内的增量
    // 由于snatched表记录的是累计值，我们需要统计在该月完成且completedat在该月的记录
    $monthSnatches = \App\Models\Snatch::where('userid', $targetUserId)
        ->where('finished', 'yes')
        ->whereNotNull('completedat')
        ->whereBetween('completedat', [$monthStart, $monthEnd])
        ->get();
    
    // 对于月度统计，我们使用该月完成记录的上传/下载量
    // 注意：snatched表中的uploaded/downloaded是该记录的累计值，不是月度增量
    // 但为了显示月度趋势，我们使用completedat在该月的记录
    $monthGames = \App\Models\MeteorGameScore::where('user_id', $targetUserId)
        ->whereBetween('created_at', [$monthStart, $monthEnd])
        ->where('is_flagged', false)
        ->get();
    
    $monthStardust = \App\Models\StardustTransactionLog::where('user_id', $targetUserId)
        ->where('type', 'earn')
        ->whereBetween('created_at', [$monthStart, $monthEnd])
        ->sum('amount');
    
    $monthlyData[$month] = [
        'uploaded' => $monthSnatches->sum('uploaded'),
        'downloaded' => $monthSnatches->sum('downloaded'),
        'games' => $monthGames->count(),
        'stardust' => $monthStardust,
    ];
}

// 获取2025年荣誉榜单（与topten.php保持一致）
// 1. 上传量第一 - Top 1 上传者（全部时间，与topten.php一致）
$uploadLeaderUser = \Nexus\Database\NexusDB::selectOne(
    "SELECT id as userid, username FROM users WHERE enabled = 'yes' ORDER BY uploaded DESC LIMIT 1"
);
$uploadLeaderUser = $uploadLeaderUser ? \App\Models\User::find($uploadLeaderUser['userid']) : null;

// 2. 下载量第一 - Top 1 下载者（全部时间，与topten.php一致）
$downloadLeaderUser = \Nexus\Database\NexusDB::selectOne(
    "SELECT id as userid, username FROM users WHERE enabled = 'yes' ORDER BY downloaded DESC LIMIT 1"
);
$downloadLeaderUser = $downloadLeaderUser ? \App\Models\User::find($downloadLeaderUser['userid']) : null;

// 3. 捐赠第一 - Top 1 人民币捐赠者（与topten.php一致）
$donateLeader = \Nexus\Database\NexusDB::selectOne(
    "SELECT id, donated, donated_cny from users where donated_cny > 0 ORDER BY donated DESC, donated_cny DESC LIMIT 1"
);
$donateLeader = $donateLeader ? \App\Models\User::find($donateLeader['id']) : null;

// 4. 游戏总榜单第一（流星游戏总得分，2025年）
$gameLeader = \App\Models\MeteorGameScore::selectRaw('user_id, SUM(score) as total_score')
    ->whereBetween('created_at', [$startDate, $endDate])
    ->where('is_flagged', false)
    ->groupBy('user_id')
    ->orderBy('total_score', 'desc')
    ->first();
$gameLeaderUser = $gameLeader ? \App\Models\User::find($gameLeader->user_id) : null;

// 5. 发种第一 - 首页的全部时间的第一（与index.php一致）
$torrentLeader = \Nexus\Database\NexusDB::selectOne(
    "SELECT owner, count(*) as counts FROM torrents WHERE visible = 'yes' AND banned = 'no' GROUP BY owner ORDER BY counts DESC LIMIT 1"
);
$torrentLeaderUser = $torrentLeader ? \App\Models\User::find($torrentLeader['owner']) : null;

// 6. 做种积分第一 - 直接表里取值（按seed_points）
$seedPointsLeader = \App\Models\User::where('enabled', 'yes')->orderBy('seed_points', 'desc')->first();

// 7. 慈善家第一 - Top 1 慈善家（与topten.php一致）
$charityLeader = \Nexus\Database\NexusDB::selectOne(
    "SELECT * FROM users ORDER BY charity DESC LIMIT 1"
);
$charityLeaderUser = $charityLeader ? \App\Models\User::find($charityLeader['id']) : null;

// 检查当前用户是否在榜单上
$honorLeaders = [
    'download' => $downloadLeaderUser,
    'upload' => $uploadLeaderUser,
    'donate' => $donateLeader,
    'game' => $gameLeaderUser,
    'torrent' => $torrentLeaderUser,
    'seed_points' => $seedPointsLeader,
    'charity' => $charityLeaderUser,
];

$userInHonorList = false;
foreach ($honorLeaders as $leader) {
    if ($leader && $leader->id == $targetUserId) {
        $userInHonorList = true;
        break;
    }
}

// 获取所有许愿（用于弹幕）
$allWishes = \App\Models\YearWish::orderBy('created_at', 'desc')->get();
$wishesData = $allWishes->map(function($wish) {
    return [
        'name' => $wish->name,
        'wish' => $wish->wish,
    ];
})->toArray();

// 预定义的20句祝福话语
$defaultWishes = [
    '愿天枢在新的一年里更加精彩！',
    '希望2026年大家都能收获满满！',
    '祝福天枢越来越好，大家越来越开心！',
    '愿新的一年里，天枢的每一位探索者都能实现自己的目标！',
    '希望2026年天枢能带来更多惊喜！',
    '祝福天枢社区越来越活跃，越来越温暖！',
    '愿新的一年里，天枢的每一个功能都能更加完善！',
    '希望2026年天枢能成为更好的平台！',
    '祝福天枢的每一位用户都能在新的一年里收获快乐！',
    '愿天枢在新的一年里继续用心做好每一个细节！',
    '希望2026年天枢能带来更多美好的回忆！',
    '祝福天枢社区越来越和谐，越来越有爱！',
    '愿新的一年里，天枢的每一位探索者都能找到属于自己的星辰！',
    '希望2026年天枢能成为大家最温暖的港湾！',
    '祝福天枢在新的一年里继续发光发热！',
    '愿天枢的每一位用户都能在新的一年里实现自己的梦想！',
    '希望2026年天枢能带来更多精彩的体验！',
    '祝福天枢社区越来越繁荣，越来越有活力！',
    '愿新的一年里，天枢的每一个功能都能更加贴心！',
    '希望2026年天枢能成为大家最信赖的平台！',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人天枢 <?php echo $year; ?> 年度报告</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #0a0e27;
            color: #fff;
            font-family: "Microsoft YaHei", Arial, sans-serif;
        }
        
        .report-container {
            width: 100%;
            height: 100vh;
            position: relative;
            overflow: hidden;
        }
        
        .slider-wrapper {
            width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .slider-container {
            display: flex;
            width: 100%;
            height: 100%;
            transition: transform 0.6s ease;
        }
        
        .slider-page {
            min-width: 100%;
            width: 100%;
            height: 100%;
            flex-shrink: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 50%, #2a2f4a 100%);
        }
        
        .ppt-slide {
            width: 90%;
            max-width: 1200px;
            height: 85%;
            background: rgba(0, 0, 0, 0.6);
            border-radius: 20px;
            border: 2px solid rgba(138, 43, 226, 0.3);
            padding: 50px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            overflow-y: auto;
        }
        
        .ppt-title {
            font-size: 48px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 40px;
            background: linear-gradient(90deg, #00d4ff, #ff6b6b, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .ppt-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 25px;
            border: 1px solid rgba(138, 43, 226, 0.2);
            text-align: center;
        }
        
        .stat-label {
            font-size: 16px;
            color: #aaa;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #00d4ff;
        }
        
        .slider-btn {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(0, 212, 255, 0.3);
            border: 2px solid rgba(0, 212, 255, 0.5);
            color: #00d4ff;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .slider-btn:hover {
            background: rgba(0, 212, 255, 0.5);
            transform: translateY(-50%) scale(1.1);
        }
        
        .slider-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        #prev-btn {
            left: 20px;
        }
        
        #next-btn {
            right: 20px;
        }
        
        .slider-counter {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.5);
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 18px;
            z-index: 1000;
        }
        
        .slider-nav {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .slider-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .slider-dot.active {
            background: #00d4ff;
            transform: scale(1.3);
        }
        
        .highlight-box {
            background: rgba(0, 0, 0, 0.3);
            border-left: 4px solid #4ECDC4;
            padding: 20px;
            margin: 15px 0;
            border-radius: 5px;
        }
        
        .monthly-chart {
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            height: 300px;
            margin-top: 30px;
        }
        
        .month-bar {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
        }
        
        .bar {
            width: 80%;
            min-height: 10px;
            border-radius: 5px 5px 0 0;
            margin-bottom: 5px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding: 5px;
            font-size: 12px;
            color: #fff;
        }
        
        .bar-value {
            font-size: 11px;
            color: #fff;
            margin-top: 5px;
        }
        
        /* 许愿表单样式 */
        .wish-form {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.6) 0%, rgba(26, 31, 58, 0.6) 100%);
            border-radius: 20px;
            padding: 40px;
            margin-top: 30px;
            border: 2px solid rgba(138, 43, 226, 0.4);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }
        
        .wish-form::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(138, 43, 226, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .wish-form > * {
            position: relative;
            z-index: 1;
        }
        
        .wish-title-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .wish-title-section h3 {
            font-size: 32px;
            color: #00d4ff;
            margin-bottom: 10px;
            text-shadow: 0 0 20px rgba(0, 212, 255, 0.5);
        }
        
        .wish-title-section p {
            font-size: 16px;
            color: #aaa;
            line-height: 1.6;
        }
        
        .wish-input-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .wish-input {
            width: 100%;
            padding: 20px;
            background: rgba(0, 0, 0, 0.7);
            border: 2px solid rgba(138, 43, 226, 0.5);
            border-radius: 12px;
            color: #fff;
            font-size: 18px;
            font-family: "Microsoft YaHei", Arial, sans-serif;
            resize: vertical;
            min-height: 100px;
            transition: all 0.3s;
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        .wish-input:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.3), inset 0 2px 10px rgba(0, 0, 0, 0.3);
            background: rgba(0, 0, 0, 0.8);
        }
        
        .wish-input::placeholder {
            color: #666;
        }
        
        .wish-char-count {
            text-align: right;
            font-size: 14px;
            color: #aaa;
            margin-top: 8px;
            padding-right: 5px;
        }
        
        .wish-char-count.warning {
            color: #ff6b6b;
            animation: pulse 1s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        .wish-buttons-section {
            margin-top: 20px;
            text-align: center;
        }
        
        .wish-random-btn {
            padding: 15px 40px;
            background: linear-gradient(135deg, rgba(138, 43, 226, 0.6) 0%, rgba(0, 212, 255, 0.6) 100%);
            border: 2px solid rgba(138, 43, 226, 0.8);
            border-radius: 30px;
            color: #fff;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(138, 43, 226, 0.4);
        }
        
        .wish-random-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .wish-random-btn:hover {
            background: linear-gradient(135deg, rgba(138, 43, 226, 0.8) 0%, rgba(0, 212, 255, 0.8) 100%);
            border-color: rgba(138, 43, 226, 1);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(138, 43, 226, 0.6);
        }
        
        .wish-random-btn:hover::before {
            width: 400px;
            height: 400px;
        }
        
        .wish-random-btn:active {
            transform: translateY(-1px);
        }
        
        .wish-random-btn span {
            position: relative;
            z-index: 1;
        }
        
        .wish-submit-section {
            text-align: center;
            margin-top: 35px;
        }
        
        .wish-submit-btn {
            padding: 18px 50px;
            background: linear-gradient(135deg, rgba(138, 43, 226, 0.6) 0%, rgba(0, 212, 255, 0.6) 100%);
            border: 2px solid rgba(138, 43, 226, 0.8);
            border-radius: 30px;
            color: #fff;
            cursor: pointer;
            font-size: 20px;
            font-weight: bold;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(138, 43, 226, 0.4);
        }
        
        .wish-submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .wish-submit-btn:hover {
            background: linear-gradient(135deg, rgba(138, 43, 226, 0.8) 0%, rgba(0, 212, 255, 0.8) 100%);
            border-color: #00d4ff;
            transform: scale(1.05);
            box-shadow: 0 8px 30px rgba(138, 43, 226, 0.6);
        }
        
        .wish-submit-btn:hover::before {
            left: 100%;
        }
        
        .wish-submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .wish-submit-btn:disabled:hover {
            transform: none;
            box-shadow: 0 5px 20px rgba(138, 43, 226, 0.4);
        }
        
        /* 弹幕样式 */
        .danmaku-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 999;
            overflow: hidden;
        }
        
        .danmaku-item {
            position: absolute;
            white-space: nowrap;
            font-size: 18px;
            color: #fff;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
            animation: danmaku-move linear;
            pointer-events: none;
        }
        
        @keyframes danmaku-move {
            from {
                transform: translateX(100vw);
            }
            to {
                transform: translateX(-100%);
            }
        }
        
        /* 背景音乐播放器样式 */
        .bg-music-player {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .music-toggle-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(0, 212, 255, 0.2);
            border: 2px solid rgba(0, 212, 255, 0.5);
            color: #00d4ff;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.3);
        }
        
        .music-toggle-btn:hover {
            background: rgba(0, 212, 255, 0.3);
            border-color: rgba(0, 212, 255, 0.8);
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 212, 255, 0.5);
        }
        
        .music-toggle-btn.playing {
            background: rgba(255, 215, 0, 0.2);
            border-color: rgba(255, 215, 0, 0.5);
            color: #ffd700;
            animation: music-pulse 2s ease-in-out infinite;
        }
        
        @keyframes music-pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        .music-info {
            background: rgba(0, 0, 0, 0.7);
            color: #00d4ff;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 212, 255, 0.3);
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- 全局弹幕容器 -->
        <div class="danmaku-container" id="danmaku-container"></div>
        
        <!-- 背景音乐播放器 -->
        <div class="bg-music-player" id="bg-music-player">
            <button class="music-toggle-btn" id="music-toggle-btn" title="播放/暂停背景音乐">
                <span class="music-icon">🎵</span>
            </button>
            <div class="music-info" id="music-info">
                <span>背景音乐播放中...</span>
            </div>
        </div>
        <!-- 隐藏的B站播放器 -->
        <iframe id="bilibili-player" 
                src="https://player.bilibili.com/player.html?bvid=BV1adB5BREDH&page=1&autoplay=1&high_quality=1" 
                style="position: fixed; width: 0; height: 0; border: none; opacity: 0; pointer-events: none; z-index: -1;"
                allow="autoplay; encrypted-media"
                scrolling="no">
        </iframe>
        
        <div class="slider-counter">
            <span id="current-page">1</span> / <span id="total-pages">14</span>
        </div>
        
        <button class="slider-btn" id="prev-btn">‹</button>
        <button class="slider-btn" id="next-btn">›</button>
        
        <div class="slider-nav" id="slider-nav"></div>
        
        <div class="slider-wrapper">
            <div class="slider-container" id="slider-container">
                <!-- 第1页：封面 -->
                <div class="slider-page">
                    <div class="ppt-slide" style="justify-content: center; text-align: center;">
                        <div style="font-size: 96px; margin-bottom: 40px;">🚀</div>
                        <h1 class="ppt-title" style="font-size: 72px; margin-bottom: 30px;">
                            个人天枢 <?php echo $year; ?><br>年度报告
                        </h1>
                        <div style="font-size: 32px; color: #00d4ff; margin-bottom: 20px;">
                            探索者：<?php echo htmlspecialchars($userInfo['username']); ?>
                        </div>
                        <div style="font-size: 24px; color: #aaa; margin-bottom: 60px;">
                            <?php echo $userInfo['class']; ?> | 注册于 <?php echo $userInfo['joined_date']; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 第2页：基础信息 -->
                <div class="slider-page">
                    <div class="ppt-slide">
                        <div class="ppt-title">📊 基础信息</div>
                        <div class="ppt-content">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-label">注册日期</div>
                                    <div class="stat-value"><?php echo $userInfo['joined_date']; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">用户等级</div>
                                    <div class="stat-value"><?php echo $userInfo['class']; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">最后访问</div>
                                    <div class="stat-value" style="font-size: 20px;"><?php echo $userInfo['last_seen']; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">邀请人</div>
                                    <div class="stat-value" style="font-size: 24px;"><?php echo htmlspecialchars($userInfo['inviter_name']); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">我的邀请（后宫）</div>
                                    <div class="stat-value"><?php echo number_format($userInfo['invited_count']); ?> 人</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 第3页：流量统计 -->
                <div class="slider-page">
                    <div class="ppt-slide">
                        <div class="ppt-title">📈 <?php echo $year; ?> 年流量统计</div>
                        <div class="ppt-content">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-label">上传量</div>
                                    <div class="stat-value"><?php echo mksize($uploaded); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">下载量</div>
                                    <div class="stat-value"><?php echo mksize($downloaded); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">分享率</div>
                                    <div class="stat-value"><?php echo $shareRatio; ?></div>
                                </div>
                            </div>
                            <div class="highlight-box" style="margin-top: 30px; border-left-color: #00d4ff;">
                                <div style="font-size: 18px; color: #00d4ff; line-height: 1.8;">
                                    💫 总上传：<strong style="font-size: 22px;"><?php echo mksize($totalUploaded); ?></strong> | 
                                    总下载：<strong style="font-size: 22px;"><?php echo mksize($totalDownloaded); ?></strong> | 
                                    总分享率：<strong style="font-size: 22px;"><?php echo $totalShareRatio; ?></strong>
                                    <div style="margin-top: 15px; font-size: 16px; color: #aaa;">
                                        每一字节的流量，都记录着您在天枢的每一次探索与分享
                                    </div>
                                </div>
                            </div>
                            <?php if ($uploaded > 0 || $downloaded > 0): ?>
                            <div class="highlight-box" style="margin-top: 20px; border-left-color: #ff6b6b;">
                                <div style="font-size: 16px; color: #ff6b6b; line-height: 1.8;">
                                    ✨ 这一年，您在天枢的流量世界里留下了深刻的足迹<br>
                                    <span style="color: #aaa; font-size: 14px;">每一次上传，都是对社区的贡献；每一次下载，都是对知识的渴求</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 第4页：种子统计 -->
                <div class="slider-page">
                    <div class="ppt-slide">
                        <div class="ppt-title">🌱 <?php echo $year; ?> 年种子统计</div>
                        <div class="ppt-content">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-label">发布种子</div>
                                    <div class="stat-value"><?php echo number_format($torrentsUploaded); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">种子总大小</div>
                                    <div class="stat-value" style="font-size: 24px;"><?php echo mksize($torrentsSize); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">完成下载</div>
                                    <div class="stat-value"><?php echo number_format($snatchesCount); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">做种数量</div>
                                    <div class="stat-value"><?php echo number_format($seedingCount); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 第5页：星尘农场统计 -->
                <div class="slider-page">
                    <div class="ppt-slide">
                        <div class="ppt-title">⭐ <?php echo $year; ?> 年星尘农场统计</div>
                        <div class="ppt-content">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-label">当前星尘</div>
                                    <div class="stat-value"><?php echo number_format($farmData['current_stardust']); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">农场等级</div>
                                    <div class="stat-value"><?php echo $farmData['current_level']; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">碎片数量</div>
                                    <div class="stat-value"><?php echo number_format($farmData['fragments_count']); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">行星数量</div>
                                    <div class="stat-value"><?php echo number_format($farmData['planets_count']); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">完成成就</div>
                                    <div class="stat-value"><?php echo number_format($farmData['achievements_count']); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">访问农场</div>
                                    <div class="stat-value"><?php echo number_format($interactionStats['total_visits']); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">浇水次数</div>
                                    <div class="stat-value"><?php echo number_format($interactionStats['total_water']); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">偷取次数</div>
                                    <div class="stat-value"><?php echo number_format($interactionStats['total_steal']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 第6页：流星游戏统计（调换顺序） -->
                <div class="slider-page">
                    <div class="ppt-slide">
                        <div class="ppt-title">🎮 <?php echo $year; ?> 年流星游戏统计</div>
                        <div class="ppt-content">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-label">游戏次数</div>
                                    <div class="stat-value"><?php echo number_format($gameStats['total_games']); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">总得分</div>
                                    <div class="stat-value"><?php echo number_format($gameStats['total_score']); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">平均分</div>
                                    <div class="stat-value"><?php echo number_format($gameStats['avg_score']); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">最高分</div>
                                    <div class="stat-value"><?php echo number_format($gameStats['max_score']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 第8页：签到统计 -->
                <div class="slider-page">
                    <div class="ppt-slide">
                        <div class="ppt-title">📅 <?php echo $year; ?> 年签到统计</div>
                        <div class="ppt-content">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-label">签到天数</div>
                                    <div class="stat-value"><?php echo number_format($attendance); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">获得魔力</div>
                                    <div class="stat-value"><?php echo number_format($attendanceBonus); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">最长连续</div>
                                    <div class="stat-value"><?php echo number_format($maxContinuous); ?> 天</div>
                                </div>
                            </div>
                            <div class="highlight-box" style="margin-top: 30px; border-left-color: #ffd700;">
                                <div style="font-size: 20px; color: #ffd700; line-height: 1.8; margin-bottom: 10px;">
                                    ✨ 每一天的坚持，都是对天枢最深情的告白
                                </div>
                                <div style="font-size: 16px; color: #aaa; line-height: 1.8;">
                                    <?php if ($attendance > 0): ?>
                                        您在这一年里签到了 <strong style="color: #ffd700;"><?php echo number_format($attendance); ?></strong> 天，
                                        <?php if ($maxContinuous > 0): ?>
                                            最长连续签到了 <strong style="color: #ffd700;"><?php echo number_format($maxContinuous); ?></strong> 天！
                                        <?php endif; ?>
                                        <br>每一次签到，都是您与天枢的约定；每一份坚持，都值得被铭记
                                    <?php else: ?>
                                        新的一年，让我们一起开始签到之旅吧！
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 第9页：火星幸运局统计 -->
                <div class="slider-page">
                    <div class="ppt-slide">
                        <div class="ppt-title">🎲 <?php echo $year; ?> 年火星幸运局统计</div>
                        <div class="ppt-content">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-label">参与次数</div>
                                    <div class="stat-value"><?php echo number_format($marsDuelStats['total_bets']); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">投注总额</div>
                                    <div class="stat-value"><?php echo number_format($marsDuelStats['total_bet_amount']); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">中奖次数</div>
                                    <div class="stat-value"><?php echo number_format($marsDuelStats['total_wins']); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">中奖总额</div>
                                    <div class="stat-value"><?php echo number_format($marsDuelStats['total_win_amount']); ?></div>
                                </div>
                            </div>
                            <?php if ($marsDuelStats['total_bets'] > 0): ?>
                            <div class="highlight-box" style="margin-top: 30px; border-left-color: #ff6b6b;">
                                <div style="font-size: 16px; color: #ff6b6b; line-height: 1.8;">
                                    <?php 
                                    $winRate = $marsDuelStats['total_bets'] > 0 ? round(($marsDuelStats['total_wins'] / $marsDuelStats['total_bets']) * 100, 1) : 0;
                                    $netProfit = $marsDuelStats['total_win_amount'] - $marsDuelStats['total_bet_amount'];
                                    ?>
                                    <?php if ($winRate > 0): ?>
                                        中奖率：<strong><?php echo $winRate; ?>%</strong>
                                        <?php if ($netProfit > 0): ?>
                                            | 净收益：<strong style="color: #4ECDC4;">+<?php echo number_format($netProfit); ?></strong>
                                        <?php elseif ($netProfit < 0): ?>
                                            | 净收益：<strong style="color: #ff6b6b;"><?php echo number_format($netProfit); ?></strong>
                                        <?php else: ?>
                                            | 净收益：<strong>0</strong>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 第10页：社区互动 -->
                <div class="slider-page">
                    <div class="ppt-slide">
                        <div class="ppt-title">💬 <?php echo $year; ?> 年社区互动</div>
                        <div class="ppt-content">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-label">论坛发帖</div>
                                    <div class="stat-value"><?php echo number_format($forumPosts); ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">评论数量</div>
                                    <div class="stat-value"><?php echo number_format($comments); ?></div>
                                </div>
                            </div>
                            <div class="highlight-box" style="margin-top: 30px; border-left-color: #4ECDC4;">
                                <div style="font-size: 18px; color: #4ECDC4; line-height: 1.8; margin-bottom: 10px;">
                                    💬 您的每一次发言，都在为天枢社区注入活力
                                </div>
                                <div style="font-size: 16px; color: #aaa; line-height: 1.8;">
                                    <?php if ($forumPosts > 0 || $comments > 0): ?>
                                        这一年，您在论坛发表了 <strong style="color: #4ECDC4;"><?php echo number_format($forumPosts); ?></strong> 个帖子，
                                        留下了 <strong style="color: #4ECDC4;"><?php echo number_format($comments); ?></strong> 条评论
                                        <br>每一句话，都是您与天枢社区的对话；每一个观点，都在丰富着这个大家庭
                                    <?php else: ?>
                                        新的一年，期待您在天枢社区留下更多精彩的声音！
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 第11页：月度数据 -->
                <div class="slider-page">
                    <div class="ppt-slide">
                        <div class="ppt-title">📅 <?php echo $year; ?> 年月度数据</div>
                        <div class="ppt-content">
                            <div class="monthly-chart">
                                <?php 
                                $maxValue = max(array_map(function($m) { 
                                    return max($m['uploaded'], $m['downloaded']); 
                                }, $monthlyData));
                                $monthNames = ['', '1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];
                                foreach ($monthlyData as $month => $data): 
                                    $uploadHeight = $maxValue > 0 ? ($data['uploaded'] / $maxValue * 100) : 0;
                                    $downloadHeight = $maxValue > 0 ? ($data['downloaded'] / $maxValue * 100) : 0;
                                ?>
                                <div class="month-bar">
                                    <div style="display: flex; flex-direction: column; height: 100%; width: 100%; justify-content: flex-end;">
                                        <?php if ($data['uploaded'] > 0): ?>
                                        <div class="bar" style="height: <?php echo $uploadHeight; ?>%; background: linear-gradient(180deg, #00d4ff, #0099cc); margin-bottom: 2px;">
                                            <div class="bar-value"><?php echo mksize($data['uploaded']); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($data['downloaded'] > 0): ?>
                                        <div class="bar" style="height: <?php echo $downloadHeight; ?>%; background: linear-gradient(180deg, #ff6b6b, #cc0000);">
                                            <div class="bar-value"><?php echo mksize($data['downloaded']); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="margin-top: 10px; font-size: 14px;"><?php echo $monthNames[$month]; ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="text-align: center; margin-top: 20px; font-size: 14px; color: #aaa;">
                                <span style="color: #00d4ff;">■</span> 上传量 | 
                                <span style="color: #ff6b6b;">■</span> 下载量
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 第12页：总结 -->
                <div class="slider-page">
                    <div class="ppt-slide">
                        <div class="ppt-title">🎯 <?php echo $year; ?> 年总结</div>
                        <div class="ppt-content">
                            <div class="highlight-box">
                                <div style="font-size: 24px; color: #ffd700; margin-bottom: 15px;">
                                    💫 这一年，您在天枢留下了无数美好的足迹
                                </div>
                                <div style="font-size: 16px; color: #aaa; line-height: 1.8;">
                                    每一份数据，都记录着您与天枢共同成长的点点滴滴
                                </div>
                            </div>
                            
                            <div style="margin-top: 30px; font-size: 18px; line-height: 2.2;">
                                <div class="highlight-box" style="border-left-color: #ff6b6b;">
                                    🤝 您与好友互动了 <strong style="color: #ff6b6b; font-size: 22px;"><?php echo number_format($interactionStats['total_visits'] + $interactionStats['total_water'] + $interactionStats['total_steal']); ?></strong> 次
                                </div>
                                
                                <?php if ($attendance > 0): ?>
                                <div class="highlight-box" style="border-left-color: #ffd700;">
                                    📅 您签到了 <strong style="color: #ffd700; font-size: 22px;"><?php echo number_format($attendance); ?></strong> 天
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="margin-top: 40px; padding: 30px; background: rgba(138, 43, 226, 0.2); border-radius: 10px; text-align: center;">
                                <div style="font-size: 20px; color: #fff; line-height: 1.8; margin-bottom: 15px;">
                                    🌟 <strong style="color: #ffd700;">感谢您这一年的陪伴！</strong>
                                </div>
                                <div style="font-size: 16px; color: #aaa; line-height: 1.8;">
                                    天枢因每一位探索者而精彩<br>
                                    愿新的一年，我们继续在星辰大海中相遇<br>
                                    <span style="color: #00d4ff; font-size: 18px; margin-top: 10px; display: block;">
                                        🚀 天枢，用心做好每一个细节
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 第13页：天枢2025年荣誉榜单 -->
                <div class="slider-page">
                    <div class="ppt-slide">
                        <div class="ppt-title">🏆 天枢2025年荣誉榜单</div>
                        <div class="ppt-content">
                            <div style="text-align: center; margin-bottom: 30px;">
                                <div style="font-size: 20px; color: #ffd700; line-height: 1.8;">
                                    ✨ 这一年，天枢荣誉用户闪耀登场 ✨
                                </div>
                            </div>
                            
                            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
                                <!-- 下载量第一 -->
                                <div class="stat-card" style="border: 2px solid #00d4ff;">
                                    <div class="stat-label">📥 下载量第一</div>
                                    <div class="stat-value" style="font-size: 24px; color: #00d4ff;">
                                        <?php echo $downloadLeaderUser ? htmlspecialchars($downloadLeaderUser->username) : '暂无'; ?>
                                    </div>
                                    <?php if ($downloadLeaderUser && $downloadLeaderUser->id == $targetUserId): ?>
                                    <div style="margin-top: 10px; color: #ffd700; font-size: 14px;">🎉 恭喜您！</div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- 上传量第一 -->
                                <div class="stat-card" style="border: 2px solid #4ECDC4;">
                                    <div class="stat-label">📤 上传量第一</div>
                                    <div class="stat-value" style="font-size: 24px; color: #4ECDC4;">
                                        <?php echo $uploadLeaderUser ? htmlspecialchars($uploadLeaderUser->username) : '暂无'; ?>
                                    </div>
                                    <?php if ($uploadLeaderUser && $uploadLeaderUser->id == $targetUserId): ?>
                                    <div style="margin-top: 10px; color: #ffd700; font-size: 14px;">🎉 恭喜您！</div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- 捐赠第一 -->
                                <div class="stat-card" style="border: 2px solid #ff6b6b;">
                                    <div class="stat-label">💝 捐赠第一</div>
                                    <div class="stat-value" style="font-size: 24px; color: #ff6b6b;">
                                        <?php echo $donateLeader ? htmlspecialchars($donateLeader->username) : '暂无'; ?>
                                    </div>
                                    <?php if ($donateLeader && $donateLeader->id == $targetUserId): ?>
                                    <div style="margin-top: 10px; color: #ffd700; font-size: 14px;">🎉 恭喜您！</div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- 游戏总榜单第一 -->
                                <div class="stat-card" style="border: 2px solid #ffd700;">
                                    <div class="stat-label">🎮 游戏总榜单第一</div>
                                    <div class="stat-value" style="font-size: 24px; color: #ffd700;">
                                        <?php echo $gameLeaderUser ? htmlspecialchars($gameLeaderUser->username) : '暂无'; ?>
                                    </div>
                                    <?php if ($gameLeaderUser && $gameLeaderUser->id == $targetUserId): ?>
                                    <div style="margin-top: 10px; color: #ffd700; font-size: 14px;">🎉 恭喜您！</div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- 发种第一 -->
                                <div class="stat-card" style="border: 2px solid #a8e6cf;">
                                    <div class="stat-label">🌱 发种第一</div>
                                    <div class="stat-value" style="font-size: 24px; color: #a8e6cf;">
                                        <?php echo $torrentLeaderUser ? htmlspecialchars($torrentLeaderUser->username) : '暂无'; ?>
                                    </div>
                                    <?php if ($torrentLeaderUser && $torrentLeaderUser->id == $targetUserId): ?>
                                    <div style="margin-top: 10px; color: #ffd700; font-size: 14px;">🎉 恭喜您！</div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- 做种积分第一 -->
                                <div class="stat-card" style="border: 2px solid #ff6b9d;">
                                    <div class="stat-label">⭐ 做种积分第一</div>
                                    <div class="stat-value" style="font-size: 24px; color: #ff6b9d;">
                                        <?php echo $seedPointsLeader ? htmlspecialchars($seedPointsLeader->username) : '暂无'; ?>
                                    </div>
                                    <?php if ($seedPointsLeader && $seedPointsLeader->id == $targetUserId): ?>
                                    <div style="margin-top: 10px; color: #ffd700; font-size: 14px;">🎉 恭喜您！</div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- 慈善家第一 -->
                                <div class="stat-card" style="border: 2px solid #9b59b6;">
                                    <div class="stat-label">💖 慈善家第一</div>
                                    <div class="stat-value" style="font-size: 24px; color: #9b59b6;">
                                        <?php echo $charityLeaderUser ? htmlspecialchars($charityLeaderUser->username) : '暂无'; ?>
                                    </div>
                                    <?php if ($charityLeaderUser && $charityLeaderUser->id == $targetUserId): ?>
                                    <div style="margin-top: 10px; color: #ffd700; font-size: 14px;">🎉 恭喜您！</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($userInHonorList): ?>
                            <div class="highlight-box" style="margin-top: 40px; border-left-color: #ffd700; background: rgba(255, 215, 0, 0.1);">
                                <div style="font-size: 24px; color: #ffd700; line-height: 1.8; margin-bottom: 15px; text-align: center;">
                                    🎉 恭喜您荣登天枢2025年荣誉榜单！🎉
                                </div>
                                <div style="font-size: 18px; color: #fff; line-height: 1.8; text-align: center;">
                                    您的卓越表现，为天枢社区增添了无限光彩<br>
                                    所有上榜用户将在年终公示之后统一发放<strong style="color: #ffd700;">"一骑绝尘"特别勋章</strong>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="highlight-box" style="margin-top: 40px; border-left-color: #00d4ff;">
                                <div style="font-size: 20px; color: #00d4ff; line-height: 1.8; margin-bottom: 15px; text-align: center;">
                                    💫 这一年，天枢荣誉用户闪耀登场
                                </div>
                                <div style="font-size: 16px; color: #aaa; line-height: 1.8; text-align: center;">
                                    虽然没有您，但请新的一年继续努力！<br>
                                    所有上榜用户将在年终公示之后统一发放<strong style="color: #ffd700;">"一骑绝尘"特别勋章</strong><br>
                                    <span style="color: #00d4ff; font-size: 18px; margin-top: 10px; display: block;">
                                        🚀 2026年，期待您的名字出现在这里！
                                    </span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 第14页：2026年许愿 -->
                <div class="slider-page">
                    <div class="ppt-slide">
                        <div class="ppt-title">🎋 2026年许愿</div>
                        <div class="ppt-content">
                            <div class="wish-title-section">
                                <h3>✨ 许下您的新年愿望 ✨</h3>
                                <p>写下您对2026年的祝福，让愿望在星辰大海中闪耀<br>每一份祝福，都是对天枢最美好的期许</p>
                            </div>
                            
                            <div class="wish-form">
                                <div class="wish-input-group">
                                    <textarea id="wish-input" class="wish-input" rows="4" placeholder="在这里输入您的祝福话语（40字以内）...&#10;例如：愿天枢在新的一年里更加精彩！" maxlength="40"></textarea>
                                    <div class="wish-char-count" id="wish-char-count">0/40</div>
                                </div>
                                
                                <div class="wish-buttons-section">
                                    <button class="wish-random-btn" id="wish-random-btn" onclick="randomWish()">
                                        <span>🎲 随机祝福</span>
                                    </button>
                                </div>
                                
                                <div class="wish-submit-section">
                                    <button class="wish-submit-btn" id="wish-submit-btn" onclick="submitWish()">
                                        <span style="position: relative; z-index: 1;">✨ 提交许愿 ✨</span>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="highlight-box" style="margin-top: 30px; border-left-color: #ffd700;">
                                <div style="font-size: 16px; color: #ffd700; line-height: 1.8;">
                                    💝 您的许愿将与其他探索者的祝福一起，以弹幕形式在页面上方展示<br>
                                    <span style="color: #aaa; font-size: 14px;">所有提交许愿的用户都将获得2025年终总结勋章</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 第14页：结尾 -->
                <div class="slider-page">
                    <div class="ppt-slide" style="justify-content: center; text-align: center;">
                        <div style="font-size: 64px; margin-bottom: 40px;">🌟</div>
                        <div class="ppt-title" style="font-size: 42px; margin-bottom: 40px; line-height: 1.6;">
                            展望未来，与您同行
                        </div>
                        <div style="font-size: 20px; line-height: 2; color: #e5e7eb; max-width: 800px; margin: 0 auto 50px; text-align: left; padding: 0 20px;">
                            <p style="margin-bottom: 25px; color: #00d4ff;">
                                💫 亲爱的探索者，感谢您陪伴天枢走过2025年的每一个瞬间。
                            </p>
                            <p style="margin-bottom: 25px;">
                                天枢虽是一个新站，但我们深知，每一个伟大的社区都始于梦想，成于坚持。我们承诺，无论前路如何，都会以最大的热情和努力，为您打造一个更好的PT社区环境。
                            </p>
                            <p style="margin-bottom: 25px; color: #ffd700;">
                                ✨ 天枢的成长，离不开每一位用户的支持与陪伴。您的每一次分享、每一次互动、每一次建议，都是我们前进的动力。
                            </p>
                            <p style="margin-bottom: 25px;">
                                我们真诚地希望，您能继续在天枢活跃，与我们一起分享资源、交流心得、互帮互助。让我们携手，共同创建一个温暖、友好、充满活力的PT社区家园。
                            </p>
                            <p style="margin-bottom: 25px; color: #ff6b9d;">
                                💝 如果您愿意支持天枢的发展，欢迎通过捐赠的方式帮助我们。每一份支持，都将用于改善服务器、优化体验、丰富功能，让天枢变得更好。
                            </p>
                            <p style="margin-top: 40px; text-align: center; color: #4ECDC4; font-size: 22px; font-weight: bold;">
                                2026，让我们继续在星辰大海中相遇 🌌
                            </p>
                        </div>
                        <div style="margin-top: 50px; display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">
                            <a href="donate.php" style="color: #ffd700; text-decoration: none; font-size: 18px; padding: 12px 30px; border: 2px solid rgba(255, 215, 0, 0.6); border-radius: 8px; transition: all 0.3s; display: inline-block; background: rgba(255, 215, 0, 0.1);">💎 支持天枢</a>
                            <a href="userdetails.php?id=<?php echo $targetUserId; ?>" style="color: #00d4ff; text-decoration: none; font-size: 18px; padding: 12px 30px; border: 2px solid rgba(0, 212, 255, 0.5); border-radius: 8px; transition: all 0.3s; display: inline-block;">返回用户详情</a>
                            <a href="index.php" style="color: #00d4ff; text-decoration: none; font-size: 18px; padding: 12px 30px; border: 2px solid rgba(0, 212, 255, 0.5); border-radius: 8px; transition: all 0.3s; display: inline-block;">返回首页</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        const sliderContainer = document.getElementById('slider-container');
        const sliderNav = document.getElementById('slider-nav');
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const currentPageSpan = document.getElementById('current-page');
        const totalPagesSpan = document.getElementById('total-pages');
        
        const pages = sliderContainer.querySelectorAll('.slider-page');
        const totalPages = pages.length;
        let currentPage = 0;
        let danmakuStarted = false;
        
        // 弹幕功能 - 先定义wishesData（必须在所有使用它的函数之前定义）
        const wishesData = <?php echo json_encode($wishesData); ?>;
        const danmakuContainer = document.getElementById('danmaku-container');
        let danmakuPlayedIndex = 0; // 记录已播放的弹幕索引
        let danmakuInterval = null; // 弹幕播放定时器
        
        // 初始化导航点
        totalPagesSpan.textContent = totalPages;
        for (let i = 0; i < totalPages; i++) {
            const dot = document.createElement('div');
            dot.className = 'slider-dot' + (i === 0 ? ' active' : '');
            dot.addEventListener('click', () => goToPage(i));
            sliderNav.appendChild(dot);
        }
        
        function updatePage() {
            const translateX = -currentPage * 100;
            sliderContainer.style.transform = `translateX(${translateX}%)`;
            
            // 更新导航点
            const dots = sliderNav.querySelectorAll('.slider-dot');
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentPage);
            });
            
            // 更新计数器
            currentPageSpan.textContent = currentPage + 1;
            
            // 更新按钮状态
            prevBtn.disabled = currentPage === 0;
            nextBtn.disabled = currentPage === totalPages - 1;
        }
        
        function goToPage(page) {
            if (page < 0 || page >= totalPages) return;
            currentPage = page;
            updatePage();
        }
        
        function prevPage() {
            if (currentPage > 0) {
                goToPage(currentPage - 1);
            }
        }
        
        function nextPage() {
            if (currentPage < totalPages - 1) {
                goToPage(currentPage + 1);
            }
        }
        
        prevBtn.addEventListener('click', prevPage);
        nextBtn.addEventListener('click', nextPage);
        
        // 键盘控制
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') prevPage();
            if (e.key === 'ArrowRight') nextPage();
        });
        
        function createDanmaku(wish, name) {
            if (!danmakuContainer) return;
            
            const danmaku = document.createElement('div');
            danmaku.className = 'danmaku-item';
            danmaku.textContent = name + '：' + wish;
            
            // 随机颜色
            const colors = ['#00d4ff', '#ff6b6b', '#ffd700', '#4ECDC4', '#ff6b9d', '#a8e6cf'];
            danmaku.style.color = colors[Math.floor(Math.random() * colors.length)];
            
            // 随机高度
            const top = Math.random() * 80 + 10; // 10% - 90%
            danmaku.style.top = top + '%';
            
            // 随机速度（8-15秒）
            const duration = 8 + Math.random() * 7;
            danmaku.style.animationDuration = duration + 's';
            
            danmakuContainer.appendChild(danmaku);
            
            // 动画结束后移除
            setTimeout(() => {
                if (danmaku.parentNode) {
                    danmaku.parentNode.removeChild(danmaku);
                }
            }, duration * 1000);
        }
        
        function startDanmaku() {
            if (!wishesData || wishesData.length === 0) return;
            if (danmakuStarted) return; // 如果已经启动，不再重复启动
            
            danmakuStarted = true;
            
            // 按照顺序播放所有弹幕，每条间隔2秒，只播放一次
            function playNextDanmaku() {
                if (danmakuPlayedIndex < wishesData.length) {
                    const wish = wishesData[danmakuPlayedIndex];
                    createDanmaku(wish.wish, wish.name);
                    danmakuPlayedIndex++;
                    
                    // 继续播放下一条
                    danmakuInterval = setTimeout(playNextDanmaku, 2000);
                } else {
                    // 所有弹幕播放完毕，停止
                    danmakuInterval = null;
                }
            }
            
            // 立即开始播放
            playNextDanmaku();
        }
        
        // 全局显示弹幕（所有页面都显示，只启动一次）
        function checkAndStartDanmaku() {
            // 只要弹幕还没开始播放，就启动
            if (!danmakuStarted && wishesData && wishesData.length > 0) {
                startDanmaku();
            }
        }
        
        // 初始化
        updatePage();
        
        // 启动全局弹幕（页面加载后自动开始）
        setTimeout(checkAndStartDanmaku, 500);
        
        // 背景音乐控制
        const musicToggleBtn = document.getElementById('music-toggle-btn');
        const musicInfo = document.getElementById('music-info');
        const bilibiliPlayer = document.getElementById('bilibili-player');
        let isMusicPlaying = true; // 默认播放状态
        
        if (musicToggleBtn && bilibiliPlayer) {
            // 初始化：设置为播放状态
            musicToggleBtn.classList.add('playing');
            musicToggleBtn.querySelector('.music-icon').textContent = '⏸️';
            
            // 尝试通过postMessage控制B站播放器
            function toggleMusic() {
                if (!isMusicPlaying) {
                    // 播放音乐
                    try {
                        // B站播放器控制方法 - 尝试重新加载iframe并自动播放
                        const currentSrc = bilibiliPlayer.src;
                        if (currentSrc.includes('autoplay=0')) {
                            bilibiliPlayer.src = currentSrc.replace('autoplay=0', 'autoplay=1');
                        } else if (!currentSrc.includes('autoplay=')) {
                            bilibiliPlayer.src = currentSrc + (currentSrc.includes('?') ? '&' : '?') + 'autoplay=1';
                        }
                        // 等待iframe加载后尝试播放
                        setTimeout(() => {
                            try {
                                bilibiliPlayer.contentWindow.postMessage('{"event":"command","func":"playVideo","args":""}', '*');
                            } catch (e) {
                                console.log('无法通过postMessage控制，使用iframe自动播放');
                            }
                        }, 1000);
                        isMusicPlaying = true;
                        musicToggleBtn.classList.add('playing');
                        musicInfo.style.display = 'block';
                        musicToggleBtn.querySelector('.music-icon').textContent = '⏸️';
                    } catch (e) {
                        console.log('无法控制播放器，可能需要用户手动点击');
                        isMusicPlaying = true;
                        musicToggleBtn.classList.add('playing');
                        musicInfo.style.display = 'block';
                        musicToggleBtn.querySelector('.music-icon').textContent = '⏸️';
                    }
                } else {
                    // 暂停音乐
                    try {
                        bilibiliPlayer.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
                        isMusicPlaying = false;
                        musicToggleBtn.classList.remove('playing');
                        musicInfo.style.display = 'none';
                        musicToggleBtn.querySelector('.music-icon').textContent = '🎵';
                    } catch (e) {
                        console.log('无法控制播放器');
                        // 尝试重新加载iframe并关闭自动播放
                        const currentSrc = bilibiliPlayer.src;
                        if (currentSrc.includes('autoplay=1')) {
                            bilibiliPlayer.src = currentSrc.replace('autoplay=1', 'autoplay=0');
                        }
                        isMusicPlaying = false;
                        musicToggleBtn.classList.remove('playing');
                        musicInfo.style.display = 'none';
                        musicToggleBtn.querySelector('.music-icon').textContent = '🎵';
                    }
                }
            }
            
            musicToggleBtn.addEventListener('click', toggleMusic);
            
            // 监听B站播放器的消息
            window.addEventListener('message', function(event) {
                // 处理B站播放器的消息
                if (event.data && typeof event.data === 'string') {
                    try {
                        const data = JSON.parse(event.data);
                        if (data.event === 'onPlay') {
                            isMusicPlaying = true;
                            musicToggleBtn.classList.add('playing');
                            musicInfo.style.display = 'block';
                            musicToggleBtn.querySelector('.music-icon').textContent = '⏸️';
                        } else if (data.event === 'onPause') {
                            isMusicPlaying = false;
                            musicToggleBtn.classList.remove('playing');
                            musicInfo.style.display = 'none';
                            musicToggleBtn.querySelector('.music-icon').textContent = '🎵';
                        }
                    } catch (e) {
                        // 忽略解析错误
                    }
                }
            });
        }
        
        // 许愿功能
        const wishInput = document.getElementById('wish-input');
        const wishCharCount = document.getElementById('wish-char-count');
        const wishSubmitBtn = document.getElementById('wish-submit-btn');
        
        if (wishInput && wishCharCount) {
            wishInput.addEventListener('input', function() {
                const length = this.value.length;
                wishCharCount.textContent = length + '/40';
                wishCharCount.classList.toggle('warning', length > 40);
            });
        }
        
        window.setWish = function(wish) {
            if (wishInput) {
                wishInput.value = wish;
                wishInput.dispatchEvent(new Event('input'));
            }
        };
        
        // 随机祝福功能
        window.randomWish = function() {
            const defaultWishes = <?php echo json_encode($defaultWishes); ?>;
            if (defaultWishes && defaultWishes.length > 0 && wishInput) {
                const randomIndex = Math.floor(Math.random() * defaultWishes.length);
                const randomWish = defaultWishes[randomIndex];
                wishInput.value = randomWish;
                wishInput.dispatchEvent(new Event('input'));
                
                // 添加一个简单的动画效果
                const randomBtn = document.getElementById('wish-random-btn');
                if (randomBtn) {
                    randomBtn.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        randomBtn.style.transform = '';
                    }, 150);
                }
            }
        };
        
        window.submitWish = function() {
            if (!wishInput || !wishSubmitBtn) return;
            
            const wish = wishInput.value.trim();
            if (wish.length === 0 || wish.length > 40) {
                alert('许愿话语长度必须在1-40字之间');
                return;
            }
            
            wishSubmitBtn.disabled = true;
            wishSubmitBtn.textContent = '提交中...';
            
            const formData = new FormData();
            formData.append('action', 'submit_wish');
            formData.append('wish', wish);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('许愿成功！');
                    wishInput.value = '';
                    wishInput.dispatchEvent(new Event('input'));
                } else {
                    alert(data.message || '许愿失败，请重试');
                }
            })
            .catch(error => {
                alert('提交失败，请重试');
                console.error('Error:', error);
            })
            .finally(() => {
                wishSubmitBtn.disabled = false;
                wishSubmitBtn.textContent = '提交许愿 ✨';
            });
        };
    })();
    </script>
</body>
</html>

