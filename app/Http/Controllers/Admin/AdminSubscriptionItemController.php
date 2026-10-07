<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionItem;

class AdminSubscriptionItemController extends Controller
{
    public function show(SubscriptionItem $subscriptionItem)
    {
        $subscriptionItem->load([
            'apartmentSubscription.items.subscription.user',
            'apartmentSubscription.items.subscription.payments',
            'apartmentSubscription.items.apartment',
            'apartmentSubscription.apartment.members' => function ($query) {
                $query->where('apartment_user.role', 'owner')
                    ->where('apartment_user.is_active', true);
            },
            'subscription.user',
            'subscription.payments',
            'apartment.members' => function ($query) {
                $query->where('apartment_user.role', 'owner')
                    ->where('apartment_user.is_active', true);
            },
        ]);

        $record = $subscriptionItem->apartmentSubscription;

        return view('admin.subscription-items.show', $this->viewData($record, $subscriptionItem));
    }

    public function subscription(Subscription $subscription)
    {
        $subscription->load([
            'apartment.members' => function ($query) {
                $query->where('apartment_user.role', 'owner')
                    ->where('apartment_user.is_active', true);
            },
            'items.subscription.user',
            'items.subscription.payments',
            'items.apartment',
        ]);

        return view('admin.subscription-items.show', $this->viewData($subscription, $subscription->currentItem()));
    }

    private function viewData(?Subscription $record, ?SubscriptionItem $opened): array
    {
        $period = $record?->currentItem() ?? $opened;

        return [
            'item' => $opened ?? $period,
            'record' => $record,
            'period' => $period,
            'history' => $record ? $record->historyItems() : collect(),
        ];
    }
}
