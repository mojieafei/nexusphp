<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StardustTransactionLog extends NexusModel
{
    protected $table = 'stardust_transaction_logs';

    protected $fillable = [
        'user_id',
        'type',
        'source',
        'amount',
        'balance_after',
        'description',
        'metadata',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'amount' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

