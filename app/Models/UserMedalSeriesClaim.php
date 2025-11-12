<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMedalSeriesClaim extends NexusModel
{
    protected $table = 'user_medal_series_claims';

    protected $fillable = [
        'user_id',
        'series_id',
        'period_key',
        'reward_amount',
        'reward_currency',
        'claimed_at',
    ];

    protected $casts = [
        'reward_amount' => 'float',
        'claimed_at' => 'datetime',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(MedalSeries::class, 'series_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

