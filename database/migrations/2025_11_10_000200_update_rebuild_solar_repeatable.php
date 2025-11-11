<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stardust_achievements')
            ->where('name', '重建太阳系')
            ->update(['is_repeatable' => 0]);
    }

    public function down(): void
    {
        DB::table('stardust_achievements')
            ->where('name', '重建太阳系')
            ->update(['is_repeatable' => 1]);
    }
};

