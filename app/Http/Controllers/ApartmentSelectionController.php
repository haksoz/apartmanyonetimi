<?php

namespace App\Http\Controllers;

use App\Support\BuildingPlaces;

class ApartmentSelectionController extends Controller
{
    public function __invoke(BuildingPlaces $places)
    {
        $user = auth()->user();
        $grouped = $places->grouped($user);

        if ($grouped['residences']->isEmpty() && $grouped['managed']->isEmpty()) {
            if ($user->isSubscriber() || $user->isAdmin()) {
                return redirect()->route('apartments.create');
            }

            return redirect()->route('onboarding.show');
        }

        return view('apartments.select', [
            'residences' => $grouped['residences'],
            'managed' => $grouped['managed'],
        ]);
    }
}
