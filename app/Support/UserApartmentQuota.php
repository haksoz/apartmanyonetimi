<?php

namespace App\Support;

use App\Models\User;

class UserApartmentQuota
{
    public function maxFor(User $user): ?int
    {
        $override = $user->quotaOverride;

        if ($override) {
            return $override->max_apartments;
        }

        $subscription = $user->subscription;

        if (! $subscription || $subscription->isExpired()) {
            return 0;
        }

        return $subscription->package->apartment_limit;
    }

    public function currentCount(User $user): int
    {
        return \App\Models\Apartment::where('user_id', $user->id)
            ->where('is_active', true)
            ->count();
    }

    public function remaining(User $user): int
    {
        $max = $this->maxFor($user);

        if ($max === null) {
            return PHP_INT_MAX;
        }

        return max(0, $max - $this->currentCount($user));
    }

    public function canCreate(User $user): bool
    {
        $max = $this->maxFor($user);

        if ($max === null) {
            return true;
        }

        return $this->currentCount($user) < $max;
    }
}
