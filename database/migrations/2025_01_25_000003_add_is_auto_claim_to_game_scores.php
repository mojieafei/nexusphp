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
        // 为流星游戏得分表添加字段
        if (Schema::hasTable('meteor_game_scores')) {
            Schema::table('meteor_game_scores', function (Blueprint $table) {
                $table->boolean('is_auto_claim')->default(false)->after('session_ended_at')->comment('是否为一键获取星尘的记录');
            });
        }
        
        // 为宇宙碎片抓取游戏得分表添加字段
        if (Schema::hasTable('space_miner_game_scores')) {
            Schema::table('space_miner_game_scores', function (Blueprint $table) {
                $table->boolean('is_auto_claim')->default(false)->after('ip_address')->comment('是否为一键获取星尘的记录');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('meteor_game_scores')) {
            Schema::table('meteor_game_scores', function (Blueprint $table) {
                $table->dropColumn('is_auto_claim');
            });
        }
        
        if (Schema::hasTable('space_miner_game_scores')) {
            Schema::table('space_miner_game_scores', function (Blueprint $table) {
                $table->dropColumn('is_auto_claim');
            });
        }
    }
};

