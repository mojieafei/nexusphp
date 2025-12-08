<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('bonus_products')) {
            $hasOldIndex = collect(DB::select("SHOW INDEX FROM bonus_products WHERE Key_name = 'bonus_products_art_unique'"))->isNotEmpty();
            $hasNewIndex = collect(DB::select("SHOW INDEX FROM bonus_products WHERE Key_name = 'bonus_products_art_menge_unique'"))->isNotEmpty();

            Schema::table('bonus_products', function (Blueprint $table) {
                // 这里的闭包内无法直接访问 $hasOldIndex/$hasNewIndex，使用全局变量传递
            });

            // 删除旧的 art 唯一约束（如果存在）
            if ($hasOldIndex) {
                Schema::table('bonus_products', function (Blueprint $table) {
                    $table->dropUnique('bonus_products_art_unique');
                });
            }

            // 添加 art + menge 的组合唯一约束（如果不存在）
            if (!$hasNewIndex) {
                Schema::table('bonus_products', function (Blueprint $table) {
                    $table->unique(['art', 'menge'], 'bonus_products_art_menge_unique');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('bonus_products')) {
            $hasNewIndex = collect(DB::select("SHOW INDEX FROM bonus_products WHERE Key_name = 'bonus_products_art_menge_unique'"))->isNotEmpty();
            $hasOldIndex = collect(DB::select("SHOW INDEX FROM bonus_products WHERE Key_name = 'bonus_products_art_unique'"))->isNotEmpty();

            if ($hasNewIndex) {
                Schema::table('bonus_products', function (Blueprint $table) {
                    $table->dropUnique('bonus_products_art_menge_unique');
                });
            }

            if (!$hasOldIndex) {
                Schema::table('bonus_products', function (Blueprint $table) {
                    $table->unique('art', 'bonus_products_art_unique');
                });
            }
        }
    }
};

