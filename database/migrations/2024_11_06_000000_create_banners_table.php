<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBannersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('banners')) {
            return;
        }
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('标题');
            $table->string('resource_url', 500)->comment('资源链接：支持相对路径或完整URL');
            $table->enum('resource_type', ['image', 'video'])->default('image')->comment('资源类型');
            $table->string('jump_url', 500)->nullable()->comment('点击跳转链接');
            $table->dateTime('start_time')->nullable()->comment('展示开始时间');
            $table->dateTime('end_time')->nullable()->comment('展示结束时间');
            $table->boolean('is_active')->default(1)->comment('是否启用');
            $table->tinyInteger('visible_to')->default(0)->comment('可见等级：0-16对应用户等级');
            $table->integer('sort_order')->default(0)->comment('排序权重，数字越大越靠前');
            $table->timestamps();
            
            $table->index(['is_active', 'start_time', 'end_time']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('banners');
    }
}

