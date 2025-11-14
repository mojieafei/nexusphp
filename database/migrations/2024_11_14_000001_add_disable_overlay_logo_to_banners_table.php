<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            if (!Schema::hasColumn('banners', 'disable_overlay_logo')) {
                $table->boolean('disable_overlay_logo')
                    ->default(false)
                    ->after('visible_to');
            }
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            if (Schema::hasColumn('banners', 'disable_overlay_logo')) {
                $table->dropColumn('disable_overlay_logo');
            }
        });
    }
};

