<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $achievements = [
            [
                'name' => '月球守护者',
                'name_en' => 'Moon Keeper',
                'description' => '合成1个完整的月球',
                'icon' => '🌙',
                'type' => 'collect',
                'conditions' => json_encode([
                    'planet_id' => 1,
                    'count' => 1,
                ], JSON_UNESCAPED_UNICODE),
                'reward_stardust' => 300,
                'is_repeatable' => 0,
                'sort_order' => 11,
            ],
            [
                'name' => '木星征服者',
                'name_en' => 'Jupiter Conqueror',
                'description' => '合成1个完整的木星',
                'icon' => '♃',
                'type' => 'collect',
                'conditions' => json_encode([
                    'planet_id' => 6,
                    'count' => 1,
                ], JSON_UNESCAPED_UNICODE),
                'reward_stardust' => 600,
                'is_repeatable' => 0,
                'sort_order' => 12,
            ],
            [
                'name' => '太阳守护者',
                'name_en' => 'Sun Guardian',
                'description' => '合成1个完整的太阳',
                'icon' => '☀️',
                'type' => 'collect',
                'conditions' => json_encode([
                    'planet_id' => 10,
                    'count' => 1,
                ], JSON_UNESCAPED_UNICODE),
                'reward_stardust' => 1200,
                'is_repeatable' => 0,
                'sort_order' => 13,
            ],
            [
                'name' => '互动大师·铜',
                'name_en' => 'Interaction Master Bronze',
                'description' => '累计互动500次',
                'icon' => '🥉',
                'type' => 'interaction',
                'conditions' => json_encode([
                    'interaction_total' => 500,
                ], JSON_UNESCAPED_UNICODE),
                'reward_stardust' => 800,
                'is_repeatable' => 0,
                'sort_order' => 14,
            ],
            [
                'name' => '互动大师·银',
                'name_en' => 'Interaction Master Silver',
                'description' => '累计互动2000次',
                'icon' => '🥈',
                'type' => 'interaction',
                'conditions' => json_encode([
                    'interaction_total' => 2000,
                ], JSON_UNESCAPED_UNICODE),
                'reward_stardust' => 1600,
                'is_repeatable' => 0,
                'sort_order' => 15,
            ],
            [
                'name' => '互动大师·金',
                'name_en' => 'Interaction Master Gold',
                'description' => '累计互动5000次',
                'icon' => '🥇',
                'type' => 'interaction',
                'conditions' => json_encode([
                    'interaction_total' => 5000,
                ], JSON_UNESCAPED_UNICODE),
                'reward_stardust' => 3000,
                'is_repeatable' => 0,
                'sort_order' => 16,
            ],
        ];

        foreach ($achievements as $achievement) {
            $exists = DB::table('stardust_achievements')
                ->where('name', $achievement['name'])
                ->exists();

            if (!$exists) {
                DB::table('stardust_achievements')->insert(array_merge($achievement, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('stardust_achievements')
            ->whereIn('name', [
                '月球守护者',
                '木星征服者',
                '太阳守护者',
                '互动大师·铜',
                '互动大师·银',
                '互动大师·金',
            ])->delete();
    }
};

