<?php

namespace App\Models;

use Nexus\Database\NexusDB;

class RoleWorkSalary extends NexusModel
{
    protected $table = 'role_work_salaries';

    protected $connection = NexusDB::ELOQUENT_CONNECTION_NAME;

    protected $fillable = [
        'user_id',
        'role_id',
        'month',
        'bonus',
        'invites',
        'stats',
    ];

    protected $casts = [
        'bonus' => 'integer',
        'invites' => 'integer',
    ];
}


