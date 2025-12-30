<?php

namespace App\Models;

use Nexus\Database\NexusDB;

class YearWish extends NexusModel
{
    protected $table = 'year_wishes';
    
    protected $connection = NexusDB::ELOQUENT_CONNECTION_NAME;
    
    public $timestamps = true;
    
    protected $fillable = [
        'user_id',
        'name',
        'wish',
    ];
    
    protected $casts = [
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

