<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AstronautStyleSeeder extends Seeder
{
    /**
     * 添加宇航员主题到stylesheets表
     *
     * @return void
     */
    public function run()
    {
        // 检查是否已存在
        $exists = DB::table('stylesheets')
            ->where('uri', 'styles/AstronautStyle/')
            ->exists();
        
        if (!$exists) {
            DB::table('stylesheets')->insert([
                'id' => 8,
                'uri' => 'styles/AstronautStyle/',
                'name' => '宇航员 (Astronaut)',
                'addicode' => '',
                'designer' => 'NexusPHP Team',
                'comment' => '探索浩瀚星海 - Space Explorer Theme',
            ]);
            
            $this->command->info('宇航员主题已成功添加！');
        } else {
            $this->command->info('宇航员主题已存在，跳过添加。');
        }
    }
}

