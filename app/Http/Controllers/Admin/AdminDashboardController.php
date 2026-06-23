<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Apartment;
use App\Models\Package;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\CurrentApartment;

class AdminDashboardController extends Controller
{
    public function __invoke(CurrentApartment $currentApartment)
    {
        $managerCount = User::where('role', 'manager')->count();
        $apartmentCount = Apartment::count();
        $activeSubscriptionCount = UserSubscription::where('is_active', true)->count();
        $expiredSubscriptionCount = UserSubscription::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();
        $packageCount = Package::where('is_active', true)->count();

        $apartments = $currentApartment->queryFor(auth()->user())
            ->with('user')
            ->orderBy('name')
            ->get();

        return view('admin.dashboard', compact(
            'managerCount',
            'apartmentCount',
            'activeSubscriptionCount',
            'expiredSubscriptionCount',
            'packageCount',
            'apartments'
        ));
    }
}
