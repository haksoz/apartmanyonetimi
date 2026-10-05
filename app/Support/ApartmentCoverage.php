<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\SubscriptionItem;

class ApartmentCoverage
{
    public static function sync(iterable $apartmentIds): void
    {
        foreach (collect($apartmentIds)->filter()->unique() as $apartmentId) {
            $covered = SubscriptionItem::query()
                ->where('apartment_id', $apartmentId)
                ->covering()
                ->exists();

            Apartment::query()->whereKey($apartmentId)->update([
                'billing_plan' => $covered ? 'paid' : 'free',
            ]);
        }
    }
}
