<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('special_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique()->comment('权限代码，如：auto_claim_stardust');
            $table->string('name', 200)->comment('权限名称');
            $table->text('description')->nullable()->comment('权限描述');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();
            
            $table->index('code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('special_permissions');
    }
};

