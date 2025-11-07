<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPerformanceModeToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 检查字段是否已存在
        if (!Schema::hasColumn('users', 'performance_mode')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('performance_mode', ['default', 'performance'])
                    ->default('default')
                    ->after('fontsize')
                    ->comment('性能模式：default=默认 performance=性能优先');
            });
        }
        
        // 确保所有现有用户都有默认值
        \Illuminate\Support\Facades\DB::statement(
            "UPDATE users SET performance_mode = 'default' WHERE performance_mode IS NULL"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('performance_mode');
        });
    }
}

