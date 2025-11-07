<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateForumTipsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('forum_tips', function (Blueprint $table) {
            $table->id();
            $table->unsignedMediumInteger('from_uid')->comment('打赏者用户ID');
            $table->unsignedMediumInteger('to_uid')->comment('接收者用户ID');
            $table->unsignedInteger('post_id')->comment('帖子ID');
            $table->unsignedMediumInteger('topic_id')->comment('主题ID');
            $table->decimal('amount', 12, 2)->comment('打赏金额（税前）');
            $table->decimal('amount_after_tax', 12, 2)->comment('实际到账金额（税后）');
            $table->string('message', 200)->nullable()->comment('打赏留言');
            $table->timestamps();
            
            $table->index('post_id');
            $table->index('topic_id');
            $table->index('from_uid');
            $table->index('to_uid');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('forum_tips');
    }
}

