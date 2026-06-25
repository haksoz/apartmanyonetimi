<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;

class SubscriberApartmentController extends Controller
{
    public function index(CurrentApartment $currentApartment)
    {
        $apartments = $currentApartment->availableFor(auth()->user())
            ->load('user')
            ->sortBy('name')
            ->values();

        if ($apartments->isEmpty()) {
            return redirect()->route('subscriber.apartments.create');
        }

        return view('subscriber.apartments.index', compact('apartments'));
    }

    public function update(Request $request, CurrentApartment $currentApartment)
    {
        $validated = $request->validate([
            'apartment_id' => ['required', 'integer'],
        ]);

        $apartment = $currentApartment->setFor($request->user(), (int) $validated['apartment_id']);

        return redirect()->route('dashboard')->with('status', $apartment->name.' seçildi.');
    }
}
