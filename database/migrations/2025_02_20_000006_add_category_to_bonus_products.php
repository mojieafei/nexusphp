<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonus_products', function (Blueprint $table) {
            $table->string('category', 50)->default('tool')->after('name')->comment('商品类别，如 upload/download/tool/social/permission');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('bonus_products', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};

