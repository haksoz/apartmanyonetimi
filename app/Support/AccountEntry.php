<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

class AccountEntry
{
    public function __construct(private CurrentApartment $currentApartment) {}

    public function redirect(User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($this->currentApartment->availableFor($user)->count() > 1) {
            return redirect()->route('current-apartment.select');
        }

        if ($user->canPurchaseSubscription()) {
            return redirect()->route('subscriber.dashboard');
        }

        return app(MemberEntry::class)->redirect($user);
    }
}
