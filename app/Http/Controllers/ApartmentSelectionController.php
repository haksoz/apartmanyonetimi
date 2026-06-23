<?php

namespace App\Http\Controllers;

use App\Support\CurrentApartment;

class ApartmentSelectionController extends Controller
{
    public function __invoke(CurrentApartment $currentApartment)
    {
        $apartments = $currentApartment->queryFor(auth()->user())
            ->with('user')
            ->orderBy('name')
            ->get();

        if ($apartments->isEmpty()) {
            return redirect()->route('apartments.create');
        }

        return view('apartments.select', compact('apartments'));
    }
}
