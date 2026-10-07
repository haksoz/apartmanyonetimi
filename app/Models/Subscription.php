<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Apartmanın kalıcı aboneliği.
 *
 * Ödeyen bu kayıtta tutulmaz; siparişin user_id alanındadır.
 * Güncel yönetici apartment_user üzerinden okunur.
 * subscription_no bir kez üretilir ve sonraki dönemlerde değişmez.
 */
class Subscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'uuid',
        'subscription_no',
        'apartment_id',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription) {
            if (! $subscription->uuid) {
                $subscription->uuid = (string) Str::uuid();
            }

            if (! $subscription->subscription_no) {
                $subscription->subscription_no = static::nextNumber();
            }
        });
    }

    public static function nextNumber(): string
    {
        $prefix = 'ABN-'.now()->format('y').'-';

        $latest = DB::transaction(function () use ($prefix) {
            return static::query()
                ->where('subscription_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('subscription_no')
                ->value('subscription_no');
        });

        $sequence = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class, 'apartment_subscription_id');
    }

    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Ana listede gösterilecek dönem.
     * Geçerli ücretli dönem, yoksa süresi dolmamış aktif dönem, yoksa bekleyen dönem.
     * İptal edilmiş ve süresi dolmuş dönemler current kabul edilmez.
     */
    public function currentItem(): ?SubscriptionItem
    {
        $items = $this->loadedItems();

        $covering = $items
            ->filter(fn (SubscriptionItem $item) => $item->isCovering())
            ->sortByDesc(fn (SubscriptionItem $item) => $item->started_at?->getTimestamp() ?? 0)
            ->first();

        if ($covering) {
            return $covering;
        }

        $active = $items
            ->filter(fn (SubscriptionItem $item) => $item->status === SubscriptionItem::STATUS_ACTIVE
                && $item->ended_at === null
                && ($item->expires_at === null || ! $item->expires_at->lt(now())))
            ->sortByDesc(fn (SubscriptionItem $item) => sprintf(
                '%d-%010d-%010d',
                $item->plan === SubscriptionItem::PLAN_PAID ? 1 : 0,
                $item->started_at?->getTimestamp() ?? 0,
                $item->id
            ))
            ->first();

        if ($active) {
            return $active;
        }

        return $items
            ->filter(fn (SubscriptionItem $item) => $item->status === SubscriptionItem::STATUS_PENDING && $item->ended_at === null)
            ->sortByDesc(fn (SubscriptionItem $item) => $item->id)
            ->first();
    }

    public function historyItems(): Collection
    {
        return $this->loadedItems()
            ->sortByDesc(fn (SubscriptionItem $item) => sprintf(
                '%010d-%010d',
                $item->started_at?->getTimestamp() ?? 0,
                $item->id
            ))
            ->values();
    }

    private function loadedItems(): Collection
    {
        if ($this->relationLoaded('items')) {
            return $this->items;
        }

        return $this->items()->with('subscription.user')->get();
    }
}
