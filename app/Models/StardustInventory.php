<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StardustInventory extends NexusModel
{
    protected $table = 'stardust_inventories';

    protected $fillable = [
        'user_id',
        'item_type',
        'item_id',
        'quantity',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'item_id' => 'integer',
        'quantity' => 'integer',
    ];

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 添加物品
     */
    public static function addItem(int $userId, string $itemType, int $itemId, int $quantity): void
    {
        $inventory = self::firstOrCreate(
            [
                'user_id' => $userId,
                'item_type' => $itemType,
                'item_id' => $itemId,
            ],
            ['quantity' => 0]
        );

        $inventory->quantity += $quantity;
        $inventory->save();
    }

    /**
     * 扣除物品
     */
    public static function removeItem(int $userId, string $itemType, int $itemId, int $quantity): bool
    {
        $inventory = self::where('user_id', $userId)
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->first();

        if (!$inventory || $inventory->quantity < $quantity) {
            return false;
        }

        $inventory->quantity -= $quantity;
        $inventory->save();

        return true;
    }

    /**
     * 获取物品数量
     */
    public static function getItemQuantity(int $userId, string $itemType, int $itemId): int
    {
        $inventory = self::where('user_id', $userId)
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->first();

        return $inventory ? $inventory->quantity : 0;
    }

    /**
     * 获取用户所有碎片
     */
    public static function getUserFragments(int $userId): array
    {
        return self::where('user_id', $userId)
            ->where('item_type', 'fragment')
            ->where('quantity', '>', 0)
            ->get()
            ->keyBy('item_id')
            ->toArray();
    }

    /**
     * 获取用户所有完整行星
     */
    public static function getUserPlanets(int $userId): array
    {
        return self::where('user_id', $userId)
            ->where('item_type', 'planet')
            ->where('quantity', '>', 0)
            ->get()
            ->keyBy('item_id')
            ->toArray();
    }

    /**
     * 合成行星（9个碎片 -> 1个行星）
     */
    public static function craftPlanet(int $userId, int $cropId): bool
    {
        $fragmentCount = self::getItemQuantity($userId, 'fragment', $cropId);
        
        if ($fragmentCount < 9) {
            return false;
        }

        // 扣除9个碎片
        if (!self::removeItem($userId, 'fragment', $cropId, 9)) {
            return false;
        }

        // 增加1个行星
        self::addItem($userId, 'planet', $cropId, 1);

        return true;
    }
}

