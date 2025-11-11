<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class StardustLand extends NexusModel
{
    protected $table = 'stardust_lands';

    protected $fillable = [
        'farm_id',
        'slot_index',
        'crop_id',
        'status',
        'planted_at',
        'mature_at',
        'wither_at',
        'watered_times',
        'watered_by',
        'can_be_stolen',
    ];

    protected $casts = [
        'farm_id' => 'integer',
        'slot_index' => 'integer',
        'crop_id' => 'integer',
        'watered_times' => 'integer',
        'watered_by' => 'array',
        'can_be_stolen' => 'boolean',
        'planted_at' => 'datetime',
        'mature_at' => 'datetime',
        'wither_at' => 'datetime',
    ];

    /**
     * 关联农场
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(StardustFarm::class, 'farm_id');
    }

    /**
     * 关联作物
     */
    public function crop(): BelongsTo
    {
        return $this->belongsTo(StardustCrop::class, 'crop_id');
    }

    /**
     * 种植作物
     */
    public function plant(int $cropId): bool
    {
        if ($this->status !== 'empty') {
            return false;
        }

        $crop = StardustCrop::find($cropId);
        if (!$crop || !$crop->is_active) {
            return false;
        }

        $now = now();
        $this->crop_id = $cropId;
        $this->status = 'growing';
        $this->planted_at = $now;
        $this->mature_at = $now->copy()->addMinutes($crop->grow_duration);
        $this->wither_at = $this->mature_at->copy()->addHours(48); // 成熟后48小时枯萎
        $this->watered_times = 0;
        $this->watered_by = [];
        $this->can_be_stolen = false;
        $this->save();

        return true;
    }

    /**
     * 检查并更新状态
     */
    public function updateStatus(): void
    {
        if ($this->status === 'growing') {
            $now = now();
            
            if ($now->greaterThanOrEqualTo($this->wither_at)) {
                $this->status = 'withered';
                $this->save();
            } elseif ($now->greaterThanOrEqualTo($this->mature_at)) {
                $this->status = 'mature';
                $this->can_be_stolen = true;
                $this->save();
            }
        }
    }

    /**
     * 收获作物
     */
    public function harvest(): ?array
    {
        $this->updateStatus();

        if (!in_array($this->status, ['mature', 'withered'])) {
            return null;
        }

        $crop = $this->crop;
        if (!$crop) {
            return null;
        }

        // 计算收获
        $fragmentCount = 0;
        $experience = 0;

        if ($this->status === 'mature') {
            // 正常收获
            $fragmentCount = rand($crop->fragment_min, $crop->fragment_max);
            $experience = $crop->experience;
            
            // 浇水加成：每次浇水增加10%产量（最多3次）
            if ($this->watered_times > 0) {
                $bonus = min($this->watered_times, 3) * 0.1;
                $fragmentCount = ceil($fragmentCount * (1 + $bonus));
            }
        } else {
            // 枯萎了，只有基础产量
            $fragmentCount = $crop->fragment_min;
            $experience = ceil($crop->experience * 0.5);
        }

        $result = [
            'crop_id' => $this->crop_id,
            'crop_name' => $crop->name,
            'crop_emoji' => $crop->emoji,
            'fragments' => $fragmentCount,
            'experience' => $experience,
            'status' => $this->status,
        ];

        // 清空土地
        $this->crop_id = null;
        $this->status = 'empty';
        $this->planted_at = null;
        $this->mature_at = null;
        $this->wither_at = null;
        $this->watered_times = 0;
        $this->watered_by = [];
        $this->can_be_stolen = false;
        $this->save();

        return $result;
    }

    /**
     * 浇水（好友帮助）
     */
    public function water(int $userId): bool
    {
        if ($this->status !== 'growing') {
            return false;
        }

        // 检查是否已经浇水3次（达到上限）
        if ($this->watered_times >= 3) {
            return false;
        }

        $wateredBy = $this->watered_by ?? [];
        if (in_array($userId, $wateredBy)) {
            return false; // 这个人已经浇过水了
        }

        // 每次浇水减少10%生长时间
        // 计算从现在到成熟还需要多少分钟（剩余时间）
        $now = now();
        if ($this->mature_at <= $now) {
            return false; // 已经成熟了，不需要浇水
        }
        
        $remainingMinutes = $now->diffInMinutes($this->mature_at, false);
        $reduction = ceil($remainingMinutes * 0.1); // 缩短10%
        
        // 减少成熟时间（提前成熟）
        $this->mature_at = $this->mature_at->subMinutes($reduction);

        $wateredBy[] = $userId;
        $this->watered_by = $wateredBy;
        $this->watered_times++;
        $this->save();

        return true;
    }

    /**
     * 偷碎片
     */
    public function steal(int $userId): ?array
    {
        $this->updateStatus();

        if (!$this->can_be_stolen || $this->status !== 'mature') {
            return null;
        }

        $crop = $this->crop;
        if (!$crop) {
            return null;
        }

        // 随机偷1个碎片
        $stolenFragments = 1;

        // 标记为已偷（不能再偷）
        $this->can_be_stolen = false;
        $this->save();

        return [
            'crop_id' => $this->crop_id,
            'crop_name' => $crop->name,
            'crop_emoji' => $crop->emoji,
            'fragments' => $stolenFragments,
        ];
    }

    /**
     * 强制成熟
     */
    public function forceMature(): void
    {
        if ($this->status === 'empty' || !$this->crop_id) {
            return;
        }

        $now = now();
        $this->status = 'mature';
        $this->mature_at = $now;
        $this->wither_at = $now->copy()->addHours(48);
        $this->can_be_stolen = true;
        $this->save();
    }

    /**
     * 获取剩余时间（分钟）
     */
    public function getRemainingMinutes(): int
    {
        if ($this->status !== 'growing' || !$this->mature_at) {
            return 0;
        }

        $remaining = $this->mature_at->diffInMinutes(now(), false);
        return max(0, $remaining);
    }

    /**
     * 获取状态文本
     */
    public function getStatusText(): string
    {
        switch ($this->status) {
            case 'empty':
                return '空地';
            case 'growing':
                $remaining = $this->getRemainingMinutes();
                $hours = floor($remaining / 60);
                $minutes = $remaining % 60;
                return sprintf('生长中 (%dh %dm)', $hours, $minutes);
            case 'mature':
                return '已成熟';
            case 'withered':
                return '已枯萎';
            default:
                return '未知';
        }
    }
}

