<?php

namespace App\Http\Controllers;

use App\Support\CurrentApartment;
use Illuminate\Http\Request;

class ApartmentSwitchController extends Controller
{
    public function __invoke(Request $request, CurrentApartment $currentApartment)
    {
        $validated = $request->validate([
            'apartment_id' => ['required', 'integer'],
            'destination' => ['nullable', 'in:dairem,ozet'],
        ]);

        $apartment = $currentApartment->setFor($request->user(), (int) $validated['apartment_id']);
        $user = $request->user();
        $isOwner = $user->isAdmin() || $apartment->members()
            ->whereKey($user->id)
            ->where('apartment_user.role', 'owner')
            ->where('apartment_user.is_active', true)
            ->exists();

        if (($validated['destination'] ?? null) === 'dairem' || ! $isOwner) {
            return redirect()->route('residence.home')->with('status', $apartment->name.' seçildi.');
        }

        if ($nextStep = $apartment->nextSetupStep()) {
            return redirect()->route('apartments.wizard.'.$nextStep, $apartment)
                ->with('status', $apartment->name.' seçildi. Kurulumu tamamlayın.');
        }

        return redirect()->route('dashboard')->with('status', $apartment->name.' seçildi.');
    }
}
