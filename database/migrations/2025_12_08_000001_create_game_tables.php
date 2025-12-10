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
        Schema::create('game_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('桌子名称');
            $table->unsignedBigInteger('owner_id')->nullable()->comment('老板用户ID，null表示无老板');
            $table->unsignedBigInteger('owner_until')->nullable()->comment('老板到期时间（13位毫秒时间戳）');
            $table->unsignedTinyInteger('owner_rake_percent')->default(1)->comment('老板抽成百分比（1-90）');
            $table->unsignedInteger('bet_amount')->default(10000)->comment('下注默认额（魔力值）');
            $table->timestamps();
            
            $table->index('owner_id');
            $table->index('owner_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_tables');
    }
};

