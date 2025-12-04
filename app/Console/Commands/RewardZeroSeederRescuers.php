<?php

namespace App\Console\Commands;

use App\Models\BonusLogs;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class RewardZeroSeederRescuers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reward:zero-seeder-rescuers 
                            {--days=7 : 做种人数为0持续天数，默认7天}
                            {--bonus=1000 : 每个种子奖励的魔力值，默认1000}
                            {--min-seed-hours=168 : 用户对每个种子的最低做种时间要求（小时），默认168小时（7天）}
                            {--dry-run : 仅预览，不实际发放奖励}
                            {--update-track : 更新零做种种子追踪表}
                            {--only-update-track : 只更新追踪表，不执行奖励流程}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '奖励对做种人数=0且持续N天的种子进行辅种的用户';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $days = (int)$this->option('days');
        $bonusAmount = (int)$this->option('bonus');
        $minSeedHours = (int)$this->option('min-seed-hours');
        $dryRun = $this->option('dry-run');
        $updateTrack = $this->option('update-track');
        $onlyUpdateTrack = $this->option('only-update-track');

        // 如果只更新追踪表，执行后直接退出
        if ($onlyUpdateTrack) {
            $this->info("=== 更新零做种种子追踪表 ===");
            $this->updateZeroSeederTracking();
            $this->info("\n✅ 追踪表更新完成！");
            return 0;
        }

        $this->info("开始处理：做种人数=0持续{$days}天的种子奖励");
        $this->info("每个种子奖励：{$bonusAmount}魔力值");
        $this->info("最低做种时间要求：{$minSeedHours}小时（" . round($minSeedHours / 24, 1) . "天）");
        
        if ($dryRun) {
            $this->warn("⚠️  当前为预览模式，不会实际发放奖励");
        }

        // 第一步：更新零做种种子追踪表
        if ($updateTrack) {
            $this->info("\n=== 第一步：更新零做种种子追踪表 ===");
            $this->updateZeroSeederTracking();
        }

        // 第二步：找出符合条件的种子（持续N天）
        $this->info("\n=== 第二步：查找符合条件的种子 ===");
        
        // 在执行奖励前，先清理已恢复做种的种子记录（如果种子已恢复，不应再奖励）
        $this->cleanupRecoveredTorrents();
        
        $targetDate = Carbon::now()->subDays($days);
        $qualifiedTorrents = $this->getQualifiedTorrents($targetDate);
        
        if ($qualifiedTorrents->isEmpty()) {
            $this->info("没有找到符合条件的种子");
            return 0;
        }

        $this->info("找到 " . $qualifiedTorrents->count() . " 个符合条件的种子");

        // 第三步：找出对这些种子进行正常辅种的用户
        $this->info("\n=== 第三步：查找辅种用户 ===");
        $torrentIds = $qualifiedTorrents->pluck('torrent_id')->toArray();
        $usersToReward = $this->getUsersToReward($torrentIds, $minSeedHours);

        if (empty($usersToReward)) {
            $this->info("没有找到需要奖励的用户");
            return 0;
        }

        $totalUsers = count($usersToReward);
        $totalTorrents = array_sum(array_column($usersToReward, 'torrent_count'));
        $totalBonus = $totalTorrents * $bonusAmount;

        $this->info("找到 {$totalUsers} 个用户，共辅种了 {$totalTorrents} 个种子");
        $this->info("将发放总魔力值：{$totalBonus}");

        // 显示详细列表
        if ($this->confirm('是否显示详细列表？', true)) {
            $this->displayRewardList($usersToReward, $bonusAmount);
        }

        // 第四步：发放奖励
        if (!$dryRun) {
            if (!$this->confirm('确认发放奖励？', false)) {
                $this->info("已取消操作");
                return 0;
            }

            $this->info("\n=== 第四步：发放奖励 ===");
            $this->rewardUsers($usersToReward, $bonusAmount, $torrentIds);
        } else {
            $this->info("\n预览模式：跳过实际发放");
        }

        $this->info("\n✅ 处理完成！");
        return 0;
    }

    /**
     * 更新零做种种子追踪表
     */
    protected function updateZeroSeederTracking()
    {
        // 找出当前做种人数=0的种子，并获取last_action用于估算断种时间
        $zeroSeederTorrents = Torrent::query()
            ->where('seeders', 0)
            ->where('visible', 'yes')
            ->where('banned', 'no')
            ->select('id', 'last_action', 'added')
            ->get();

        $this->info("当前做种人数=0的种子数量：" . $zeroSeederTorrents->count());

        $now = Carbon::now()->toDateTimeString();
        $updated = 0;
        $inserted = 0;
        $deleted = 0;

        foreach ($zeroSeederTorrents as $torrent) {
            $torrentId = $torrent->id;
            $existing = DB::table('zero_seeder_torrents')
                ->where('torrent_id', $torrentId)
                ->first();

            if ($existing) {
                // 检查当前是否还是0做种
                $current = Torrent::find($torrentId);
                if ($current && $current->seeders > 0) {
                    // 做种人数恢复了，删除记录
                    DB::table('zero_seeder_torrents')
                        ->where('torrent_id', $torrentId)
                        ->delete();
                    $deleted++;
                    continue;
                }
                // 更新最后检查时间
                DB::table('zero_seeder_torrents')
                    ->where('torrent_id', $torrentId)
                    ->update(['last_checked_at' => $now]);
                $updated++;
            } else {
                // 新记录：估算断种开始时间
                // 优先使用last_action（最后一次活动时间），如果没有则使用种子添加时间
                $estimatedStartTime = $now;
                if ($torrent->last_action) {
                    // 如果last_action存在，使用它作为断种开始时间的估算
                    // 因为如果种子还有做种人，last_action会更频繁更新
                    // 所以如果做种人数=0且last_action很早，说明可能已经断种很久了
                    $estimatedStartTime = $torrent->last_action;
                } elseif ($torrent->added) {
                    // 如果连last_action都没有，使用种子添加时间
                    $estimatedStartTime = $torrent->added;
                }

                DB::table('zero_seeder_torrents')->insert([
                    'torrent_id' => $torrentId,
                    'zero_seeder_start_time' => $estimatedStartTime,
                    'last_checked_at' => $now,
                    'rewarded' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $inserted++;
            }
        }

        $this->info("更新 {$updated} 条记录，新增 {$inserted} 条记录，删除 {$deleted} 条记录（已恢复做种）");
    }

    /**
     * 清理已恢复做种的种子记录
     */
    protected function cleanupRecoveredTorrents()
    {
        // 找出追踪表中记录但种子已恢复做种的情况
        $recovered = DB::table('zero_seeder_torrents')
            ->join('torrents', 'zero_seeder_torrents.torrent_id', '=', 'torrents.id')
            ->where('torrents.seeders', '>', 0)
            ->where('zero_seeder_torrents.rewarded', 0)
            ->pluck('zero_seeder_torrents.torrent_id')
            ->toArray();
        
        if (!empty($recovered)) {
            $deleted = DB::table('zero_seeder_torrents')
                ->whereIn('torrent_id', $recovered)
                ->delete();
            $this->info("清理已恢复做种的种子记录：{$deleted} 条");
        }
    }

    /**
     * 获取符合条件的种子（持续N天）
     */
    protected function getQualifiedTorrents(Carbon $targetDate)
    {
        return DB::table('zero_seeder_torrents')
            ->where('rewarded', 0)
            ->where('zero_seeder_start_time', '<=', $targetDate->toDateTimeString())
            ->join('torrents', 'zero_seeder_torrents.torrent_id', '=', 'torrents.id')
            ->where('torrents.seeders', 0)
            ->where('torrents.visible', 'yes')
            ->where('torrents.banned', 'no')
            // 排除官方种子（tag_id=3）
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('torrent_tags')
                    ->whereColumn('torrent_tags.torrent_id', 'torrents.id')
                    ->where('torrent_tags.tag_id', 3);
            })
            ->select('zero_seeder_torrents.torrent_id', 'zero_seeder_torrents.zero_seeder_start_time', 'torrents.name')
            ->get();
    }

    /**
     * 获取需要奖励的用户列表
     */
    protected function getUsersToReward(array $torrentIds, int $minSeedHours = 168)
    {
        if (empty($torrentIds)) {
            return [];
        }

        $minSeedSeconds = $minSeedHours * 3600; // 转换为秒

        // 找出对这些种子完成下载（finished=yes）、正在做种、且做种时间达到要求的用户
        // 通过peers表确认当前正在做种
        // 通过snatched.seedtime确认做种时间达到要求
        $users = DB::table('snatched')
            ->join('peers', function ($join) {
                $join->on('snatched.torrentid', '=', 'peers.torrent')
                    ->on('snatched.userid', '=', 'peers.userid');
            })
            ->whereIn('snatched.torrentid', $torrentIds)
            ->where('snatched.finished', 'yes')
            ->where('peers.seeder', 'yes')
            ->where('snatched.seedtime', '>=', $minSeedSeconds) // 做种时间必须达到最低要求
            ->select(
                'snatched.userid',
                'snatched.torrentid',
                'snatched.seedtime'
            )
            ->get();

        // 按用户分组统计
        $userData = [];
        foreach ($users as $record) {
            $userId = $record->userid;
            if (!isset($userData[$userId])) {
                $userData[$userId] = [
                    'user_id' => $userId,
                    'torrent_count' => 0,
                    'torrent_ids' => [],
                    'seedtimes' => [], // 记录每个种子的做种时间，用于显示
                ];
            }
            if (!in_array($record->torrentid, $userData[$userId]['torrent_ids'])) {
                $userData[$userId]['torrent_count']++;
                $userData[$userId]['torrent_ids'][] = $record->torrentid;
                $userData[$userId]['seedtimes'][$record->torrentid] = $record->seedtime;
            }
        }

        return array_values($userData);
    }

    /**
     * 显示奖励列表
     */
    protected function displayRewardList(array $usersToReward, int $bonusAmount)
    {
        $headers = ['用户ID', '用户名', '辅种数量', '平均做种时间', '奖励魔力值'];
        $rows = [];

        foreach ($usersToReward as $item) {
            $user = User::find($item['user_id']);
            
            // 计算平均做种时间
            $avgSeedTime = 0;
            if (!empty($item['seedtimes'])) {
                $avgSeedTime = array_sum($item['seedtimes']) / count($item['seedtimes']);
            }
            
            $rows[] = [
                $item['user_id'],
                $user ? $user->username : '未知',
                $item['torrent_count'],
                $this->formatSeedTime($avgSeedTime),
                $item['torrent_count'] * $bonusAmount,
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * 格式化做种时间（秒转为可读格式）
     */
    protected function formatSeedTime($seconds)
    {
        if ($seconds < 3600) {
            return round($seconds / 60) . '分钟';
        } elseif ($seconds < 86400) {
            return round($seconds / 3600, 1) . '小时';
        } else {
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            return $days . '天' . ($hours > 0 ? $hours . '小时' : '');
        }
    }

    /**
     * 发放奖励
     */
    protected function rewardUsers(array $usersToReward, int $bonusAmount, array $torrentIds)
    {
        $successCount = 0;
        $failCount = 0;

        DB::beginTransaction();
        try {
            foreach ($usersToReward as $item) {
                $userId = $item['user_id'];
                $torrentCount = $item['torrent_count'];
                $totalBonus = $torrentCount * $bonusAmount;

                $user = User::find($userId);
                if (!$user) {
                    $this->error("用户 {$userId} 不存在");
                    $failCount++;
                    continue;
                }

                $oldBonus = (float)$user->seedbonus;
                $newBonus = $oldBonus + $totalBonus;

                // 更新用户魔力值
                $user->seedbonus = $newBonus;
                $user->save();

                // 记录日志
                BonusLogs::add(
                    $userId,
                    $oldBonus,
                    $totalBonus,
                    $newBonus,
                    "辅种救活零做种种子奖励（{$torrentCount}个种子）",
                    BonusLogs::BUSINESS_TYPE_RECEIVE_GIFT
                );

                $this->info("✓ 用户 {$user->username} (ID:{$userId}): +{$totalBonus} 魔力值 (辅种{$torrentCount}个)");
                $successCount++;
            }

            // 标记这些种子为已奖励
            DB::table('zero_seeder_torrents')
                ->whereIn('torrent_id', $torrentIds)
                ->update([
                    'rewarded' => 1,
                    'updated_at' => Carbon::now()->toDateTimeString(),
                ]);

            DB::commit();
            $this->info("\n成功奖励 {$successCount} 个用户，失败 {$failCount} 个");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("发放奖励失败: " . $e->getMessage());
            throw $e;
        }
    }
}

