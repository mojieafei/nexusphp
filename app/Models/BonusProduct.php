<?php

namespace App\Models;

class BonusProduct extends NexusModel
{
    protected $table = 'bonus_products';
    
    public $timestamps = true;
    
    protected $fillable = [
        'art',
        'name',
        'category',
        'description',
        'points',
        'menge',
        'product_type',
        'special_permission_id',
        'sort_order',
        'is_active',
        'extra_data',
    ];

    protected $casts = [
        'points' => 'decimal:1',
        'menge' => 'integer',
        'is_active' => 'boolean',
        'extra_data' => 'array',
        'sort_order' => 'integer',
    ];

    // 商品类型常量
    const PRODUCT_TYPE_NORMAL = 'normal';
    const PRODUCT_TYPE_SPECIAL_PERMISSION = 'special_permission';

    // 商品分类
    const CATEGORY_UPLOAD = 'upload';
    const CATEGORY_DOWNLOAD = 'download';
    const CATEGORY_TOOL = 'tool';
    const CATEGORY_SOCIAL = 'social';
    const CATEGORY_PERMISSION = 'permission';
    const CATEGORY_OTHER = 'other';

    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_UPLOAD => '上传类',
            self::CATEGORY_DOWNLOAD => '下载类',
            self::CATEGORY_TOOL => '道具类',
            self::CATEGORY_SOCIAL => '互动类',
            self::CATEGORY_PERMISSION => '权限类',
            self::CATEGORY_OTHER => '其他',
        ];
    }

    /**
     * 关联特殊权限
     */
    public function specialPermission()
    {
        return $this->belongsTo(SpecialPermission::class, 'special_permission_id');
    }

    /**
     * 获取所有启用的商品，按排序顺序
     */
    public static function getActiveProducts()
    {
        return self::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }
}

