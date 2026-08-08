<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class UserSubscription extends Model
{
    use HasFactory;

    public const PERIOD_MONTHLY = 'monthly';

    public const PERIOD_YEARLY = 'yearly';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_number',
        'user_id',
        'package_id',
        'period',
        'price',
        'started_at',
        'expires_at',
        'ended_at',
        'is_active',
        'is_trial',
        'notes',
        'feature_auto_dues',
        'feature_user_portal',
        'feature_reports',
        'feature_multi_apartment',
        'multi_apartment_limit_override',
        'status',
        'payment_method',
        'receipt_path',
        'receipt_reference',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_active' => 'boolean',
        'is_trial' => 'boolean',
        'feature_auto_dues' => 'boolean',
        'feature_user_portal' => 'boolean',
        'feature_reports' => 'boolean',
        'feature_multi_apartment' => 'boolean',
        'multi_apartment_limit_override' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'subscription_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('price', '>', 0);
    }

    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isCancelled(): bool
    {
        return $this->ended_at !== null && ! $this->is_active;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
