<?php

namespace App\Models;

use Nexus\Database\NexusDB;

class AdminOperationLog extends NexusModel
{
    protected $table = 'admin_operation_logs';

    protected $connection = NexusDB::ELOQUENT_CONNECTION_NAME;

    protected $fillable = [
        'user_id',
        'path',
        'method',
        'action',
        'request_query',
        'request_body',
        'old_values',
        'new_values',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'request_query' => 'array',
        'request_body' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}


