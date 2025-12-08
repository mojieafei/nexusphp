<?php

namespace App\Models;

class SpecialPermission extends NexusModel
{
    protected $table = 'special_permissions';
    
    public $timestamps = true;
    
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * 关联用户（多对多）
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_special_permissions', 'special_permission_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * 权限代码常量
     */
    const CODE_AUTO_CLAIM_STARDUST = 'auto_claim_stardust';

    /**
     * 关联商品（一对多）
     */
    public function bonusProducts()
    {
        return $this->hasMany(BonusProduct::class, 'special_permission_id');
    }

    /**
     * Boot方法，注册模型事件
     */
    protected static function boot()
    {
        parent::boot();

        // 创建或更新特殊权限时，自动创建/更新对应的商品
        static::saved(function ($permission) {
            if ($permission->is_active) {
                // 检查是否已存在商品
                $product = BonusProduct::where('special_permission_id', $permission->id)
                    ->where('product_type', BonusProduct::PRODUCT_TYPE_SPECIAL_PERMISSION)
                    ->first();

                if (!$product) {
                    // 创建新商品
                    BonusProduct::create([
                        'art' => 'buy_special_permission_' . $permission->id,
                        'name' => '购买特殊权限：' . $permission->name,
                        'description' => $permission->description ?? '购买此特殊权限',
                        'points' => 50000, // 默认价格，可在后台修改
                        'menge' => 0,
                        'product_type' => BonusProduct::PRODUCT_TYPE_SPECIAL_PERMISSION,
                        'special_permission_id' => $permission->id,
                        'sort_order' => 100 + $permission->id,
                        'is_active' => true,
                    ]);
                } else {
                    // 更新现有商品
                    $product->update([
                        'name' => '购买特殊权限：' . $permission->name,
                        'description' => $permission->description ?? '购买此特殊权限',
                        'is_active' => true,
                    ]);
                }
            } else {
                // 如果权限被禁用，也禁用对应的商品
                BonusProduct::where('special_permission_id', $permission->id)
                    ->where('product_type', BonusProduct::PRODUCT_TYPE_SPECIAL_PERMISSION)
                    ->update(['is_active' => false]);
            }
        });

        // 删除特殊权限时，删除对应的商品
        static::deleted(function ($permission) {
            BonusProduct::where('special_permission_id', $permission->id)
                ->where('product_type', BonusProduct::PRODUCT_TYPE_SPECIAL_PERMISSION)
                ->delete();
        });
    }
}

