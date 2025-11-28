<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('roles')) {
            return;
        }
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('角色标识，如：normal_user, torrent_reviewer, uploader, seeder');
            $table->string('display_name', 100)->comment('显示名称，如：普通用户, 种审员, 发布员, 保种员');
            $table->text('description')->nullable()->comment('角色描述');
            $table->string('icon', 50)->nullable()->comment('角色图标，可以是emoji或图片路径');
            $table->boolean('is_default')->default(false)->comment('是否为默认角色');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('roles');
    }
}

