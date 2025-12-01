<?php

namespace App\Repositories;

use App\Models\BonusLogs;
use App\Models\Role;
use App\Models\RoleWorkSalary;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoleSalaryRepository extends BaseRepository
{
    /**
     * 按月份结算角色工资
     *
     * @param string|null $month  结算月份，格式：YYYY-MM，默认为当前月份
     * @param int|null    $userId 只结算指定用户，NULL 表示全部
     * @param bool        $isTest 是否为测试模式（只计算不落库）
     *
     * @return array
     */
    public function settle(?string $month = null, ?int $userId = null, bool $isTest = false): array
    {
        $carbonMonth = $this->parseMonth($month);
        $monthKey = $carbonMonth->format('Y-m');

        $result = [
            'month' => $monthKey,
            'is_test' => $isTest,
            'user_id' => $userId,
            'roles' => [],
        ];

        // 为了后续扩展，这里先查出所有相关角色和用户映射，暂不做复杂统计
        $roles = Role::query()
            ->whereIn('name', [
                Role::NAME_UPLOADER,
                Role::NAME_SEEDER,
                Role::NAME_TORRENT_REVIEWER,
                Role::NAME_REUPLOADER,
            ])
            ->get();

        /** @var Collection $roles */
        foreach ($roles as $role) {
            $usersQuery = $role->users()->select(['users.id', 'users.username', 'users.seedbonus', 'users.invites']);
            if ($userId) {
                $usersQuery->where('users.id', $userId);
            }
            $users = $usersQuery->get();

            $result['roles'][$role->name] = [
                'role_id' => $role->id,
                'users' => $users->pluck('id')->all(),
                'settled' => [],
            ];

            // 目前先实现「发布员」的月度工资结算，其它角色后续再补充
            switch ($role->name) {
                case Role::NAME_UPLOADER:
                    $settled = $this->settleUploader($carbonMonth, $role, $users, $isTest);
                    break;
                case Role::NAME_REUPLOADER:
                    $settled = $this->settleReuploader($carbonMonth, $role, $users, $isTest);
                    break;
                case Role::NAME_SEEDER:
                    $settled = $this->settleSeeder($carbonMonth, $role, $users, $isTest);
                    break;
                case Role::NAME_TORRENT_REVIEWER:
                    $settled = $this->settleReviewer($carbonMonth, $role, $users, $isTest);
                    break;
                default:
                    $settled = [];
            }
            $result['roles'][$role->name]['settled'] = $settled;
        }

        return $result;
    }

    protected function parseMonth(?string $month): Carbon
    {
        if ($month) {
            try {
                return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            } catch (\Throwable $e) {
                do_log(sprintf('[RoleSalaryRepository] invalid month format: %s, use current month. error: %s', $month, $e->getMessage()), 'warning');
            }
        }

        return Carbon::now()->startOfMonth();
    }

    /**
     * 发布员（uploader）工资结算
     *
     * 规则（当前实现）：
     * - 统计自然月内，该用户发布的有效种子：
     *   - 表：torrents
     *   - 条件：owner = user_id, visible = 'yes', banned = 'no', added 在当月 [start, end)
     * - 统计结果：
     *   - N = 发种数量
     *   - X = 当月发种总体积（GB）
     * - 达标条件：
     *   - N >= role_salary.uploader.min_torrents（默认 10）
     *   - X >= role_salary.uploader.min_volume_gb（默认 30 GB）
     * - 达标则发放：
     *   - seedbonus += role_salary.uploader.bonus（默认 1000）
     *   - invites  += role_salary.uploader.invites（默认 1）
     * - 幂等：
     *   - 表 role_work_salaries 上 user_id + role_id + month 唯一
     *   - 已存在记录则跳过（但仍在返回结果中标记为 skipped）
     *
     * @param Carbon     $month
     * @param Role       $role
     * @param Collection $users
     * @param bool       $isTest
     *
     * @return array
     */
    protected function settleUploader(Carbon $month, Role $role, Collection $users, bool $isTest): array
    {
        // uploader 和 re_uploader 存在互斥规则：同一个用户在同一个月只能发一份
        $uploaderDecision = $this->buildUploaderReuploaderDecision($month, $users);

        $monthKey = $month->format('Y-m');
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->addMonthNoOverflow()->startOfMonth();

        // 配置项，未配置时使用默认值
        $minTorrents = (int)(Setting::get('role_salary.uploader.min_torrents') ?? 10);
        $minVolumeGb = (float)(Setting::get('role_salary.uploader.min_volume_gb') ?? 30);
        $bonusPerUser = (int)(Setting::get('role_salary.uploader.bonus') ?? 1000);
        $invitesPerUser = (int)(Setting::get('role_salary.uploader.invites') ?? 1);

        $result = [];

        if ($users->isEmpty()) {
            return $result;
        }

        $userIds = $users->pluck('id')->all();

        // 统计当月每个用户的发种数量和总大小
        $stats = Torrent::query()
            ->selectRaw('owner as user_id, COUNT(*) as torrents_count, SUM(size) as total_size_bytes')
            ->whereIn('owner', $userIds)
            ->where('visible', 'yes')
            ->where('banned', 'no')
            ->whereBetween('added', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->groupBy('owner')
            ->get()
            ->keyBy('user_id');

        foreach ($users as $user) {
            /** @var User $user */
            $uid = $user->id;
            $stat = $stats->get($uid);

            $torrentsCount = $stat ? (int)$stat->torrents_count : 0;
            $totalSizeBytes = $stat ? (int)$stat->total_size_bytes : 0;
            $volumeGb = $totalSizeBytes > 0 ? $totalSizeBytes / (1024 * 1024 * 1024) : 0.0;

            $decision = $uploaderDecision[$uid] ?? null;
            $isUploaderPreferred = $decision === Role::NAME_UPLOADER || $decision === null;

            $meetThreshold = $isUploaderPreferred && $torrentsCount >= $minTorrents && $volumeGb >= $minVolumeGb;

            $item = [
                'user_id' => $uid,
                'username' => $user->username,
                'torrents_count' => $torrentsCount,
                'volume_gb' => round($volumeGb, 4),
                'meet_threshold' => $meetThreshold,
                'bonus' => $meetThreshold ? $bonusPerUser : 0,
                'invites' => $meetThreshold ? $invitesPerUser : 0,
                'skipped' => false,
                'reason' => '',
            ];

            if (!$meetThreshold) {
                $item['skipped'] = true;
                $item['reason'] = $isUploaderPreferred ? 'NOT_MEET_THRESHOLD' : 'PREFERS_REUPLOADER';
                $result[] = $item;
                continue;
            }

            // 检查当月是否已经发放过该角色工资（幂等）
            $existing = RoleWorkSalary::query()
                ->where('user_id', $uid)
                ->where('role_id', $role->id)
                ->where('month', $monthKey)
                ->first();

            if ($existing) {
                $item['skipped'] = true;
                $item['reason'] = 'ALREADY_PAID';
                $result[] = $item;
                continue;
            }

            // 测试模式：只返回结果，不落库、不发放
            if ($isTest) {
                $item['reason'] = 'TEST_MODE';
                $result[] = $item;
                continue;
            }

            // 实际发放：包一层事务，保证用户余额和日志表同步
            try {
                DB::transaction(function () use ($uid, $role, $monthKey, $item, $bonusPerUser, $invitesPerUser, $torrentsCount, $volumeGb, $minTorrents, $minVolumeGb) {
                    /** @var User $userForUpdate */
                    $userForUpdate = User::query()->where('id', $uid)->lockForUpdate()->first();
                    if (!$userForUpdate) {
                        throw new \RuntimeException("User not found when settle uploader salary, uid: $uid");
                    }

                    $oldBonus = (float)$userForUpdate->seedbonus;
                    $newBonus = $oldBonus + $bonusPerUser;

                    // 更新用户魔力与邀请
                    $userForUpdate->seedbonus = $newBonus;
                    $userForUpdate->invites = (int)$userForUpdate->invites + $invitesPerUser;
                    $userForUpdate->save();

                    // 记录工资汇总表（保证幂等）
                    RoleWorkSalary::query()->create([
                        'user_id' => $uid,
                        'role_id' => $role->id,
                        'month' => $monthKey,
                        'bonus' => $bonusPerUser,
                        'invites' => $invitesPerUser,
                        'stats' => json_encode([
                            'torrents_count' => $torrentsCount,
                            'volume_gb' => $volumeGb,
                            'thresholds' => [
                                'min_torrents' => $minTorrents,
                                'min_volume_gb' => $minVolumeGb,
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ]);

                    // 记录 BonusLogs
                    $comment = sprintf(
                        'Uploader monthly salary, month=%s, torrents=%d, volume_gb=%.4f',
                        $monthKey,
                        $torrentsCount,
                        $volumeGb
                    );
                    BonusLogs::add(
                        $uid,
                        $oldBonus,
                        $bonusPerUser,
                        $newBonus,
                        $comment,
                        BonusLogs::BUSINESS_TYPE_ROLE_WORK_SALARY
                    );
                });

                $item['reason'] = 'PAID';
            } catch (\Throwable $e) {
                do_log(sprintf(
                    '[RoleSalaryRepository][settleUploader] error when settle, uid=%s, month=%s, error=%s',
                    $uid,
                    $monthKey,
                    $e->getMessage()
                ), 'error');
                $item['skipped'] = true;
                $item['reason'] = 'ERROR: ' . $e->getMessage();
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * 转载员工资结算
     */
    protected function settleReuploader(Carbon $month, Role $role, Collection $users, bool $isTest): array
    {
        $monthKey = $month->format('Y-m');
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->addMonthNoOverflow()->startOfMonth();

        $minTorrents = (int)(Setting::get('role_salary.reuploader.min_valid_torrents') ?? 1);
        $bonusPerTorrent = (int)(Setting::get('role_salary.reuploader.bonus_per_torrent') ?? 1000);
        $invitesPerUser = (int)(Setting::get('role_salary.reuploader.invites') ?? 0);

        $result = [];
        if ($users->isEmpty()) {
            return $result;
        }

        $uploaderDecision = $this->buildUploaderReuploaderDecision($month, $users);

        $userIds = $users->pluck('id')->all();
        $stats = Torrent::query()
            ->selectRaw('owner as user_id, COUNT(*) as torrents_count')
            ->whereIn('owner', $userIds)
            ->where('visible', 'yes')
            ->where('banned', 'no')
            ->whereBetween('added', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->groupBy('owner')
            ->get()
            ->keyBy('user_id');

        foreach ($users as $user) {
            $uid = $user->id;
            $decision = $uploaderDecision[$uid] ?? null;
            $isReuploaderPreferred = $decision === Role::NAME_REUPLOADER;

            $torrentsCount = $stats->get($uid)->torrents_count ?? 0;
            $meetThreshold = $isReuploaderPreferred && $torrentsCount >= $minTorrents;

            $item = [
                'user_id' => $uid,
                'username' => $user->username,
                'torrents_count' => $torrentsCount,
                'meet_threshold' => $meetThreshold,
                'bonus' => $meetThreshold ? $bonusPerTorrent * $torrentsCount : 0,
                'invites' => $meetThreshold ? $invitesPerUser : 0,
                'skipped' => false,
                'reason' => '',
            ];

            if (!$meetThreshold) {
                $item['skipped'] = true;
                if (!$isReuploaderPreferred) {
                    $item['reason'] = $decision === null ? 'PREFERS_UPLOADER_OR_NONE' : 'PREFERS_UPLOADER';
                } else {
                    $item['reason'] = 'NOT_MEET_THRESHOLD';
                }
                $result[] = $item;
                continue;
            }

            $existing = RoleWorkSalary::query()
                ->where('user_id', $uid)
                ->where('role_id', $role->id)
                ->where('month', $monthKey)
                ->first();
            if ($existing) {
                $item['skipped'] = true;
                $item['reason'] = 'ALREADY_PAID';
                $result[] = $item;
                continue;
            }
            if ($isTest) {
                $item['reason'] = 'TEST_MODE';
                $result[] = $item;
                continue;
            }

            $bonusValue = $bonusPerTorrent * $torrentsCount;
            try {
                DB::transaction(function () use ($uid, $role, $monthKey, $invitesPerUser, $torrentsCount, $bonusValue) {
                    $userForUpdate = User::query()->where('id', $uid)->lockForUpdate()->first();
                    if (!$userForUpdate) {
                        throw new \RuntimeException("User not found when settle reuploader salary, uid: $uid");
                    }

                    $oldBonus = (float)$userForUpdate->seedbonus;
                    $newBonus = $oldBonus + $bonusValue;
                    $userForUpdate->seedbonus = $newBonus;
                    $userForUpdate->invites = (int)$userForUpdate->invites + $invitesPerUser;
                    $userForUpdate->save();

                    RoleWorkSalary::query()->create([
                        'user_id' => $uid,
                        'role_id' => $role->id,
                        'month' => $monthKey,
                        'bonus' => $bonusValue,
                        'invites' => $invitesPerUser,
                        'stats' => json_encode([
                            'torrents_count' => $torrentsCount,
                        ], JSON_UNESCAPED_UNICODE),
                    ]);

                    $comment = sprintf(
                        'Reuploader monthly salary, month=%s, torrents=%d',
                        $monthKey,
                        $torrentsCount
                    );
                    BonusLogs::add(
                        $uid,
                        $oldBonus,
                        $bonusValue,
                        $newBonus,
                        $comment,
                        BonusLogs::BUSINESS_TYPE_ROLE_WORK_SALARY
                    );
                });
                $item['reason'] = 'PAID';
            } catch (\Throwable $e) {
                do_log(sprintf(
                    '[RoleSalaryRepository][settleReuploader] error when settle, uid=%s, month=%s, error=%s',
                    $uid,
                    $monthKey,
                    $e->getMessage()
                ), 'error');
                $item['skipped'] = true;
                $item['reason'] = 'ERROR: ' . $e->getMessage();
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * 保种员工资结算
     */
    protected function settleSeeder(Carbon $month, Role $role, Collection $users, bool $isTest): array
    {
        $monthKey = $month->format('Y-m');
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->addMonthNoOverflow()->startOfMonth();

        $baseDays = (int)(Setting::get('role_salary.seeder.base_days') ?? 20);
        $bonusPerTorrent = (int)(Setting::get('role_salary.seeder.bonus_per_torrent') ?? 100);
        $invitesPerUser = (int)(Setting::get('role_salary.seeder.invites') ?? 0);

        $result = [];
        if ($users->isEmpty()) {
            return $result;
        }

        $userIds = $users->pluck('id')->all();

        // 计算每个用户已发放的保种员工资次数
        $paidCounts = RoleWorkSalary::query()
            ->selectRaw('user_id, COUNT(*) as paid_count')
            ->where('role_id', $role->id)
            ->whereIn('user_id', $userIds)
            ->where('month', '<', $monthKey)
            ->groupBy('user_id')
            ->get()
            ->pluck('paid_count', 'user_id');

        // 计算符合条件的保种种子数量
        $requiredSeeds = collect();
        $snatchStats = Snatch::query()
            ->selectRaw('userid as user_id, COUNT(*) as valid_torrents')
            ->whereIn('userid', $userIds)
            ->where('finished', Snatch::FINISHED_YES)
            ->whereBetween('last_action', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->groupBy('userid')
            ->get()
            ->keyBy('user_id');

        foreach ($users as $user) {
            $uid = $user->id;
            $paidCount = (int)$paidCounts->get($uid, 0);
            $level = $paidCount + 1;
            $requiredSeedtimeSeconds = $level * $baseDays * 24 * 3600;

            $validCount = Snatch::query()
                ->where('userid', $uid)
                ->where('finished', Snatch::FINISHED_YES)
                ->whereBetween('last_action', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->where('seedtime', '>=', $requiredSeedtimeSeconds)
                ->count();

            $bonusValue = $validCount * $bonusPerTorrent;

            $item = [
                'user_id' => $uid,
                'username' => $user->username,
                'valid_torrents' => $validCount,
                'level' => $level,
                'seedtime_required_seconds' => $requiredSeedtimeSeconds,
                'bonus' => $bonusValue,
                'invites' => $validCount > 0 ? $invitesPerUser : 0,
                'skipped' => $validCount <= 0,
                'reason' => $validCount > 0 ? '' : 'NOT_MEET_THRESHOLD',
            ];

            if ($validCount <= 0) {
                $result[] = $item;
                continue;
            }

            $existing = RoleWorkSalary::query()
                ->where('user_id', $uid)
                ->where('role_id', $role->id)
                ->where('month', $monthKey)
                ->first();
            if ($existing) {
                $item['skipped'] = true;
                $item['reason'] = 'ALREADY_PAID';
                $result[] = $item;
                continue;
            }
            if ($isTest) {
                $item['reason'] = 'TEST_MODE';
                $result[] = $item;
                continue;
            }

            try {
                DB::transaction(function () use ($uid, $role, $monthKey, $bonusValue, $invitesPerUser, $level, $validCount, $requiredSeedtimeSeconds) {
                    $userForUpdate = User::query()->where('id', $uid)->lockForUpdate()->first();
                    if (!$userForUpdate) {
                        throw new \RuntimeException("User not found when settle seeder salary, uid: $uid");
                    }

                    $oldBonus = (float)$userForUpdate->seedbonus;
                    $newBonus = $oldBonus + $bonusValue;
                    $userForUpdate->seedbonus = $newBonus;
                    $userForUpdate->invites = (int)$userForUpdate->invites + $invitesPerUser;
                    $userForUpdate->save();

                    RoleWorkSalary::query()->create([
                        'user_id' => $uid,
                        'role_id' => $role->id,
                        'month' => $monthKey,
                        'bonus' => $bonusValue,
                        'invites' => $invitesPerUser,
                        'stats' => json_encode([
                            'valid_torrents' => $validCount,
                            'level' => $level,
                            'seedtime_required_seconds' => $requiredSeedtimeSeconds,
                        ], JSON_UNESCAPED_UNICODE),
                    ]);

                    $comment = sprintf(
                        'Seeder monthly salary, month=%s, level=%d, valid_torrents=%d',
                        $monthKey,
                        $level,
                        $validCount
                    );
                    BonusLogs::add(
                        $uid,
                        $oldBonus,
                        $bonusValue,
                        $newBonus,
                        $comment,
                        BonusLogs::BUSINESS_TYPE_ROLE_WORK_SALARY
                    );
                });
                $item['reason'] = 'PAID';
            } catch (\Throwable $e) {
                do_log(sprintf(
                    '[RoleSalaryRepository][settleSeeder] error when settle, uid=%s, month=%s, error=%s',
                    $uid,
                    $monthKey,
                    $e->getMessage()
                ), 'error');
                $item['skipped'] = true;
                $item['reason'] = 'ERROR: ' . $e->getMessage();
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * 种审员工资结算
     */
    protected function settleReviewer(Carbon $month, Role $role, Collection $users, bool $isTest): array
    {
        $monthKey = $month->format('Y-m');
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->addMonthNoOverflow()->startOfMonth();

        $minReviews = (int)(Setting::get('role_salary.reviewer.min_reviews') ?? 60);
        $bonusPerReview = (int)(Setting::get('role_salary.reviewer.bonus_per_review') ?? 200);
        $invitesPerUser = (int)(Setting::get('role_salary.reviewer.invites') ?? 0);

        $result = [];
        if ($users->isEmpty()) {
            return $result;
        }

        $userIds = $users->pluck('id')->all();
        $stats = TorrentOperationLog::query()
            ->selectRaw('uid as user_id, COUNT(*) as reviews_count')
            ->whereIn('uid', $userIds)
            ->whereIn('action_type', [
                TorrentOperationLog::ACTION_TYPE_APPROVAL_ALLOW,
                TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY,
            ])
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('uid')
            ->get()
            ->keyBy('user_id');

        foreach ($users as $user) {
            $uid = $user->id;
            $reviewsCount = $stats->get($uid)->reviews_count ?? 0;
            $meetThreshold = $reviewsCount >= $minReviews;
            $bonusValue = $meetThreshold ? $reviewsCount * $bonusPerReview : 0;

            $item = [
                'user_id' => $uid,
                'username' => $user->username,
                'reviews_count' => $reviewsCount,
                'meet_threshold' => $meetThreshold,
                'bonus' => $bonusValue,
                'invites' => $meetThreshold ? $invitesPerUser : 0,
                'skipped' => !$meetThreshold,
                'reason' => $meetThreshold ? '' : 'NOT_MEET_THRESHOLD',
            ];

            if (!$meetThreshold) {
                $result[] = $item;
                continue;
            }

            $existing = RoleWorkSalary::query()
                ->where('user_id', $uid)
                ->where('role_id', $role->id)
                ->where('month', $monthKey)
                ->first();
            if ($existing) {
                $item['skipped'] = true;
                $item['reason'] = 'ALREADY_PAID';
                $result[] = $item;
                continue;
            }
            if ($isTest) {
                $item['reason'] = 'TEST_MODE';
                $result[] = $item;
                continue;
            }

            try {
                DB::transaction(function () use ($uid, $role, $monthKey, $bonusValue, $invitesPerUser, $reviewsCount) {
                    $userForUpdate = User::query()->where('id', $uid)->lockForUpdate()->first();
                    if (!$userForUpdate) {
                        throw new \RuntimeException("User not found when settle reviewer salary, uid: $uid");
                    }

                    $oldBonus = (float)$userForUpdate->seedbonus;
                    $newBonus = $oldBonus + $bonusValue;
                    $userForUpdate->seedbonus = $newBonus;
                    $userForUpdate->invites = (int)$userForUpdate->invites + $invitesPerUser;
                    $userForUpdate->save();

                    RoleWorkSalary::query()->create([
                        'user_id' => $uid,
                        'role_id' => $role->id,
                        'month' => $monthKey,
                        'bonus' => $bonusValue,
                        'invites' => $invitesPerUser,
                        'stats' => json_encode([
                            'reviews_count' => $reviewsCount,
                        ], JSON_UNESCAPED_UNICODE),
                    ]);

                    $comment = sprintf(
                        'Reviewer monthly salary, month=%s, reviews=%d',
                        $monthKey,
                        $reviewsCount
                    );
                    BonusLogs::add(
                        $uid,
                        $oldBonus,
                        $bonusValue,
                        $newBonus,
                        $comment,
                        BonusLogs::BUSINESS_TYPE_ROLE_WORK_SALARY
                    );
                });
                $item['reason'] = 'PAID';
            } catch (\Throwable $e) {
                do_log(sprintf(
                    '[RoleSalaryRepository][settleReviewer] error when settle, uid=%s, month=%s, error=%s',
                    $uid,
                    $monthKey,
                    $e->getMessage()
                ), 'error');
                $item['skipped'] = true;
                $item['reason'] = 'ERROR: ' . $e->getMessage();
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * 构建上传/转载岗位的优先决策：
     * - ratio >= 0.5 → uploader
     * - 否则 → re_uploader
     * - 无数据 → null
     *
     * @return array<int, string|null>
     */
    protected function buildUploaderReuploaderDecision(Carbon $month, Collection $users): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->addMonthNoOverflow()->startOfMonth();
        $userIds = $users->pluck('id')->all();

        $officialTagId = Setting::get('bonus.official_tag') ?? Tag::DEFAULTS[2]['id'] ?? 3;

        $totals = Torrent::query()
            ->selectRaw('owner as user_id, COUNT(*) as total_count')
            ->whereIn('owner', $userIds)
            ->where('visible', 'yes')
            ->where('banned', 'no')
            ->whereBetween('added', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->groupBy('owner')
            ->get()
            ->keyBy('user_id');

        $officialCounts = Torrent::query()
            ->selectRaw('owner as user_id, COUNT(*) as official_count')
            ->whereIn('owner', $userIds)
            ->where('visible', 'yes')
            ->where('banned', 'no')
            ->whereBetween('added', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereHas('torrent_tags', function ($query) use ($officialTagId) {
                $query->where('tag_id', $officialTagId);
            })
            ->groupBy('owner')
            ->get()
            ->keyBy('user_id');

        $decision = [];
        foreach ($users as $user) {
            $uid = $user->id;
            $total = (int)($totals->get($uid)->total_count ?? 0);
            if ($total <= 0) {
                $decision[$uid] = null;
                continue;
            }
            $official = (int)($officialCounts->get($uid)->official_count ?? 0);
            $ratio = $official / $total;
            if ($ratio >= 0.5) {
                $decision[$uid] = Role::NAME_UPLOADER;
            } else {
                $decision[$uid] = Role::NAME_REUPLOADER;
            }
        }

        return $decision;
    }
}


