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

    public const CANCELLED_BY_CUSTOMER = 'customer';

    public const CANCELLED_BY_ADMIN = 'admin';

    protected $fillable = [
        'order_number',
        'user_id',
        'subscription_id',
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
        'cancelled_by',
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

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class, 'subscription_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'subscription_id');
    }

    /**
     * Gerçek satın alma. Yalnızca ücretsiz temel kullanım kalemi taşıyan başlıklar sipariş değildir.
     */
    public function scopeCommercial(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereDoesntHave('items', function (Builder $query) {
                $query->where('plan', SubscriptionItem::PLAN_FREE);
            })->orWhereHas('items', function (Builder $query) {
                $query->where('plan', '!=', SubscriptionItem::PLAN_FREE);
            });
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCovering(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $query) {
                $query->whereNull('started_at')->orWhere('started_at', '<=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isCovering(): bool
    {
        return $this->is_active
            && $this->status === self::STATUS_ACTIVE
            && ($this->started_at === null || ! $this->started_at->isFuture())
            && ($this->expires_at === null || $this->expires_at->isFuture());
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

    public function hasPaymentProof(): bool
    {
        return filled($this->receipt_path) || filled($this->receipt_reference);
    }

    public function hasFeature(string $key): bool
    {
        if (! $this->is_active || $this->isExpired()) {
            return false;
        }

        return match ($key) {
            'auto_dues' => (bool) $this->feature_auto_dues,
            'user_portal' => (bool) $this->feature_user_portal,
            'reports' => (bool) $this->feature_reports,
            'multi_apartment' => (bool) $this->feature_multi_apartment,
            default => false,
        };
    }
}
