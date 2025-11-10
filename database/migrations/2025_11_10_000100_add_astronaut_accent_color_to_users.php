<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `users` ADD COLUMN `astronaut_accent_color` ENUM('electric','plasma','gold','neon') NOT NULL DEFAULT 'electric' COMMENT '宇航员主题强调色 electric=电光蓝 plasma=等离子紫 gold=星辉金 neon=能量绿' AFTER `performance_mode`"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE `users` DROP COLUMN `astronaut_accent_color`"
        );
    }
};

