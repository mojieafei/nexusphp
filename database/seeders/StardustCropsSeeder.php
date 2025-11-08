<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StardustCropsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $crops = [
            [
                'name' => '月球',
                'name_en' => 'Moon',
                'emoji' => '🌙',
                'seed_price' => 50,
                'grow_duration' => 120, // 2小时
                'fragment_min' => 1,
                'fragment_max' => 2,
                'experience' => 10,
                'level_required' => 1,
                'sort_order' => 1,
            ],
            [
                'name' => '水星',
                'name_en' => 'Mercury',
                'emoji' => '☿️',
                'seed_price' => 100,
                'grow_duration' => 240, // 4小时
                'fragment_min' => 1,
                'fragment_max' => 2,
                'experience' => 20,
                'level_required' => 1,
                'sort_order' => 2,
            ],
            [
                'name' => '金星',
                'name_en' => 'Venus',
                'emoji' => '♀️',
                'seed_price' => 150,
                'grow_duration' => 360, // 6小时
                'fragment_min' => 1,
                'fragment_max' => 2,
                'experience' => 30,
                'level_required' => 2,
                'sort_order' => 3,
            ],
            [
                'name' => '地球',
                'name_en' => 'Earth',
                'emoji' => '🌍',
                'seed_price' => 200,
                'grow_duration' => 480, // 8小时
                'fragment_min' => 1,
                'fragment_max' => 2,
                'experience' => 40,
                'level_required' => 3,
                'sort_order' => 4,
            ],
            [
                'name' => '火星',
                'name_en' => 'Mars',
                'emoji' => '♂️',
                'seed_price' => 250,
                'grow_duration' => 720, // 12小时
                'fragment_min' => 1,
                'fragment_max' => 2,
                'experience' => 50,
                'level_required' => 4,
                'sort_order' => 5,
            ],
            [
                'name' => '木星',
                'name_en' => 'Jupiter',
                'emoji' => '♃',
                'seed_price' => 400,
                'grow_duration' => 1440, // 24小时
                'fragment_min' => 1,
                'fragment_max' => 3,
                'experience' => 80,
                'level_required' => 5,
                'sort_order' => 6,
            ],
            [
                'name' => '土星',
                'name_en' => 'Saturn',
                'emoji' => '♄',
                'seed_price' => 500,
                'grow_duration' => 2160, // 36小时
                'fragment_min' => 1,
                'fragment_max' => 3,
                'experience' => 100,
                'level_required' => 6,
                'sort_order' => 7,
            ],
            [
                'name' => '天王星',
                'name_en' => 'Uranus',
                'emoji' => '⛢',
                'seed_price' => 600,
                'grow_duration' => 2880, // 48小时
                'fragment_min' => 1,
                'fragment_max' => 3,
                'experience' => 120,
                'level_required' => 7,
                'sort_order' => 8,
            ],
            [
                'name' => '海王星',
                'name_en' => 'Neptune',
                'emoji' => '♆',
                'seed_price' => 700,
                'grow_duration' => 3600, // 60小时
                'fragment_min' => 2,
                'fragment_max' => 4,
                'experience' => 150,
                'level_required' => 8,
                'sort_order' => 9,
            ],
            [
                'name' => '太阳',
                'name_en' => 'Sun',
                'emoji' => '☀️',
                'seed_price' => 2000,
                'grow_duration' => 4320, // 72小时
                'fragment_min' => 2,
                'fragment_max' => 5,
                'experience' => 300,
                'level_required' => 10,
                'sort_order' => 10,
            ],
        ];

        foreach ($crops as $crop) {
            DB::table('stardust_crops')->insert(array_merge($crop, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}

