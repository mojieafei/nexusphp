<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('game_tables') && !Schema::hasColumn('game_tables', 'owner_start')) {
            Schema::table('game_tables', function (Blueprint $table) {
                $table->unsignedBigInteger('owner_start')->nullable()->after('owner_id')->comment('老板生效开始时间（13位毫秒时间戳）');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('game_tables') && Schema::hasColumn('game_tables', 'owner_start')) {
            Schema::table('game_tables', function (Blueprint $table) {
                $table->dropColumn('owner_start');
            });
        }
    }
};

