<?php

namespace App\Support;

use App\Models\User;

class SubscriberMenu
{
    public function showsCommerce(User $user): bool
    {
        if ($user->managesBuilding()) {
            return true;
        }

        if ($user->subscriptions()->commercial()->exists()) {
            return true;
        }

        return $user->billingProfiles()->exists();
    }
}
