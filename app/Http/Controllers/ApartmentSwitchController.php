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
        ]);

        $apartment = $currentApartment->setFor($request->user(), (int) $validated['apartment_id']);

        $route = $request->user()->isSubscriber() ? 'subscriber.dashboard' : 'dashboard';

        return redirect()->route($route)->with('status', $apartment->name.' seçildi.');
    }
}
