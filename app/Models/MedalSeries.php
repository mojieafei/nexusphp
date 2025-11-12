<?php

namespace App\Models;

use Carbon\Carbon;
use Nexus\Database\NexusDB;
use App\Models\BonusLogs;
use App\Models\User;
use App\Models\UserMedalSeriesClaim;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class MedalSeries extends NexusModel
{
    protected $table = 'medal_series';

    protected $fillable = [
        'name',
        'slug',
        'cover_image',
        'banner_image',
        'description',
        'reward_title',
        'reward_description',
        'reward_amount',
        'reward_currency',
        'reward_interval_unit',
        'reward_interval_value',
        'reward_start_at',
        'reward_end_at',
        'reward_cooldown_hours',
        'bonus_addition_factor',
        'bonus_addition_description',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'reward_amount' => 'float',
        'reward_interval_value' => 'integer',
        'reward_cooldown_hours' => 'integer',
        'bonus_addition_factor' => 'float',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'reward_start_at' => 'datetime',
        'reward_end_at' => 'datetime',
    ];

    public function medals(): HasMany
    {
        return $this->hasMany(Medal::class, 'series_id');
    }

    public function userClaims(): HasMany
    {
        return $this->hasMany(UserMedalSeriesClaim::class, 'series_id');
    }

    public static function listRewardIntervalOptions(): array
    {
        $mapping = [
            'none' => 'medal-series.reward_interval.none',
            'daily' => 'medal-series.reward_interval.daily',
            'weekly' => 'medal-series.reward_interval.weekly',
            'monthly' => 'medal-series.reward_interval.monthly',
            'yearly' => 'medal-series.reward_interval.yearly',
        ];

        $results = [];
        foreach ($mapping as $key => $translationKey) {
            $results[$key] = self::translateLabel($translationKey);
        }

        return $results;
    }

    public function isRewardActive(): bool
    {
        if (!$this->is_active || $this->reward_amount <= 0) {
            return false;
        }

        $now = Carbon::now();
        if ($this->reward_start_at && $now->lt($this->reward_start_at)) {
            return false;
        }
        if ($this->reward_end_at && $now->gt($this->reward_end_at)) {
            return false;
        }

        return true;
    }

    public function orderedMedals(): Collection
    {
        return $this->medals
            ->sortByDesc('series_position')
            ->sortByDesc('priority')
            ->sortByDesc('id')
            ->values();
    }

    public function hasMedals(): bool
    {
        return $this->medals->isNotEmpty();
    }

    public function currentPeriodKey(?Carbon $now = null): string
    {
        $now = $now ?: Carbon::now();
        return match ($this->reward_interval_unit) {
            'daily' => $now->format('Y-m-d'),
            'weekly' => $now->format('o-W'),
            'monthly' => $now->format('Y-m'),
            'yearly' => $now->format('Y'),
            default => 'once',
        };
    }

    public function hasUserCompleted(Collection $ownedMedalIds): bool
    {
        $requiredIds = $this->medals->pluck('id')->filter()->values();
        if ($requiredIds->isEmpty()) {
            return false;
        }
        return $requiredIds->diff($ownedMedalIds)->isEmpty();
    }

    public function evaluateClaimState(Collection $ownedMedalIds, Collection $seriesClaims, ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now();
        $orderedClaims = $seriesClaims->sortByDesc('claimed_at')->values();
        $latestClaim = $orderedClaims->first();

        $totalMedals = $this->medals->count();
        $ownedCount = $this->medals->pluck('id')->intersect($ownedMedalIds)->count();
        $completed = $totalMedals > 0 && $ownedCount === $totalMedals;

        $periodKey = $this->currentPeriodKey($now);
        $claimsThisPeriod = $seriesClaims->where('period_key', $periodKey);
        $claimsUsed = $claimsThisPeriod->count();
        $remainingClaims = max(0, ($this->reward_interval_value ?: 1) - $claimsUsed);

        $cooldownReadyAt = null;
        $cooldownPassed = true;
        if ($this->reward_cooldown_hours > 0 && $latestClaim) {
            $cooldownReadyAt = Carbon::parse($latestClaim->claimed_at)->addHours($this->reward_cooldown_hours);
            $cooldownPassed = $cooldownReadyAt->lte($now);
        }

        $status = 'ready';
        if (!$this->isRewardActive()) {
            $status = 'inactive';
        } elseif (!$completed) {
            $status = 'not_complete';
        } elseif ($remainingClaims <= 0) {
            $status = 'limit_reached';
        } elseif (!$cooldownPassed) {
            $status = 'cooldown';
        }

        $claimable = $status === 'ready';

        return [
            'status' => $status,
            'claimable' => $claimable,
            'owned_count' => $ownedCount,
            'total_count' => $totalMedals,
            'remaining_claims' => $remainingClaims,
            'period_key' => $periodKey,
            'latest_claim_at' => $latestClaim?->claimed_at,
            'cooldown_ready_at' => $cooldownReadyAt,
        ];
    }

    public function getRewardIntervalLabelAttribute(): string
    {
        $options = self::listRewardIntervalOptions();
        return $options[$this->reward_interval_unit] ?? $this->reward_interval_unit;
    }

    /**
     * @throws \Throwable
     */
    public function claimReward(User $user): UserMedalSeriesClaim
    {
        return NexusDB::transaction(function () use ($user) {
            $ownedMedalIds = $user->valid_medals()
                ->pluck('medals.id')
                ->unique()
                ->values();

            $seriesClaims = $this->userClaims()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->get();

            $state = $this->evaluateClaimState($ownedMedalIds, $seriesClaims);
            if (!$state['claimable']) {
                $reason = __('medal-series.claim_error.' . $state['status']);
                throw new \LogicException($reason);
            }

            if ($this->reward_currency !== 'seedbonus') {
                throw new \LogicException(__('medal-series.claim_error.unsupported_currency'));
            }

            $rewardAmount = (float) $this->reward_amount;
            if ($rewardAmount <= 0) {
                throw new \LogicException(__('medal-series.claim_error.invalid_reward'));
            }

            /** @var User $freshUser */
            $freshUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->id, User::$commonFields);

            $oldValue = (float) $freshUser->seedbonus;
            $newValue = $oldValue + $rewardAmount;

            User::query()->where('id', $freshUser->id)->update(['seedbonus' => $newValue]);

            BonusLogs::add(
                $freshUser->id,
                $oldValue,
                $rewardAmount,
                $newValue,
                __('medal-series.bonus_log', ['series' => $this->name]),
                BonusLogs::BUSINESS_TYPE_MEDAL_SERIES_REWARD
            );

            /** @var UserMedalSeriesClaim $claim */
            $claim = $this->userClaims()->create([
                'user_id' => $freshUser->id,
                'period_key' => $state['period_key'],
                'reward_amount' => $rewardAmount,
                'reward_currency' => $this->reward_currency,
                'claimed_at' => Carbon::now(),
            ]);

            clear_user_cache($freshUser->id);

            return $claim;
        });
    }

    protected static function translateLabel(string $key): string
    {
        if (function_exists('nexus_trans')) {
            return nexus_trans($key);
        }

        if (function_exists('__')) {
            try {
                return __($key);
            } catch (\Throwable) {
                // translator 可能未初始化，忽略异常
            }
        }

        return $key;
    }
}

