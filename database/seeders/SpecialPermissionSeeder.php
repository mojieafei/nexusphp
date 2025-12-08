<?php

namespace Database\Seeders;

use App\Models\SpecialPermission;
use Illuminate\Database\Seeder;

class SpecialPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 创建一键获取星尘权限
        SpecialPermission::firstOrCreate(
            ['code' => SpecialPermission::CODE_AUTO_CLAIM_STARDUST],
            [
                'name' => '一键获取星尘',
                'description' => '允许用户使用一键获取今日星尘功能，自动获取游戏星尘奖励',
                'is_active' => true,
            ]
        );
    }
}

