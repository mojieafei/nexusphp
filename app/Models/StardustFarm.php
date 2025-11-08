<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StardustFarm extends NexusModel
{
    protected $table = 'stardust_farms';

    protected $fillable = [
        'user_id',
        'stardust',
        'level',
        'experience',
        'land_slots',
        'last_visit_reward_at',
    ];

    protected $casts = [
        'stardust' => 'integer',
        'level' => 'integer',
        'experience' => 'integer',
        'land_slots' => 'integer',
        'last_visit_reward_at' => 'datetime',
    ];

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联土地
     */
    public function lands(): HasMany
    {
        return $this->hasMany(StardustLand::class, 'farm_id');
    }

    /**
     * 获取或创建用户农场
     */
    public static function getOrCreateForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'stardust' => 100, // 初始星尘
                'level' => 1,
                'experience' => 0,
                'land_slots' => 3,
            ]
        );
    }

    /**
     * 增加星尘
     */
    public function addStardust(int $amount, string $source, string $description = null): void
    {
        $this->stardust += $amount;
        $this->save();

        // 记录日志
        StardustTransactionLog::create([
            'user_id' => $this->user_id,
            'type' => $amount > 0 ? 'earn' : 'spend',
            'source' => $source,
            'amount' => $amount,
            'balance_after' => $this->stardust,
            'description' => $description,
        ]);
    }

    /**
     * 扣除星尘（检查余额）
     */
    public function spendStardust(int $amount, string $source, string $description = null): bool
    {
        if ($this->stardust < $amount) {
            return false;
        }

        $this->addStardust(-$amount, $source, $description);
        return true;
    }

    /**
     * 增加经验
     */
    public function addExperience(int $amount): void
    {
        $oldLevel = $this->level;
        $this->experience += $amount;

        // 升级逻辑：每级需要 level * 100 经验
        while ($this->experience >= $this->getNextLevelExp()) {
            $this->level++;
            $this->experience -= $this->getNextLevelExp();
        }

        if ($this->level > $oldLevel) {
            // 升级奖励
            $this->addStardust($this->level * 50, 'level_up', "升级到 {$this->level} 级");
        }

        $this->save();
    }

    /**
     * 获取下一级所需经验
     */
    public function getNextLevelExp(): int
    {
        return $this->level * 100;
    }

    /**
     * 初始化土地
     */
    public function initializeLands(): void
    {
        for ($i = 0; $i < $this->land_slots; $i++) {
            StardustLand::firstOrCreate([
                'farm_id' => $this->id,
                'slot_index' => $i,
            ], [
                'status' => 'empty',
            ]);
        }
    }

    /**
     * 购买土地
     */
    public function purchaseLand(): bool
    {
        if ($this->land_slots >= 12) {
            return false; // 已达上限
        }

        // 价格递增：第4块=500, 第5块=700, 第6块=900, 之后每块+200
        // 初始3块免费，从第4块开始收费
        $nextSlot = $this->land_slots + 1;
        if ($nextSlot <= 3) {
            $cost = 0; // 前3块免费
        } elseif ($nextSlot == 4) {
            $cost = 500;
        } elseif ($nextSlot == 5) {
            $cost = 700;
        } elseif ($nextSlot == 6) {
            $cost = 900;
        } else {
            // 第7块起：900 + (n-6)*200
            $cost = 900 + ($nextSlot - 6) * 200;
        }
        
        if ($cost > 0 && !$this->spendStardust($cost, 'purchase_land', '购买土地')) {
            return false;
        }

        $this->land_slots++;
        $this->save();

        // 创建新土地
        StardustLand::create([
            'farm_id' => $this->id,
            'slot_index' => $this->land_slots - 1,
            'status' => 'empty',
        ]);

        return true;
    }
    
    /**
     * 获取下一块土地的价格
     */
    public function getNextLandPrice(): int
    {
        $nextSlot = $this->land_slots + 1;
        
        if ($nextSlot > 12) {
            return 0; // 已达上限
        }
        
        if ($nextSlot <= 3) {
            return 0; // 前3块免费
        } elseif ($nextSlot == 4) {
            return 500;
        } elseif ($nextSlot == 5) {
            return 700;
        } elseif ($nextSlot == 6) {
            return 900;
        } else {
            return 900 + ($nextSlot - 6) * 200;
        }
    }
}

