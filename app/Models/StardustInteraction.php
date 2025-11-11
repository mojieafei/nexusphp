<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StardustInteraction extends NexusModel
{
    protected $table = 'stardust_interactions';

    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'land_id',
        'action',
        'reward',
        'result',
    ];

    protected $casts = [
        'from_user_id' => 'integer',
        'to_user_id' => 'integer',
        'land_id' => 'integer',
        'reward' => 'integer',
    ];

    /**
     * 关联操作用户
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * 关联目标用户
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * 关联土地
     */
    public function land(): BelongsTo
    {
        return $this->belongsTo(StardustLand::class, 'land_id');
    }

    /**
     * 记录互动
     */
    public static function record(
        int $fromUserId,
        int $toUserId,
        string $action,
        ?int $landId = null,
        int $reward = 0,
        ?string $result = null
    ): void {
        self::create([
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'land_id' => $landId,
            'action' => $action,
            'reward' => $reward,
            'result' => $result,
        ]);
    }

    /**
     * 检查今天是否已经对某用户执行过某操作
     */
    public static function hasActionToday(int $fromUserId, int $toUserId, string $action): bool
    {
        return self::where('from_user_id', $fromUserId)
            ->where('to_user_id', $toUserId)
            ->where('action', $action)
            ->whereDate('created_at', today())
            ->exists();
    }

    /**
     * 获取今天的访问次数
     */
    public static function getTodayVisitCount(int $fromUserId): int
    {
        return self::where('from_user_id', $fromUserId)
            ->where('action', 'visit')
            ->whereDate('created_at', today())
            ->count();
    }

    /**
     * 获取今天的偷取次数
     */
    public static function getTodayStealCount(int $fromUserId): int
    {
        return self::where('from_user_id', $fromUserId)
            ->where('action', 'steal')
            ->whereDate('created_at', today())
            ->count();
    }

    /**
     * 检查土地今天是否已被偷取
     */
    public static function hasLandBeenStolenToday(int $landId): bool
    {
        return self::where('land_id', $landId)
            ->where('action', 'steal')
            ->whereDate('created_at', today())
            ->exists();
    }
}

