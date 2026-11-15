<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPaymentNotification extends Model
{
    protected $guarded = [];

    protected $casts = [
        'total_amount_minor' => 'integer',
        'payment_amount_minor' => 'integer',
        'reported_test_mode' => 'boolean',
        'deliveries' => 'integer',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPaymentAttempt::class, 'payment_attempt_id');
    }
}
