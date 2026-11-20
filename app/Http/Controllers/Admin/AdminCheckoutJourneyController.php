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
        $journeys = CheckoutJourney::with(['user', 'apartment', 'order.paymentAttempts'])
            ->when($customerId > 0, fn ($query) => $query->where('user_id', $customerId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('uuid', $search)
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('apartment', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('order', function ($query) use ($search) {
                            $query->where('order_number', 'like', "%{$search}%")
                                ->orWhereHas('paymentAttempts', fn ($query) => $query->where('merchant_oid', 'like', "%{$search}%"));
                        });
                    if (preg_match('/^GCS-(\d+)$/iD', $search, $matches)) {
                        $query->orWhere('checkout_journeys.id', (int) $matches[1]);
                    }
                });
            })
            ->when($status === 'incomplete', fn ($query) => $query->whereNull('order_id')->whereNull('closed_at')->where('last_seen_at', '<', now()->subMinutes(30)))
            ->when($status === 'closed', fn ($query) => $query->where(fn ($query) => $query->whereNotNull('closed_at')->orWhereHas('order', fn ($query) => $query->whereNotNull('checkout_abandoned_at'))))
            ->when($status === 'active', fn ($query) => $query->whereHas('order', fn ($query) => $query->where('status', 'active')))
            ->when($status === 'pending', fn ($query) => $query->whereHas('order', fn ($query) => $query->where('status', 'pending')))
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
