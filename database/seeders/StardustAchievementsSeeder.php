<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StardustAchievementsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $achievements = [
            [
                'name' => '重建太阳系',
                'name_en' => 'Rebuild Solar System',
                'description' => '集齐9大天体（8大行星+太阳）各1个完整行星',
                'icon' => '🌟',
                'type' => 'collect',
                'conditions' => json_encode([
                    'planets' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], // 所有作物ID
                    'each_count' => 1,
                ]),
                'reward_stardust' => 10000,
                'is_repeatable' => true,
                'sort_order' => 1,
            ],
            [
                'name' => '星际农夫',
                'name_en' => 'Star Farmer',
                'description' => '累计收获100个碎片',
                'icon' => '👨‍🌾',
                'type' => 'collect',
                'conditions' => json_encode([
                    'total_fragments' => 100,
                ]),
                'reward_stardust' => 500,
                'is_repeatable' => false,
                'sort_order' => 2,
            ],
            [
                'name' => '资深农夫',
                'name_en' => 'Veteran Farmer',
                'description' => '累计收获500个碎片',
                'icon' => '🎖️',
                'type' => 'collect',
                'conditions' => json_encode([
                    'total_fragments' => 500,
                ]),
                'reward_stardust' => 2000,
                'is_repeatable' => false,
                'sort_order' => 3,
            ],
            [
                'name' => '好邻居',
                'name_en' => 'Good Neighbor',
                'description' => '帮助好友浇水50次',
                'icon' => '💧',
                'type' => 'interaction',
                'conditions' => json_encode([
                    'water_times' => 50,
                ]),
                'reward_stardust' => 300,
                'is_repeatable' => false,
                'sort_order' => 4,
            ],
            [
                'name' => '神秘访客',
                'name_en' => 'Mysterious Visitor',
                'description' => '访问100个不同的农场',
                'icon' => '👣',
                'type' => 'interaction',
                'conditions' => json_encode([
                    'visit_unique_farms' => 100,
                ]),
                'reward_stardust' => 500,
                'is_repeatable' => false,
                'sort_order' => 5,
            ],
            [
                'name' => '星际大盗',
                'name_en' => 'Star Thief',
                'description' => '累计偷取100个碎片',
                'icon' => '🦹',
                'type' => 'interaction',
                'conditions' => json_encode([
                    'steal_times' => 100,
                ]),
                'reward_stardust' => 800,
                'is_repeatable' => false,
                'sort_order' => 6,
            ],
            [
                'name' => '首次收获',
                'name_en' => 'First Harvest',
                'description' => '完成第一次作物收获',
                'icon' => '🌱',
                'type' => 'special',
                'conditions' => json_encode([
                    'first_harvest' => true,
                ]),
                'reward_stardust' => 50,
                'is_repeatable' => false,
                'sort_order' => 7,
            ],
            [
                'name' => '土地大亨',
                'name_en' => 'Land Tycoon',
                'description' => '拥有12块土地',
                'icon' => '🏆',
                'type' => 'special',
                'conditions' => json_encode([
                    'land_slots' => 12,
                ]),
                'reward_stardust' => 1000,
                'is_repeatable' => false,
                'sort_order' => 8,
            ],
            [
                'name' => '太阳收藏家',
                'name_en' => 'Sun Collector',
                'description' => '拥有10个完整的太阳',
                'icon' => '☀️',
                'type' => 'collect',
                'conditions' => json_encode([
                    'planet_id' => 10, // 太阳
                    'count' => 10,
                ]),
                'reward_stardust' => 5000,
                'is_repeatable' => false,
                'sort_order' => 9,
            ],
            [
                'name' => '财富自由',
                'name_en' => 'Wealthy',
                'description' => '拥有100000星尘',
                'icon' => '💰',
                'type' => 'special',
                'conditions' => json_encode([
                    'stardust' => 100000,
                ]),
                'reward_stardust' => 10000,
                'is_repeatable' => false,
                'sort_order' => 10,
            ],
        ];

        foreach ($achievements as $achievement) {
            DB::table('stardust_achievements')->insert(array_merge($achievement, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}

