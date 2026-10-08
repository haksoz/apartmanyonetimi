<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $payment = (string) $request->query('payment', '');

        $statuses = [
            UserSubscription::STATUS_PENDING,
            UserSubscription::STATUS_ACTIVE,
            UserSubscription::STATUS_CANCELLED,
        ];

        $orders = UserSubscription::query()
            ->commercial()
            ->with([
                'user',
                'subscription',
                'items.apartment',
                'payments',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhere('billing_legal_name', 'like', "%{$search}%")
                        ->orWhere('billing_label', 'like', "%{$search}%")
                        ->orWhereHas('items', function ($query) use ($search) {
                            $query->where('apartment_name', 'like', "%{$search}%")
                                ->orWhereHas('apartment', function ($query) use ($search) {
                                    $query->where('name', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when(in_array($status, $statuses, true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($payment === 'collected', function ($query) {
                $query->whereHas('payments');
            })
            ->when($payment === 'pending', function ($query) {
                $query->where('status', UserSubscription::STATUS_PENDING)
                    ->whereDoesntHave('payments');
            })
            ->when($payment === 'unpaid', function ($query) {
                $query->where('status', UserSubscription::STATUS_CANCELLED)
                    ->whereDoesntHave('payments');
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', compact('orders', 'search', 'status', 'payment'));
    }

    public function show(UserSubscription $order)
    {
        $order->load([
            'user',
            'subscription.apartment',
            'subscription.items',
            'items.apartment',
            'items.apartmentSubscription',
            'payments',
            'legalAcceptances.legalDocumentVersion',
        ]);

        return view('admin.orders.show', compact('order'));
    }
}
