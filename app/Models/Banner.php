<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class Banner extends NexusModel
{
    const RESOURCE_TYPE_IMAGE = 'image';
    const RESOURCE_TYPE_VIDEO = 'video';

    protected $fillable = [
        'title', 
        'resource_url', 
        'resource_type', 
        'jump_url', 
        'start_time', 
        'end_time', 
        'is_active', 
        'visible_to', 
        'sort_order',
        'disable_overlay_logo',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_active' => 'boolean',
        'visible_to' => 'integer',
        'sort_order' => 'integer',
        'disable_overlay_logo' => 'boolean',
    ];

    public $timestamps = true;

    /**
     * 获取当前有效的banners
     * 
     * @param int $userClass 用户等级
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getActiveBanners($userClass = 0)
    {
        $now = Carbon::now();
        
        return self::where('is_active', 1)
            ->where('visible_to', '<=', $userClass)
            ->where(function ($query) use ($now) {
                $query->whereNull('start_time')
                    ->orWhere('start_time', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('end_time')
                    ->orWhere('end_time', '>=', $now);
            })
            ->orderByDesc('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * 判断资源是否为外部URL
     * 
     * @return bool
     */
    public function isExternalUrl(): bool
    {
        return str_starts_with($this->resource_url, 'http://') 
            || str_starts_with($this->resource_url, 'https://');
    }

    /**
     * 获取完整的资源URL
     * 
     * @return string
     */
    public function getFullResourceUrl(): string
    {
        if ($this->isExternalUrl()) {
            return $this->resource_url;
        }
        
        // 相对路径，去掉开头的斜杠
        $path = ltrim($this->resource_url, '/');
        return $path;
    }

    /**
     * 获取资源类型列表
     * 
     * @return array
     */
    public static function getResourceTypes(): array
    {
        return [
            self::RESOURCE_TYPE_IMAGE => '图片',
            self::RESOURCE_TYPE_VIDEO => '视频',
        ];
    }

    /**
     * 获取用户等级列表
     * 
     * @return array
     */
    public static function getUserClassList(): array
    {
        return [
            0 => '游客 (Peasant)',
            1 => '用户 (User)',
            2 => '高级用户 (Power User)',
            3 => '精英用户 (Elite User)',
            4 => '疯狂用户 (Crazy User)',
            5 => '疯狂进阶 (Insane User)',
            6 => '老兵 (Veteran User)',
            7 => '极限用户 (Extreme User)',
            8 => '终极用户 (Ultimate User)',
            9 => 'Nexus Master',
            10 => 'VIP',
            11 => '退休用户 (Retiree)',
            12 => '上传者 (Uploader)',
            13 => '版主 (Moderator)',
            14 => '管理员 (Administrator)',
            15 => '系统管理员 (SysOp)',
            16 => '站长 (Staff Leader)',
        ];
    }
}

