<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

class AccountEntry
{
    public function redirect(User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if (session('open_apartment_requested')) {
            $quota = app(UserApartmentQuota::class);

            if ($quota->canCreate($user) || $quota->hasRoomForManagement($user)) {
                return redirect()->route('apartments.create');
            }

            session()->forget('open_apartment_requested');

            return redirect()
                ->route('profile.edit')
                ->with('status', UserApartmentQuota::CONTACT_MESSAGE);
        }

        return redirect()->route('subscriber.dashboard');
    }
}
