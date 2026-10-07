<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Collection;

class SubscriberApartmentOverview
{
    public function decorate(Collection $apartments, User $user, PriceQuote $prices): Collection
    {
        $coverage = SubscriptionItem::query()
            ->whereIn('apartment_id', $apartments->pluck('id'))
            ->with('subscription.user')
            ->orderByDesc('id')
            ->get()
            ->groupBy('apartment_id');

        $ownerIds = Apartment::query()
            ->whereIn('id', $apartments->pluck('id'))
            ->whereHas('members', function ($query) use ($user) {
                $query->whereKey($user->id)
                    ->where('apartment_user.role', 'owner')
                    ->where('apartment_user.is_active', true);
            })
            ->pluck('id');

        $apartments->each(function (Apartment $apartment) use ($coverage, $prices, $ownerIds) {
            $items = $coverage->get($apartment->id, collect());
            $apartment->setAttribute('commercial_active', $this->activeItem($items));
            $apartment->setAttribute('commercial_pending', $this->pendingItem($items));
            $apartment->setAttribute('commercial_expired', $this->expiredItem($items));
            $apartment->setAttribute('can_purchase', $ownerIds->contains($apartment->id));
            $apartment->setAttribute('monthly_quote', $prices->forUnits((int) $apartment->unit_count, 'monthly', $apartment));
            $apartment->setAttribute('yearly_quote', $prices->forUnits((int) $apartment->unit_count, 'yearly', $apartment));
        });

        return $apartments;
    }

    private function activeItem(Collection $items): ?SubscriptionItem
    {
        return $items->first(fn (SubscriptionItem $item) => $item->isCovering());
    }

    private function pendingItem(Collection $items): ?SubscriptionItem
    {
        return $items->first(function (SubscriptionItem $item) {
            return $item->subscription?->status === UserSubscription::STATUS_PENDING;
        });
    }

    private function expiredItem(Collection $items): ?SubscriptionItem
    {
        return $items->first(function (SubscriptionItem $item) {
            return $item->plan === SubscriptionItem::PLAN_PAID
                && $item->expires_at !== null
                && $item->expires_at->lt(now())
                && $item->status !== SubscriptionItem::STATUS_PENDING;
        });
    }
}
