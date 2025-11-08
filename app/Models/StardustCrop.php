<?php

namespace App\Models;

class StardustCrop extends NexusModel
{
    protected $table = 'stardust_crops';

    protected $fillable = [
        'name',
        'name_en',
        'emoji',
        'seed_price',
        'grow_duration',
        'fragment_min',
        'fragment_max',
        'experience',
        'level_required',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'seed_price' => 'integer',
        'grow_duration' => 'integer',
        'fragment_min' => 'integer',
        'fragment_max' => 'integer',
        'experience' => 'integer',
        'level_required' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * 获取所有可用作物
     */
    public static function getAvailableCrops(int $userLevel = 1): array
    {
        // 返回所有激活的作物，前端会根据等级要求判断是否可种植
        return self::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    /**
     * 获取格式化的成长时间
     */
    public function getGrowDurationFormatted(): string
    {
        $minutes = $this->grow_duration;
        
        if ($minutes < 60) {
            return $minutes . '分钟';
        } elseif ($minutes < 1440) {
            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            return $mins > 0 ? "{$hours}小时{$mins}分钟" : "{$hours}小时";
        } else {
            $days = floor($minutes / 1440);
            $hours = floor(($minutes % 1440) / 60);
            return $hours > 0 ? "{$days}天{$hours}小时" : "{$days}天";
        }
    }
}

