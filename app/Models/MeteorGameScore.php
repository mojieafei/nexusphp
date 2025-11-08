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
    public static function getLeaderboard($type = 'alltime', $limit = 20)
    {
        // 使用子查询：每个用户只取最高分的那条记录
        $subQuery = self::selectRaw('user_id, MAX(score) as max_score')
            ->groupBy('user_id');
        
        // 根据类型筛选时间范围
        if ($type === 'today') {
            $subQuery->whereDate('created_at', today());
        } elseif ($type === '7days') {
            $subQuery->where('created_at', '>=', now()->subDays(7));
        }
        
        // 主查询：关联用户信息并取每个用户的最高分记录
        $query = self::with('user')
            ->joinSub($subQuery, 'max_scores', function ($join) {
                $join->on('meteor_game_scores.user_id', '=', 'max_scores.user_id')
                     ->on('meteor_game_scores.score', '=', 'max_scores.max_score');
            });
        
        // 再次应用时间范围过滤
        if ($type === 'today') {
            $query->whereDate('meteor_game_scores.created_at', today());
        } elseif ($type === '7days') {
            $query->where('meteor_game_scores.created_at', '>=', now()->subDays(7));
        }

        return $query->orderBy('meteor_game_scores.score', 'desc')
            ->orderBy('meteor_game_scores.created_at', 'asc')
            ->limit($limit)
            ->get();
    }
}

