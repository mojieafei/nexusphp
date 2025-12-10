<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'mars_owner_card')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('mars_owner_card')->default(0)->after('attendance_card')->comment('火星老板卡数量');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'mars_owner_card')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('mars_owner_card');
            });
        }
    }
};

