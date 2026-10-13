<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    public function destroy(Request $request, UserSubscription $order)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'purge_order_action' => ['nullable', 'string'],
        ]);

        if ($order->payments()->exists()) {
            throw ValidationException::withMessages([
                'order' => 'Bu siparişe bağlı ödeme var. Önce ödemeyi silin.',
            ]);
        }

        $number = $order->order_number ?: 'Sipariş';
        $order->delete();

        return redirect()
            ->route('admin.orders.index')
            ->with('status', $number.' pasif silindi.');
    }

    public function destroyPayment(Request $request, UserSubscription $order, SubscriptionPayment $payment)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'purge_payment_action' => ['nullable', 'string'],
        ]);

        abort_unless((int) $payment->subscription_id === (int) $order->id, 404);

        $payment->delete();

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Ödeme pasif silindi.');
    }
}
