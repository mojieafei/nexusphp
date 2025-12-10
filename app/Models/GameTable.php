<?php

namespace App\Models;

class GameTable extends NexusModel
{
    protected $table = 'game_tables';

    protected $fillable = [
        'name',
        'owner_id',
        'owner_start',
        'owner_until',
        'owner_rake_percent',
        'bet_amount',
    ];

    protected $casts = [
        'owner_id' => 'integer',
        'owner_start' => 'integer',
        'owner_until' => 'integer',
        'owner_rake_percent' => 'integer',
        'bet_amount' => 'integer',
    ];

    /**
     * 获取有效的老板ID（如果过期则返回1）
     */
    public function getEffectiveOwnerIdAttribute(): int
    {
        if ($this->owner_id && $this->owner_until) {
            $now = (int)(microtime(true) * 1000); // 13位毫秒时间戳
            if ($this->owner_until >= $now) {
                return $this->owner_id;
            }
        }
        return 1; // 平台默认老板
    }

    /**
     * 关联用户（老板）
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}

