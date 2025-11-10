<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `users` MODIFY `performance_mode` ENUM('default','performance','minimal') NOT NULL DEFAULT 'default' COMMENT '性能模式：default=默认 performance=性能优先 minimal=极简'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE `users` MODIFY `performance_mode` ENUM('default','performance') NOT NULL DEFAULT 'default' COMMENT '性能模式：default=默认 performance=性能优先'"
        );
    }
};

