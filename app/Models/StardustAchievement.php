<?php

namespace App\Models;

class StardustAchievement extends NexusModel
{
    protected $table = 'stardust_achievements';

    protected $fillable = [
        'name',
        'name_en',
        'description',
        'icon',
        'type',
        'conditions',
        'reward_stardust',
        'is_repeatable',
        'sort_order',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_repeatable' => 'boolean',
        'reward_stardust' => 'integer',
        'sort_order' => 'integer',
    ];
}

