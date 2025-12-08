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
        if (Schema::hasTable('bonus_products')) {
            Schema::table('bonus_products', function (Blueprint $table) {
                // 删除旧的 art 唯一约束
                $table->dropUnique(['art']);
                // 添加 art + menge 的组合唯一约束
                $table->unique(['art', 'menge'], 'bonus_products_art_menge_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('bonus_products')) {
            Schema::table('bonus_products', function (Blueprint $table) {
                // 恢复旧的 art 唯一约束
                $table->dropUnique(['art', 'menge']);
                $table->unique('art');
            });
        }
    }
};

