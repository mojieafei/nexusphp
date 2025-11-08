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
        Schema::create('stardust_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('username')->comment('用户名');
            $table->enum('type', ['fragment', 'planet', 'achievement', 'milestone'])->comment('广播类型');
            $table->text('message')->comment('广播消息');
            $table->json('data')->nullable()->comment('附加数据');
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('created_at');
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stardust_broadcasts');
    }
};

