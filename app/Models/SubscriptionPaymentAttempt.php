<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPaymentAttempt extends Model
{
    protected $fillable = ['subscription_id', 'provider', 'merchant_oid', 'amount_minor', 'currency', 'is_test', 'status', 'settled_at', 'payer_snapshot', 'submitted_at', 'review_required', 'iframe_token', 'token_expires_at', 'token_failure_code'];

    protected $hidden = ['iframe_token', 'payer_snapshot'];

    protected $casts = ['amount_minor' => 'integer', 'is_test' => 'boolean', 'settled_at' => 'datetime', 'payer_snapshot' => 'encrypted:array', 'submitted_at' => 'datetime', 'review_required' => 'boolean', 'iframe_token' => 'encrypted', 'token_expires_at' => 'datetime'];

    public function notifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubscriptionPaymentNotification::class, 'payment_attempt_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'subscription_id')->withTrashed();
    }
}
