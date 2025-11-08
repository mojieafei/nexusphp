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
        // 1. 用户农场信息表
        Schema::create('stardust_farms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->comment('用户ID');
            $table->unsignedBigInteger('stardust')->default(0)->comment('星尘数量');
            $table->unsignedInteger('level')->default(1)->comment('农场等级');
            $table->unsignedBigInteger('experience')->default(0)->comment('经验值');
            $table->unsignedInteger('land_slots')->default(3)->comment('土地数量');
            $table->timestamp('last_visit_reward_at')->nullable()->comment('最后一次访问好友奖励时间');
            $table->timestamps();
            
            $table->index('user_id');
        });

        // 2. 土地状态表
        Schema::create('stardust_lands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('farm_id')->comment('农场ID');
            $table->unsignedInteger('slot_index')->comment('土地序号 0-11');
            $table->unsignedBigInteger('crop_id')->nullable()->comment('作物配置ID');
            $table->enum('status', ['empty', 'growing', 'mature', 'withered'])->default('empty')->comment('状态');
            $table->timestamp('planted_at')->nullable()->comment('种植时间');
            $table->timestamp('mature_at')->nullable()->comment('成熟时间');
            $table->timestamp('wither_at')->nullable()->comment('枯萎时间');
            $table->unsignedInteger('watered_times')->default(0)->comment('被浇水次数');
            $table->json('watered_by')->nullable()->comment('浇水用户ID列表');
            $table->boolean('can_be_stolen')->default(false)->comment('是否可被偷');
            $table->timestamps();
            
            $table->index('farm_id');
            $table->unique(['farm_id', 'slot_index']);
        });

        // 3. 作物配置表（预设数据）
        Schema::create('stardust_crops', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('作物名称');
            $table->string('name_en', 50)->comment('英文名称');
            $table->string('emoji', 10)->comment('表情符号');
            $table->unsignedInteger('seed_price')->comment('种子价格（星尘）');
            $table->unsignedInteger('grow_duration')->comment('成熟时间（分钟）');
            $table->unsignedInteger('fragment_min')->comment('最小碎片产出');
            $table->unsignedInteger('fragment_max')->comment('最大碎片产出');
            $table->unsignedInteger('experience')->default(0)->comment('收获经验值');
            $table->unsignedInteger('level_required')->default(1)->comment('需要等级');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();
        });

        // 4. 用户背包表（碎片和物品）
        Schema::create('stardust_inventories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->enum('item_type', ['fragment', 'planet', 'decoration'])->comment('物品类型');
            $table->unsignedBigInteger('item_id')->comment('物品ID（作物ID或装饰ID）');
            $table->unsignedInteger('quantity')->default(0)->comment('数量');
            $table->timestamps();
            
            $table->index('user_id');
            $table->unique(['user_id', 'item_type', 'item_id']);
        });

        // 5. 互动记录表
        Schema::create('stardust_interactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_user_id')->comment('操作用户ID');
            $table->unsignedBigInteger('to_user_id')->comment('目标用户ID');
            $table->unsignedBigInteger('land_id')->nullable()->comment('土地ID');
            $table->enum('action', ['water', 'steal', 'visit'])->comment('操作类型');
            $table->unsignedInteger('reward')->default(0)->comment('奖励数量');
            $table->string('result', 100)->nullable()->comment('结果描述');
            $table->timestamps();
            
            $table->index(['from_user_id', 'created_at']);
            $table->index(['to_user_id', 'created_at']);
        });

        // 6. 成就表
        Schema::create('stardust_achievements', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('成就名称');
            $table->string('name_en', 100)->comment('英文名称');
            $table->string('description', 255)->comment('成就描述');
            $table->string('icon', 10)->comment('图标emoji');
            $table->enum('type', ['collect', 'interaction', 'special'])->comment('成就类型');
            $table->json('conditions')->comment('完成条件JSON');
            $table->unsignedInteger('reward_stardust')->default(0)->comment('奖励星尘');
            $table->boolean('is_repeatable')->default(false)->comment('是否可重复');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序');
            $table->timestamps();
        });

        // 7. 用户成就记录表
        Schema::create('stardust_user_achievements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('achievement_id')->comment('成就ID');
            $table->unsignedInteger('completed_times')->default(1)->comment('完成次数');
            $table->json('progress')->nullable()->comment('进度JSON');
            $table->timestamp('first_completed_at')->comment('首次完成时间');
            $table->timestamp('last_completed_at')->comment('最后完成时间');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index(['achievement_id', 'completed_times']);
        });

        // 8. 星尘交易日志表
        Schema::create('stardust_transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->enum('type', ['earn', 'spend'])->comment('类型：获得/消费');
            $table->string('source', 50)->comment('来源：game/harvest/steal/visit/purchase');
            $table->integer('amount')->comment('数量（正数或负数）');
            $table->unsignedBigInteger('balance_after')->comment('操作后余额');
            $table->string('description', 255)->nullable()->comment('描述');
            $table->json('metadata')->nullable()->comment('额外数据');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index('source');
        });

        // 9. 排行榜缓存表
        Schema::create('stardust_leaderboards', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['wealth', 'level', 'planets', 'steal'])->comment('排行榜类型');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('value')->comment('排名值');
            $table->unsignedInteger('rank')->default(0)->comment('排名');
            $table->date('period_date')->comment('统计日期');
            $table->timestamps();
            
            $table->index(['type', 'period_date', 'rank']);
            $table->unique(['type', 'user_id', 'period_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stardust_leaderboards');
        Schema::dropIfExists('stardust_transaction_logs');
        Schema::dropIfExists('stardust_user_achievements');
        Schema::dropIfExists('stardust_achievements');
        Schema::dropIfExists('stardust_interactions');
        Schema::dropIfExists('stardust_inventories');
        Schema::dropIfExists('stardust_crops');
        Schema::dropIfExists('stardust_lands');
        Schema::dropIfExists('stardust_farms');
    }
};

