<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;

class ApartmentSwitchController extends Controller
{
    public function __invoke(Request $request, CurrentApartment $currentApartment)
    {
        $validated = $request->validate([
            'apartment_id' => ['required', 'integer'],
            'destination' => ['nullable', 'in:dairem,ozet'],
            'account_id' => ['nullable', 'integer'],
        ]);

        $apartment = $currentApartment->setFor($request->user(), (int) $validated['apartment_id']);
        $user = $request->user();
        $isOwner = $user->isAdmin() || $apartment->members()
            ->whereKey($user->id)
            ->where('apartment_user.role', 'owner')
            ->where('apartment_user.is_active', true)
            ->exists();

        if (($validated['destination'] ?? null) === 'dairem' || ! $isOwner) {
            if (! empty($validated['account_id'])) {
                $account = Account::where('apartment_id', $apartment->id)
                    ->where('user_id', $user->id)->findOrFail($validated['account_id']);

                return redirect()->route('residence.account', $account);
            }

            return redirect()->route('residence.home')->with('status', $apartment->name.' seçildi.');
        }

        if ($nextStep = $apartment->nextSetupStep()) {
            return redirect()->route('apartments.wizard.'.$nextStep, $apartment)
                ->with('status', $apartment->name.' seçildi. Kurulumu tamamlayın.');
        }

        return redirect()->route('dashboard')->with('status', $apartment->name.' seçildi.');
    }
}
