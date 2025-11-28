<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Nexus\Database\NexusDB;

class RolePermission extends Model
{
    protected $connection = NexusDB::ELOQUENT_CONNECTION_NAME;

    protected $table = 'role_permissions';

    protected $fillable = [
        'role_id',
        'permission',
    ];

    public $timestamps = false;

    /**
     * 获取关联的角色
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}

