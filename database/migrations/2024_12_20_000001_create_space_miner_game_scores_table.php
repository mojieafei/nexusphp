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
        Schema::create('space_miner_game_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->integer('score')->default(0)->comment('游戏得分');
            $table->integer('caught')->default(0)->comment('抓取数量');
            $table->integer('level')->default(1)->comment('达到关卡');
            $table->string('ip_address', 45)->nullable()->comment('IP地址');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('score');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('space_miner_game_scores');
    }
};
