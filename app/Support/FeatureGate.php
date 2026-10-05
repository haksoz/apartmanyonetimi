<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\Feature;
use App\Models\SubscriptionItem;
use App\Models\User;

class FeatureGate
{
    public static function allows(?Apartment $apartment, string $key, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user?->isAdmin()) {
            return true;
        }

        $feature = Feature::query()->where('key', $key)->first();

        if (! $feature || $feature->access === Feature::ACCESS_FREE) {
            return $feature !== null;
        }

        if (! $apartment) {
            return false;
        }

        return SubscriptionItem::query()
            ->where('apartment_id', $apartment->id)
            ->covering()
            ->exists();
    }
}
