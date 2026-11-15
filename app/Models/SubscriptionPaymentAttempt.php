<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPaymentAttempt extends Model
{
    protected $fillable = ['subscription_id', 'provider', 'merchant_oid', 'amount_minor', 'currency', 'is_test', 'status', 'settled_at', 'payer_snapshot'];

    protected $casts = ['amount_minor' => 'integer', 'is_test' => 'boolean', 'settled_at' => 'datetime', 'payer_snapshot' => 'encrypted:array'];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'subscription_id')->withTrashed();
    }
}
