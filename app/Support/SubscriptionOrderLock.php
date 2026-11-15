<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\UserSubscription;

class SubscriptionOrderLock
{
    // All settlement, cancellation and deletion paths lock apartments before the order.
    public static function acquire(UserSubscription $order): UserSubscription
    {
        $ids = $order->items()->pluck('apartment_id')->filter()->unique()->sort()->values();
        Apartment::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();

        return UserSubscription::withTrashed()->whereKey($order->id)->lockForUpdate()->firstOrFail();
    }
}
