<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;

class SubscriberDashboardController extends Controller
{
    public function __invoke(Request $request, CurrentApartment $currentApartment)
    {
        if ($request->boolean('reset')) {
            session()->forget(CurrentApartment::SESSION_KEY);

            return redirect()->route('subscriber.dashboard');
        }


        $user = auth()->user();

        $subscription = $user->subscription?->load('package');

        $apartments = $currentApartment->availableFor($user);
        $currentApartmentModel = $currentApartment->getFor($user);

        $apartmentIds = $apartments->pluck('id')->toArray();

        $recentPayments = collect();
        $upcomingPayment = null;

        if ($apartmentIds) {
            $recentPayments = Payment::query()
                ->whereIn('apartment_id', $apartmentIds)
                ->with('apartment')
                ->latest('payment_date')
                ->limit(10)
                ->get();
        }

        if ($subscription && ! $subscription->isExpired()) {
            $upcomingPayment = $subscription;
        }

        return view('subscriber.dashboard', compact(
            'subscription',
            'apartments',
            'currentApartmentModel',
            'recentPayments',
            'upcomingPayment'
        ));
    }
}
