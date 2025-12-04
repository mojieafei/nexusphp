<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateZeroSeederTorrentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('zero_seeder_torrents')) {
            return;
        }
        Schema::create('zero_seeder_torrents', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->unsignedMediumInteger('torrent_id')->unique()->comment('种子ID');
            $table->dateTime('zero_seeder_start_time')->comment('做种人数变为0的开始时间');
            $table->dateTime('last_checked_at')->comment('最后检查时间');
            $table->tinyInteger('rewarded')->default(0)->comment('是否已奖励：0未奖励，1已奖励');
            $table->timestamps();
            
            $table->index(['rewarded', 'zero_seeder_start_time']);
            $table->index('torrent_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('zero_seeder_torrents');
    }
}

