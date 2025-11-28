<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nexus\Database\NexusDB;

class Role extends Model
{
    protected $connection = NexusDB::ELOQUENT_CONNECTION_NAME;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'icon',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public $timestamps = true;

    // 默认角色名称常量
    const NAME_NORMAL_USER = 'normal_user';
    const NAME_TORRENT_REVIEWER = 'torrent_reviewer';
    const NAME_UPLOADER = 'uploader';
    const NAME_SEEDER = 'seeder';

    /**
     * 获取拥有此角色的用户
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'uid')->withTimestamps();
    }

    /**
     * 获取角色关联的权限
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'role_id');
    }

    /**
     * 获取角色权限列表（字符串数组）
     */
    public function getPermissionListAttribute(): array
    {
        return $this->permissions()->pluck('permission')->toArray();
    }

    /**
     * 设置角色权限
     */
    public function setPermissions(array $permissions): void
    {
        // 删除旧权限
        $this->permissions()->delete();
        
        // 添加新权限
        foreach ($permissions as $permission) {
            RolePermission::create([
                'role_id' => $this->id,
                'permission' => $permission,
            ]);
        }
    }
}

