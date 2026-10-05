<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionItem;

class AdminSubscriptionItemController extends Controller
{
    public function show(SubscriptionItem $subscriptionItem)
    {
        $subscriptionItem->load([
            'apartmentSubscription.items.subscription.user',
            'subscription.user',
            'subscription.payments',
            'apartment.members' => function ($query) {
                $query->where('apartment_user.role', 'owner')
                    ->where('apartment_user.is_active', true);
            },
        ]);

        $record = $subscriptionItem->apartmentSubscription;
        $period = $subscriptionItem;

        if ($record) {
            $period = $record->items->first(fn (SubscriptionItem $row) => $row->isCovering())
                ?? $record->items->first(fn (SubscriptionItem $row) => $row->status === SubscriptionItem::STATUS_ACTIVE && $row->ended_at === null)
                ?? $subscriptionItem;
        }

        return view('admin.subscription-items.show', [
            'item' => $subscriptionItem,
            'record' => $record,
            'period' => $period,
        ]);
    }
}
