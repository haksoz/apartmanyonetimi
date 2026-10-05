<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir sipariş başlığının içindeki apartman dönemi.
 *
 * subscription() sipariş başlığıdır. Kalıcı abonelik apartmentSubscription() ilişkisindedir.
 * plan, started_at, expires_at, status ve ended_at bu dönemin kendi tarihleridir.
 * Ücretli özellik kapsamı bu alanlardan okunur. Sipariş başlığının tarihi kapsamı belirlemez.
 * Başlık yalnızca siparişin yürürlükte olup olmadığını doğrular.
 */
class SubscriptionItem extends Model
{
    public const PLAN_FREE = 'free';

    public const PLAN_PAID = 'paid';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'apartment_subscription_id',
        'subscription_id',
        'apartment_id',
        'apartment_name',
        'unit_count',
        'price_band_id',
        'band_label',
        'band_min_units',
        'band_max_units',
        'amount',
        'list_amount',
        'campaign_name',
        'discount_amount',
        'currency',
        'plan',
        'started_at',
        'expires_at',
        'status',
        'ended_at',
    ];

    protected $casts = [
        'unit_count' => 'integer',
        'band_min_units' => 'integer',
        'band_max_units' => 'integer',
        'amount' => 'decimal:2',
        'list_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function scopeCovering(Builder $query): Builder
    {
        return $query
            ->where('plan', self::PLAN_PAID)
            ->where('status', self::STATUS_ACTIVE)
            ->where('started_at', '<=', now())
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->whereHas('subscription', function (Builder $query) {
                $query->where('is_active', true)
                    ->where('status', UserSubscription::STATUS_ACTIVE);
            });
    }

    public function isCovering(): bool
    {
        $subscription = $this->relationLoaded('subscription')
            ? $this->subscription
            : $this->subscription()->first();

        return $this->plan === self::PLAN_PAID
            && $this->status === self::STATUS_ACTIVE
            && $this->started_at !== null
            && ! $this->started_at->gt(now())
            && ($this->expires_at === null || $this->expires_at->gte(now()))
            && $subscription !== null
            && $subscription->is_active
            && $subscription->status === UserSubscription::STATUS_ACTIVE;
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'subscription_id');
    }

    public function apartmentSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'apartment_subscription_id');
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function priceBand(): BelongsTo
    {
        return $this->belongsTo(PriceBand::class);
    }
}
