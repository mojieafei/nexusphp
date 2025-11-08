<?php

namespace App\Models;

class MeteorGameScore extends NexusModel
{
    protected $table = 'meteor_game_scores';
    
    public $timestamps = true; // 启用时间戳
    
    protected $fillable = [
        'user_id',
        'score',
        'combo_max',
        'duration',
        'ip_address',
    ];

    protected $casts = [
        'score' => 'integer',
        'combo_max' => 'integer',
        'duration' => 'integer',
    ];
    
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 获取排行榜数据
     * @param string $type today|7days|alltime
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public static function getLeaderboard($type = 'alltime', $limit = 50)
    {
        $query = self::with('user');

        // 根据类型筛选时间范围
        if ($type === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($type === '7days') {
            $query->where('created_at', '>=', now()->subDays(7));
        }

        return $query->orderBy('score', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }
}

