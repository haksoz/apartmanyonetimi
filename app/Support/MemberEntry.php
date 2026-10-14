<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

class MemberEntry
{
    public function __construct(private CurrentApartment $currentApartment) {}

    public function redirect(User $user): RedirectResponse
    {
        $apartments = $this->currentApartment->availableFor($user);

        if ($apartments->count() === 1) {
            $this->currentApartment->setFor($user, $apartments->first()->id);

            return redirect()->route('residence.home');
        }

        if ($apartments->isNotEmpty()) {
            return redirect()->route('current-apartment.select');
        }

        return redirect()->route('onboarding.show');
    }
}
