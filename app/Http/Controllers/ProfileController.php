<?php

namespace App\Http\Controllers;

use App\Models\LegalAcceptance;
use App\Support\UserApartmentQuota;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();

        $quota = app(UserApartmentQuota::class);
        $limit = $quota->definedLimit($user);
        $managed = $quota->currentCount($user);
        $remainingApartments = null;

        if (! $user->isAdmin() && ! $user->canPurchaseSubscription()) {
            if ($limit === null) {
                $remainingApartments = $user->isSubscriber() ? null : ($managed === 0 ? 1 : 0);
            } else {
                $remainingApartments = max(0, $limit - $managed);
            }
        }

        return view('profile.edit', [
            'user' => $user,
            'remainingApartments' => $remainingApartments,
            'acceptances' => LegalAcceptance::query()
                ->where('user_id', $user->id)
                ->with(['apartment:id,name', 'order:id,order_number'])
                ->orderByDesc('accepted_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?: null,
        ]);

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Ad ve telefon güncellendi.');
    }

    public function openApartment(Request $request)
    {
        $request->session()->put('open_apartment_requested', true);

        return redirect()->route('apartments.create');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Parola güncellendi.');
    }
}
