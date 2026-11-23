<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CheckoutJourney;
use Illuminate\Http\Request;

class AdminCheckoutJourneyController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $method = (string) $request->query('method', '');
        $customerId = $request->integer('customer');
        $journeys = CheckoutJourney::with(['user', 'apartment', 'order.paymentAttempts'])->tap([\App\Support\AdminTestData::class, 'apply'])
            ->when($customerId > 0, fn ($query) => $query->where('user_id', $customerId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('uuid', $search)
                        ->orWhere('reference_number', strtoupper($search))
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('apartment', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('order', function ($query) use ($search) {
                            $query->where('order_number', 'like', "%{$search}%")
                                ->orWhereHas('paymentAttempts', fn ($query) => $query->where('merchant_oid', 'like', "%{$search}%"));
                        });
                    if (preg_match('/^GCS-(\d+)$/iD', $search, $matches)) {
                        $query->orWhere(fn ($query) => $query->whereNull('reference_number')->where('checkout_journeys.id', (int) $matches[1]));
                    }
                });
            })
            ->when($status === 'incomplete', fn ($query) => $query->whereNull('order_id')->whereNull('closed_at')->where('last_seen_at', '<=', now()->subSeconds(CheckoutJourney::FORM_TIMEOUT_SECONDS)))
            ->when($status === 'closed', fn ($query) => $query->whereDoesntHave('events', fn ($query) => $query->where('type', 'card_expired'))
                ->where(fn ($query) => $query->whereNotNull('closed_at')->orWhereHas('order', fn ($query) => $query->whereNotNull('checkout_abandoned_at'))))
            ->when($status === 'expired', fn ($query) => $query->expiredCardScreen())
            ->when($status === 'superseded', fn ($query) => $query
                ->whereHas('events', fn ($query) => $query->where('type', 'superseded'))
                ->whereHas('order', fn ($query) => $query->where('status', 'cancelled')
                    ->whereDoesntHave('paymentAttempts', fn ($query) => $query->where('review_required', true)->orWhere('status', 'failed'))))
            ->when($status === 'failed', fn ($query) => $query->whereHas('order', fn ($query) => $query
                ->whereIn('status', ['pending', 'cancelled'])
                ->whereDoesntHave('paymentAttempts', fn ($query) => $query->where('review_required', true))
                ->whereHas('paymentAttempts', fn ($query) => $query->where('provider', 'paytr')
                    ->where('status', 'failed')->whereNull('token_failure_code'))))
            ->when($status === 'active', fn ($query) => $query->whereHas('order', fn ($query) => $query->where('status', 'active')))
            ->when($status === 'pending', fn ($query) => $query->whereNull('closed_at')
                ->whereNotIn('checkout_journeys.id', CheckoutJourney::query()->expiredCardScreen()->select('checkout_journeys.id'))
                ->whereHas('order', fn ($query) => $query->where('status', 'pending')->whereNull('checkout_abandoned_at')
                    ->whereDoesntHave('paymentAttempts', fn ($query) => $query->where('review_required', true)->orWhere('status', 'failed'))))
            ->when($status === 'review', fn ($query) => $query->whereHas('order.paymentAttempts', fn ($query) => $query->where('review_required', true)))
            ->when(in_array($method, ['kredi_kartı', 'havale'], true), fn ($query) => $query->where('payment_method', $method))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.checkout-journeys.index', compact('journeys', 'search', 'status', 'method', 'customerId'));
    }

    public function show(CheckoutJourney $journey)
    {
        $journey->load(['user', 'apartment', 'events' => fn ($query) => $query->orderBy('id'), 'order.paymentAttempts.notifications']);

        return view('admin.checkout-journeys.show', compact('journey'));
    }
}
