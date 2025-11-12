<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medal_series', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->string('cover_image')->nullable();
            $table->string('banner_image')->nullable();
            $table->text('description')->nullable();
            $table->string('reward_title')->nullable();
            $table->text('reward_description')->nullable();
            $table->decimal('reward_amount', 20, 2)->default(0)->comment('领取奖励数值，默认单位为魔力');
            $table->string('reward_currency')->default('seedbonus')->comment('奖励类型，默认魔力');
            $table->enum('reward_interval_unit', ['none', 'daily', 'weekly', 'monthly', 'yearly'])->default('none');
            $table->unsignedInteger('reward_interval_value')->default(1)->comment('每个周期可领取次数，默认 1');
            $table->timestamp('reward_start_at')->nullable();
            $table->timestamp('reward_end_at')->nullable();
            $table->unsignedInteger('reward_cooldown_hours')->default(0)->comment('领取后的冷却时间，小时');
            $table->decimal('bonus_addition_factor', 8, 5)->default(0)->comment('集齐系列额外魔力加成系数');
            $table->text('bonus_addition_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
        });

        Schema::create('user_medal_series_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('series_id');
            $table->string('period_key')->nullable()->comment('领取周期标识，例如 2025-11');
            $table->decimal('reward_amount', 20, 2)->default(0);
            $table->string('reward_currency')->default('seedbonus');
            $table->timestamp('claimed_at');
            $table->timestamps();

            $table->unique(['user_id', 'series_id', 'period_key'], 'user_series_period_unique');
            $table->index(['user_id', 'series_id']);
            $table->foreign('series_id')->references('id')->on('medal_series')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('medals', function (Blueprint $table) {
            if (!Schema::hasColumn('medals', 'series_id')) {
                $table->unsignedBigInteger('series_id')->nullable()->after('id');
                $table->unsignedInteger('series_position')->default(0)->after('series_id');
                $table->foreign('series_id')->references('id')->on('medal_series')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('medals', function (Blueprint $table) {
            if (Schema::hasColumn('medals', 'series_id')) {
                $table->dropForeign(['series_id']);
                $table->dropColumn(['series_id', 'series_position']);
            }
        });

        Schema::dropIfExists('user_medal_series_claims');
        Schema::dropIfExists('medal_series');
    }
};

