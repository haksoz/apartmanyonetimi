<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscription;

class AdminDashboardController extends Controller
{
    public function __invoke()
    {
        $customerCount = User::query()
            ->whereNotIn('role', User::adminRoles())
            ->whereNotNull('activated_at')
            ->where(function ($query) {
                $query->where('role', User::ROLE_MANAGER)
                    ->orWhereHas('apartments', function ($query) {
                        $query->where('apartments.is_active', true)
                            ->where('apartment_user.role', 'owner')
                            ->where('apartment_user.is_active', true);
                    })
                    ->orWhereHas('ownedApartments', fn ($query) => $query->withTrashed())
                    ->orWhereHas('managementPeriods')
                    ->orWhereHas('subscriptions', fn ($query) => $query->withTrashed()->commercial());
            })
            ->count();
        $subscriptionCount = Subscription::count();
        $orders = UserSubscription::query()->commercial()->visibleInOrderList();
        $orderCount = (clone $orders)->count();
        $pendingOrderCount = (clone $orders)->where('status', UserSubscription::STATUS_PENDING)
            ->whereNull('checkout_abandoned_at')->count();
        $activeOrderCount = (clone $orders)->where('status', UserSubscription::STATUS_ACTIVE)->count();
        $cancelledOrderCount = (clone $orders)->where('status', UserSubscription::STATUS_CANCELLED)->count();

        return view('admin.dashboard', compact(
            'customerCount',
            'subscriptionCount',
            'orderCount',
            'pendingOrderCount',
            'activeOrderCount',
            'cancelledOrderCount'
        ));
    }
}
