<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $roles = [
            [
                'name' => Role::NAME_NORMAL_USER,
                'display_name' => '普通用户',
                'description' => '默认用户角色，所有用户默认拥有此角色',
                'icon' => '👤',
                'is_default' => true,
                'permissions' => [],
            ],
            [
                'name' => Role::NAME_TORRENT_REVIEWER,
                'display_name' => '种审员',
                'description' => '负责审核种子的角色',
                'icon' => '🔍',
                'is_default' => false,
                'permissions' => [
                    'torrentmanage',
                    'torrent-approval-allow-automatic',
                ],
            ],
            [
                'name' => Role::NAME_UPLOADER,
                'display_name' => '发布员',
                'description' => '负责发布种子的角色',
                'icon' => '✨',
                'is_default' => false,
                'permissions' => [
                    'upload',
                    'uploadspecial',
                ],
            ],
            [
                'name' => Role::NAME_SEEDER,
                'display_name' => '保种员',
                'description' => '负责保种的角色',
                'icon' => '💚',
                'is_default' => false,
                'permissions' => [],
            ],
            [
                'name' => Role::NAME_REUPLOADER,
                'display_name' => '转载员',
                'description' => '负责转载发布种子的角色',
                'icon' => '📥',
                'is_default' => false,
                // 权限与发布员类似，允许上传
                'permissions' => [
                    'upload',
                    'uploadspecial',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);
            
            $role = Role::firstOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );
            
            // 设置权限
            if (!empty($permissions)) {
                $role->setPermissions($permissions);
            }
        }
    }
}

