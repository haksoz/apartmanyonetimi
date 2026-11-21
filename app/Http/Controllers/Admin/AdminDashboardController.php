<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\SubscriptionItem;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request)
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

        // Dönem tarihleri sipariş başlığından bağımsızdır. Yenilenmiş veya
        // ileri tarihli ücretli dönemi olan abonelikleri kayıp sayma.
        $paidPeriod = fn ($query) => $query->where('plan', SubscriptionItem::PLAN_PAID)
            ->where('status', SubscriptionItem::STATUS_ACTIVE)
            ->whereNull('ended_at')
            ->whereHas('subscription', fn ($order) => $order->where('status', UserSubscription::STATUS_ACTIVE));
        $followUps = [];
        $windows = [
            'monthly_3' => [0, 3, UserSubscription::PERIOD_MONTHLY],
            'monthly_7' => [0, 7, UserSubscription::PERIOD_MONTHLY],
            'yearly_7' => [0, 7, UserSubscription::PERIOD_YEARLY],
            'yearly_30' => [7, 30, UserSubscription::PERIOD_YEARLY],
            'expired_7' => [-7, 0, null],
            'expired_30' => [-30, -7, null],
            'dormant' => [null, -30, null],
        ];
        foreach ($windows as $key => [$from, $to, $period]) {
            $cutoff = now()->addDays($to);
            $query = Subscription::query()
                ->whereHas('items', function ($query) use ($paidPeriod, $cutoff, $from, $period) {
                    $paidPeriod($query)->where('started_at', '<=', now())
                        ->whereNotNull('expires_at')->where('expires_at', '<=', $cutoff);
                    // Dönem türü geçmişte değişmişse yalnızca son ücretli bitişi sınıflandır.
                    $query->whereNotExists(function ($later) {
                        $later->selectRaw('1')->from('subscription_items as later')
                            ->join('user_subscriptions as later_order', 'later_order.id', '=', 'later.subscription_id')
                            ->whereColumn('later.apartment_subscription_id', 'subscription_items.apartment_subscription_id')
                            ->whereColumn('later.expires_at', '>', 'subscription_items.expires_at')
                            ->where('later.plan', SubscriptionItem::PLAN_PAID)
                            ->where('later.status', SubscriptionItem::STATUS_ACTIVE)
                            ->whereNull('later.ended_at')->whereNull('later_order.deleted_at')
                            ->where('later_order.status', UserSubscription::STATUS_ACTIVE);
                    });
                    if ($from !== null) {
                        $query->where('expires_at', '>', now()->addDays($from));
                    }
                    if ($period !== null) {
                        $query->whereHas('subscription', fn ($order) => $order->where('period', $period));
                    }
                })
                ->whereDoesntHave('items', function ($query) use ($paidPeriod, $cutoff) {
                    $paidPeriod($query)->where(fn ($query) => $query
                        ->whereNull('expires_at')->orWhere('expires_at', '>', $cutoff));
                });
            $followUps[$key] = $query;
        }
        $followUpCounts = collect($followUps)->map(fn ($query) => (clone $query)->count());
        $requestedSegment = $request->query('follow_up', 'monthly_3');
        $segment = is_string($requestedSegment) && array_key_exists($requestedSegment, $followUps)
            ? $requestedSegment : 'monthly_3';
        $attentionSubscriptions = (clone $followUps[$segment])
            ->with('apartment')
            ->withMax(['items as paid_until' => $paidPeriod], 'expires_at')
            ->orderBy('paid_until')->orderBy('id')->paginate(10)->withQueryString();

        $pendingOrders = (clone $orders)->where('status', UserSubscription::STATUS_PENDING)->whereNull('checkout_abandoned_at');
        $staleOrderCount = (clone $pendingOrders)->where('created_at', '<=', now()->subHours(48))->count();
        $pendingOrderAmount = (clone $pendingOrders)->sum('price');
        $reviewOrderCount = (clone $orders)->whereHas('paymentAttempts', fn ($query) => $query->where('review_required', true))->count();
        $attentionOrders = (clone $orders)->with('user')->where(function ($query) {
            $query->where(fn ($query) => $query->where('status', UserSubscription::STATUS_PENDING)->whereNull('checkout_abandoned_at'))
                ->orWhereHas('paymentAttempts', fn ($query) => $query->where('review_required', true));
        })->withExists(['paymentAttempts as needs_review' => fn ($query) => $query->where('review_required', true)])
            ->orderByDesc('needs_review')->oldest('created_at')->limit(8)->get();

        return view('admin.dashboard', compact(
            'followUpCounts', 'segment', 'attentionSubscriptions', 'staleOrderCount',
            'pendingOrderAmount', 'reviewOrderCount', 'attentionOrders',
            'customerCount',
            'subscriptionCount',
            'orderCount',
            'pendingOrderCount',
            'activeOrderCount',
            'cancelledOrderCount'
        ));
    }
}
