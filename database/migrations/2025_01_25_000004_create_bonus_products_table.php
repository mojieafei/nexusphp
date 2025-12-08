<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bonus_products', function (Blueprint $table) {
            $table->id();
            $table->string('art', 50)->comment('商品类型标识，如：traffic, invite, title等');
            $table->string('name', 200)->comment('商品名称');
            $table->text('description')->nullable()->comment('商品描述');
            $table->decimal('points', 10, 1)->default(0)->comment('需要的魔力值');
            $table->bigInteger('menge')->default(0)->comment('数量（某些商品需要，如流量大小）');
            $table->string('product_type', 50)->default('normal')->comment('商品类型：normal-普通商品, special_permission-特殊权限商品');
            $table->unsignedBigInteger('special_permission_id')->nullable()->comment('关联的特殊权限ID（如果是特殊权限商品）');
            $table->integer('sort_order')->default(0)->comment('排序顺序');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->json('extra_data')->nullable()->comment('额外数据（JSON格式，存储商品特定配置）');
            $table->timestamps();
            
            // 使用 art + menge 的组合作为唯一约束，因为多个商品可能使用相同的 art（如不同大小的流量包）
            $table->unique(['art', 'menge'], 'bonus_products_art_menge_unique');
            $table->index('art');
            $table->index('product_type');
            $table->index('is_active');
            $table->index('sort_order');
            $table->foreign('special_permission_id')->references('id')->on('special_permissions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_products');
    }
};

