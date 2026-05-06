<?php

namespace App\Http\Controllers;

use App\Support\CurrentApartment;

class ApartmentSelectionController extends Controller
{
    public function __invoke(CurrentApartment $currentApartment)
    {
        $apartments = $currentApartment->availableFor(auth()->user());

        if ($apartments->isEmpty()) {
            return redirect()->route('apartments.create');
        }

        return view('apartments.select', compact('apartments'));
    }
}
