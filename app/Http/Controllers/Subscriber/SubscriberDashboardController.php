<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Support\CurrentApartment;
use App\Support\PriceQuote;
use App\Support\SubscriberApartmentOverview;
use Illuminate\Http\Request;

class SubscriberDashboardController extends Controller
{
    public function __invoke(Request $request, CurrentApartment $currentApartment, PriceQuote $prices, SubscriberApartmentOverview $overview)
    {
        $user = auth()->user();

        $apartments = $currentApartment->availableFor($user);
        $currentApartmentModel = $currentApartment->getFor($user);

        if ($currentApartmentModel && ($nextStep = $currentApartmentModel->nextSetupStep())) {
            return redirect()->route('apartments.wizard.'.$nextStep, $currentApartmentModel)
                ->with('status', 'Lütfen apartman kurulumunu tamamlayın.');
        }

        $overview->decorate($apartments, $user, $prices);

        return view('subscriber.dashboard', compact('apartments', 'currentApartmentModel'));
    }
}
