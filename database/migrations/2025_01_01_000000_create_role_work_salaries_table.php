<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('role_work_salaries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('role_id')->index();
            // 工资所属月份，格式：YYYY-MM
            $table->string('month', 7)->index();
            // 本次发放的魔力值和邀请数量
            $table->bigInteger('bonus')->default(0);
            $table->integer('invites')->default(0);
            // 统计信息（例如发种数量、体积、阈值等），JSON 字符串
            $table->text('stats')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'month'], 'role_work_salaries_user_role_month_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('role_work_salaries');
    }
};


