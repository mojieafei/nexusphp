<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class GenerateYearReportCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'year-report:generate-cache 
                            {--year=2025 : 年份}
                            {--user-id= : 指定用户ID，不指定则处理所有用户}
                            {--batch-size=100 : 每批处理的用户数量}
                            {--force : 强制重新生成，即使缓存已存在}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '批量生成所有用户的年度报告数据并缓存到Redis';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $year = (int)$this->option('year');
        $userId = $this->option('user-id');
        $batchSize = (int)$this->option('batch-size');
        $force = $this->option('force');

        $this->info("开始生成 {$year} 年度报告缓存...");

        // 计算年份的日期范围
        $startDate = Carbon::create($year, 1, 1, 0, 0, 0);
        $endDate = Carbon::create($year, 12, 31, 23, 59, 59);

        // 获取要处理的用户
        if ($userId) {
            $users = User::where('id', $userId)->where('enabled', 'yes')->get();
        } else {
            $users = User::where('enabled', 'yes')->get();
        }

        $total = $users->count();
        $this->info("共需要处理 {$total} 个用户");

        $processed = 0;
        $success = 0;
        $failed = 0;

        foreach ($users->chunk($batchSize) as $chunk) {
            foreach ($chunk as $user) {
                $processed++;
                
                try {
                    $cacheKey = "year_report:{$year}:user:{$user->id}";
                    
                    // 如果缓存已存在且不强制重新生成，则跳过
                    if (!$force && Redis::exists($cacheKey)) {
                        $this->line("[{$processed}/{$total}] 用户 {$user->id} ({$user->username}) - 缓存已存在，跳过");
                        $success++;
                        continue;
                    }

                    $this->line("[{$processed}/{$total}] 正在处理用户 {$user->id} ({$user->username})...");

                    // 生成报告数据
                    $reportData = $this->generateReportData($user, $year, $startDate, $endDate);
                    
                    // 添加缓存生成时间
                    $reportData['cache_generated_at'] = Carbon::now()->format('Y-m-d H:i:s');
                    $reportData['cache_generated_timestamp'] = Carbon::now()->timestamp;

                    // 存储到Redis，设置过期时间为1年
                    Redis::setex($cacheKey, 31536000, json_encode($reportData, JSON_UNESCAPED_UNICODE));

                    $success++;
                    $this->info("  ✓ 完成");
                } catch (\Exception $e) {
                    $failed++;
                    $this->error("  ✗ 失败: " . $e->getMessage());
                    do_log("生成年度报告缓存失败 - 用户ID: {$user->id}, 错误: " . $e->getMessage(), 'error');
                }
            }
        }

        $this->info("\n处理完成！");
        $this->info("总计: {$total} 个用户");
        $this->info("成功: {$success} 个");
        $this->info("失败: {$failed} 个");

        return Command::SUCCESS;
    }

    /**
     * 生成单个用户的报告数据
     *
     * @param User $user
     * @param int $year
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    private function generateReportData(User $user, int $year, Carbon $startDate, Carbon $endDate): array
    {
        $targetUserId = $user->id;

        // 1. 基础数据统计
        $inviter = $user->inviter;
        $invitedCount = User::where('invited_by', $targetUserId)->count();

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
            ->where('finished', 'yes')
            ->whereNotNull('completedat')
            ->whereBetween('completedat', [$startDate, $endDate])
            ->get();

        $yearUploaded = $snatches->sum('uploaded');
        $yearDownloaded = $snatches->sum('downloaded');
        $yearShareRatio = $yearDownloaded > 0 ? round($yearUploaded / $yearDownloaded, 3) : ($yearUploaded > 0 ? '∞' : 0);

        // 2.2 2025年真实流量统计
        $yearTrueUploaded = 0;
        $yearTrueDownloaded = 0;
        $isAnnounceLogEnabledForYear = \App\Models\Setting::getIsRecordAnnounceLog();

        if ($isAnnounceLogEnabledForYear) {
            try {
                $clickhouseClient = app(\ClickHouseDB\Client::class);
                $startDateStr = $startDate->format('Y-m-d H:i:s');
                $endDateStr = $endDate->format('Y-m-d H:i:s');
                
                $uploadedSql = sprintf(
                    "SELECT sum(uploaded_increment_for_user) as total FROM announce_logs WHERE user_id = %d AND timestamp >= '%s' AND timestamp <= '%s'",
                    $targetUserId, $startDateStr, $endDateStr
                );
                $uploadedResult = $clickhouseClient->select($uploadedSql);
                $uploadedRows = $uploadedResult->rows();
                $yearTrueUploaded = isset($uploadedRows[0]['total']) ? (float)$uploadedRows[0]['total'] : 0;
                
                $downloadedSql = sprintf(
                    "SELECT sum(downloaded_increment_for_user) as total FROM announce_logs WHERE user_id = %d AND timestamp >= '%s' AND timestamp <= '%s'",
                    $targetUserId, $startDateStr, $endDateStr
                );
                $downloadedResult = $clickhouseClient->select($downloadedSql);
                $downloadedRows = $downloadedResult->rows();
                $yearTrueDownloaded = isset($downloadedRows[0]['total']) ? (float)$downloadedRows[0]['total'] : 0;
            } catch (\Exception $e) {
                $yearTrueUploaded = $yearUploaded;
                $yearTrueDownloaded = $yearDownloaded;
            }
        } else {
            $yearTrueUploaded = $yearUploaded;
            $yearTrueDownloaded = $yearDownloaded;
        }

        $yearTrueShareRatio = $yearTrueDownloaded > 0 ? round($yearTrueUploaded / $yearTrueDownloaded, 3) : ($yearTrueUploaded > 0 ? '∞' : 0);

        // 总流量（所有时间，从users表）
        $totalUploaded = $user->uploaded;
        $totalDownloaded = $user->downloaded;
        $totalShareRatio = $totalDownloaded > 0 ? round($totalUploaded / $totalDownloaded, 3) : ($totalUploaded > 0 ? '∞' : 0);

        // 真实流量（所有时间，从snatched表统计）
        $trueUploadedResult = \Nexus\Database\NexusDB::selectOne(
            "SELECT SUM(uploaded) as total FROM snatched WHERE userid = ?",
            [$targetUserId]
        );
        $trueDownloadedResult = \Nexus\Database\NexusDB::selectOne(
            "SELECT SUM(downloaded) as total FROM snatched WHERE userid = ?",
            [$targetUserId]
        );
        $trueUploaded = isset($trueUploadedResult['total']) ? (float)$trueUploadedResult['total'] : 0;
        $trueDownloaded = isset($trueDownloadedResult['total']) ? (float)$trueDownloadedResult['total'] : 0;
        $trueShareRatio = $trueDownloaded > 0 ? round($trueUploaded / $trueDownloaded, 3) : ($trueUploaded > 0 ? '∞' : 0);

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

        // 做种数量
        $seedingCount = \Nexus\Database\NexusDB::selectOne(
            "SELECT COUNT(*) as count 
             FROM peers 
             LEFT JOIN torrents ON peers.torrent = torrents.id 
             LEFT JOIN categories ON torrents.category = categories.id 
             LEFT JOIN snatched ON torrents.id = snatched.torrentid 
             WHERE peers.userid = ? 
             AND snatched.userid = ? 
             AND peers.seeder = 'yes'",
            [$targetUserId, $targetUserId]
        );
        $seedingCount = $seedingCount ? (int)$seedingCount['count'] : 0;

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

        // 6. 星尘农场互动数据
        $interactions = \App\Models\StardustInteraction::where('from_user_id', $targetUserId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $interactionStats = [
            'total_visits' => $interactions->where('action', 'visit')->count(),
            'total_water' => $interactions->where('action', 'water')->count(),
            'total_steal' => $interactions->where('action', 'steal')->count(),
        ];

        // 7. 论坛和评论数据
        $forumPosts = \App\Models\Topic::where('userid', $targetUserId)->count();

        $firstPostIds = \App\Models\Topic::whereNotNull('firstpost')
            ->where('firstpost', '>', 0)
            ->pluck('firstpost')
            ->toArray();

        $comments = \App\Models\Post::where('userid', $targetUserId)
            ->whereNotIn('id', $firstPostIds)
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

        // 计算最长连续签到天数
        $maxContinuous = 0;
        $currentContinuous = 0;
        $lastDate = null;
        $sortedLogs = $attendanceLogs->sortBy('date');

        foreach ($sortedLogs as $log) {
            $logDate = Carbon::parse($log->date)->startOfDay();
            
            if ($lastDate === null) {
                $currentContinuous = 1;
            } else {
                $daysDiff = $lastDate->diffInDays($logDate, false);
                
                if ($daysDiff == 1) {
                    $currentContinuous++;
                } else {
                    $maxContinuous = max($maxContinuous, $currentContinuous);
                    $currentContinuous = 1;
                }
            }
            $lastDate = $logDate;
        }

        $maxContinuous = max($maxContinuous, $currentContinuous);
        if ($attendanceLogs->count() == 0) {
            $maxContinuous = 0;
        }

        // 9. 做种时间统计
        $seedTime = $user->seedtime ?? 0;
        $leechTime = $user->leechtime ?? 0;
        $seedTimeDays = round($seedTime / 86400, 1);
        $leechTimeDays = round($leechTime / 86400, 1);
        $seedLeechRatio = $leechTime > 0 ? round($seedTime / $leechTime, 3) : ($seedTime > 0 ? '∞' : 0);

        // 10. 火星幸运局统计
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
            'total_bet_amount' => abs($marsDuelBets->sum('value')),
            'total_wins' => $marsDuelWins->count(),
            'total_win_amount' => $marsDuelWins->sum('value'),
        ];

        // 计算综合评分
        $scoreData = [
            'uploaded' => $totalUploaded,
            'downloaded' => $totalDownloaded,
            'shareRatio' => $totalShareRatio,
            'attendance' => $attendance,
            'torrentsUploaded' => $torrentsUploaded,
            'seedTimeDays' => $seedTimeDays,
            'forumPosts' => $forumPosts,
            'comments' => $comments,
            'gameStats' => $gameStats,
            'farmData' => $farmData,
        ];

        $yearScore = $this->calculateYearScore($scoreData);
        $userTitle = $this->getTitleByScore($yearScore);

        // 返回所有数据（确保所有集合类型都转换为数组）
        return [
            'userInfo' => $userInfo,
            'yearUploaded' => $yearUploaded,
            'yearDownloaded' => $yearDownloaded,
            'yearShareRatio' => $yearShareRatio,
            'yearTrueUploaded' => $yearTrueUploaded,
            'yearTrueDownloaded' => $yearTrueDownloaded,
            'yearTrueShareRatio' => $yearTrueShareRatio,
            'totalUploaded' => $totalUploaded,
            'totalDownloaded' => $totalDownloaded,
            'totalShareRatio' => $totalShareRatio,
            'trueUploaded' => $trueUploaded,
            'trueDownloaded' => $trueDownloaded,
            'trueShareRatio' => $trueShareRatio,
            'torrentsUploaded' => $torrentsUploaded,
            'torrentsSize' => $torrentsSize,
            'snatchesCount' => $snatchesCount,
            'seedingCount' => $seedingCount,
            'farmData' => $farmData,
            'stardustEarned' => $stardustEarned,
            'stardustSpent' => $stardustSpent,
            'stardustBySource' => $stardustBySource->toArray(), // 转换为数组以便JSON序列化
            'gameStats' => $gameStats,
            'interactionStats' => $interactionStats,
            'forumPosts' => $forumPosts,
            'comments' => $comments,
            'attendance' => $attendance,
            'attendanceBonus' => $attendanceBonus,
            'currentContinuousDays' => $currentContinuousDays,
            'totalAttendanceDays' => $totalAttendanceDays,
            'maxContinuous' => $maxContinuous,
            'seedTime' => $seedTime,
            'leechTime' => $leechTime,
            'seedTimeDays' => $seedTimeDays,
            'leechTimeDays' => $leechTimeDays,
            'seedLeechRatio' => $seedLeechRatio,
            'marsDuelStats' => $marsDuelStats,
            'yearScore' => $yearScore,
            'userTitle' => $userTitle,
        ];
    }

    /**
     * 计算综合评分
     */
    private function calculateYearScore($data)
    {
        $score = 0;
        
        $uploadedGB = $data['uploaded'] / (1024 * 1024 * 1024);
        if ($uploadedGB >= 1000) $score += 2.0;
        elseif ($uploadedGB >= 500) $score += 1.5;
        elseif ($uploadedGB >= 200) $score += 1.0;
        elseif ($uploadedGB >= 100) $score += 0.7;
        elseif ($uploadedGB >= 50) $score += 0.4;
        elseif ($uploadedGB >= 10) $score += 0.2;
        
        $downloadedGB = $data['downloaded'] / (1024 * 1024 * 1024);
        if ($downloadedGB >= 500) $score += 1.0;
        elseif ($downloadedGB >= 200) $score += 0.7;
        elseif ($downloadedGB >= 100) $score += 0.5;
        elseif ($downloadedGB >= 50) $score += 0.3;
        elseif ($downloadedGB >= 10) $score += 0.1;
        
        if ($data['shareRatio'] === '∞' || (is_numeric($data['shareRatio']) && $data['shareRatio'] >= 2.0)) {
            $score += 1.5;
        } elseif (is_numeric($data['shareRatio']) && $data['shareRatio'] >= 1.5) {
            $score += 1.2;
        } elseif (is_numeric($data['shareRatio']) && $data['shareRatio'] >= 1.0) {
            $score += 1.0;
        } elseif (is_numeric($data['shareRatio']) && $data['shareRatio'] >= 0.8) {
            $score += 0.7;
        } elseif (is_numeric($data['shareRatio']) && $data['shareRatio'] >= 0.5) {
            $score += 0.4;
        } elseif (is_numeric($data['shareRatio']) && $data['shareRatio'] >= 0.3) {
            $score += 0.2;
        }
        
        if ($data['attendance'] >= 300) $score += 1.0;
        elseif ($data['attendance'] >= 200) $score += 0.8;
        elseif ($data['attendance'] >= 150) $score += 0.6;
        elseif ($data['attendance'] >= 100) $score += 0.4;
        elseif ($data['attendance'] >= 50) $score += 0.2;
        elseif ($data['attendance'] >= 20) $score += 0.1;
        
        if ($data['torrentsUploaded'] >= 50) $score += 1.5;
        elseif ($data['torrentsUploaded'] >= 30) $score += 1.2;
        elseif ($data['torrentsUploaded'] >= 20) $score += 1.0;
        elseif ($data['torrentsUploaded'] >= 10) $score += 0.7;
        elseif ($data['torrentsUploaded'] >= 5) $score += 0.4;
        elseif ($data['torrentsUploaded'] >= 1) $score += 0.2;
        
        if ($data['seedTimeDays'] >= 200) $score += 1.0;
        elseif ($data['seedTimeDays'] >= 150) $score += 0.8;
        elseif ($data['seedTimeDays'] >= 100) $score += 0.6;
        elseif ($data['seedTimeDays'] >= 50) $score += 0.4;
        elseif ($data['seedTimeDays'] >= 20) $score += 0.2;
        elseif ($data['seedTimeDays'] >= 10) $score += 0.1;
        
        $communityActivity = $data['forumPosts'] + $data['comments'];
        if ($communityActivity >= 100) $score += 0.5;
        elseif ($communityActivity >= 50) $score += 0.3;
        elseif ($communityActivity >= 20) $score += 0.2;
        elseif ($communityActivity >= 10) $score += 0.1;
        
        if ($data['gameStats']['total_games'] >= 100 && $data['gameStats']['max_score'] >= 10000) $score += 0.5;
        elseif ($data['gameStats']['total_games'] >= 50 && $data['gameStats']['max_score'] >= 5000) $score += 0.3;
        elseif ($data['gameStats']['total_games'] >= 20 && $data['gameStats']['max_score'] >= 2000) $score += 0.2;
        elseif ($data['gameStats']['total_games'] >= 10) $score += 0.1;
        
        $farmScore = 0;
        if ($data['farmData']['current_level'] >= 20) $farmScore += 0.4;
        elseif ($data['farmData']['current_level'] >= 15) $farmScore += 0.3;
        elseif ($data['farmData']['current_level'] >= 10) $farmScore += 0.2;
        elseif ($data['farmData']['current_level'] >= 5) $farmScore += 0.1;
        
        if ($data['farmData']['achievements_count'] >= 20) $farmScore += 0.3;
        elseif ($data['farmData']['achievements_count'] >= 10) $farmScore += 0.2;
        elseif ($data['farmData']['achievements_count'] >= 5) $farmScore += 0.1;
        
        if ($data['farmData']['fragments_count'] + $data['farmData']['planets_count'] >= 100) $farmScore += 0.3;
        elseif ($data['farmData']['fragments_count'] + $data['farmData']['planets_count'] >= 50) $farmScore += 0.2;
        elseif ($data['farmData']['fragments_count'] + $data['farmData']['planets_count'] >= 20) $farmScore += 0.1;
        
        $score += min($farmScore, 1.0);
        
        return min(10, max(0, round($score, 1)));
    }

    /**
     * 获取头衔
     */
    private function getTitleByScore($score)
    {
        $titles = [
            0 => ['name' => '初来乍到', 'desc' => '欢迎来到天枢，开始您的探索之旅'],
            1 => ['name' => '新手上路', 'desc' => '您已经迈出了第一步，继续加油'],
            2 => ['name' => '小有成就', 'desc' => '您正在逐步成长，表现不错'],
            3 => ['name' => '渐入佳境', 'desc' => '您已经熟悉了天枢，表现越来越好'],
            4 => ['name' => '活跃用户', 'desc' => '您是天枢的活跃成员，感谢您的参与'],
            5 => ['name' => '优秀成员', 'desc' => '您的表现非常优秀，是天枢的中坚力量'],
            6 => ['name' => '精英用户', 'desc' => '您是天枢的精英，为社区做出了重要贡献'],
            7 => ['name' => '社区之星', 'desc' => '您是天枢的明星用户，闪耀着独特的光芒'],
            8 => ['name' => '天枢之光', 'desc' => '您是天枢的骄傲，照亮了社区前进的道路'],
            9 => ['name' => '传奇人物', 'desc' => '您在天枢创造了传奇，是所有人的榜样'],
            10 => ['name' => '天枢之神', 'desc' => '您是天枢的至高存在，无人能及'],
        ];
        
        $scoreInt = (int)floor($score);
        return $titles[min(10, max(0, $scoreInt))];
    }
}

