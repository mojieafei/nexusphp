<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meteor_game_scores', function (Blueprint $table) {
            $table->json('telemetry')->nullable()->after('ip_address');
            $table->string('telemetry_hash', 128)->nullable()->after('telemetry');
            $table->boolean('is_flagged')->default(false)->after('telemetry_hash');
            $table->json('flag_reasons')->nullable()->after('is_flagged');
            $table->unsignedSmallInteger('miss_count')->default(0)->after('flag_reasons');
            $table->unsignedSmallInteger('good_catch_count')->default(0)->after('miss_count');
            $table->unsignedSmallInteger('bad_catch_count')->default(0)->after('good_catch_count');
            $table->unsignedMediumInteger('inputs_count')->default(0)->after('bad_catch_count');
            $table->timestamp('session_started_at')->nullable()->after('inputs_count');
            $table->timestamp('session_ended_at')->nullable()->after('session_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('meteor_game_scores', function (Blueprint $table) {
            $table->dropColumn([
                'telemetry',
                'telemetry_hash',
                'is_flagged',
                'flag_reasons',
                'miss_count',
                'good_catch_count',
                'bad_catch_count',
                'inputs_count',
                'session_started_at',
                'session_ended_at',
            ]);
        });
    }
};

