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
        Schema::create('meteor_game_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->integer('score')->default(0)->comment('游戏得分');
            $table->integer('combo_max')->default(0)->comment('最大连击数');
            $table->integer('duration')->default(60)->comment('游戏时长（秒）');
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
        Schema::dropIfExists('meteor_game_scores');
    }
};

