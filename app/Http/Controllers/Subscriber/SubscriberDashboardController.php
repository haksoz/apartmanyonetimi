<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\UserSubscription;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;

class SubscriberDashboardController extends Controller
{
    public function __invoke(Request $request, CurrentApartment $currentApartment)
    {
        $user = auth()->user();

        $subscription = $user->subscription?->load('package');

        $apartments = $currentApartment->availableFor($user);
        $currentApartmentModel = $currentApartment->getFor($user);

        $apartmentIds = $apartments->pluck('id')->toArray();

        $recentPayments = collect();

        if ($apartmentIds) {
            $recentPayments = Payment::query()
                ->whereIn('apartment_id', $apartmentIds)
                ->with('apartment')
                ->latest('payment_date')
                ->limit(10)
                ->get();
        }

        $upcomingPayment = null;
        $upcomingPaymentState = null;

        if ($subscription && ! $subscription->isExpired()) {
            if ($subscription->expires_at !== null && $subscription->expires_at->lessThanOrEqualTo(now()->addDays(3))) {
                $upcomingPayment = $subscription;
                $upcomingPaymentState = 'due_soon';
            }
        } elseif ($subscription && $subscription->isExpired()) {
            $lastFinished = $user->subscriptions()
                ->where('status', UserSubscription::STATUS_CANCELLED)
                ->whereNotNull('ended_at')
                ->orderByDesc('ended_at')
                ->first();

            $upcomingPayment = $lastFinished ?? $subscription;
            $upcomingPaymentState = 'expired';
        }

        // Check if user is on trial
        $isTrial = $subscription && $subscription->price == 0;
        $fallbackPackage = SystemSetting::getFallbackPackage();

        $packages = Package::where('is_active', true)
            ->where('show_on_website', true)
            ->orderBy('sort_order')
            ->with('features')
            ->get();

        return view('subscriber.dashboard', compact(
            'subscription',
            'apartments',
            'currentApartmentModel',
            'recentPayments',
            'upcomingPayment',
            'upcomingPaymentState',
            'isTrial',
            'fallbackPackage',
            'packages'
        ));
    }
}
