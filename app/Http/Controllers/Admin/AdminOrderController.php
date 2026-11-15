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
            'paymentAttempts' => fn ($query) => $query->orderByDesc('id'),
            'paymentAttempts.notifications' => fn ($query) => $query->latest('updated_at')->latest('id')->limit(1),
        ]);

        return view('admin.orders.show', compact('order'));
    }

    public function destroy(Request $request, UserSubscription $order)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'purge_order_action' => ['nullable', 'string'],
        ]);

        $number = $order->order_number ?: 'Sipariş';
        \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            $order = \App\Support\SubscriptionOrderLock::acquire($order);
            if ($order->payments()->exists() || $order->paymentAttempts()->where(fn ($query) => $query
                ->whereIn('status', ['unknown', 'succeeded'])
                ->orWhere('review_required', true)
                ->orWhere(fn ($query) => $query->where('provider', 'paytr')->where('status', 'pending')))->exists()) {
                throw ValidationException::withMessages(['order' => 'Ödeme veya sonucu belirsiz işlem kaydı olan sipariş silinemez.']);
            }
            $order->paymentAttempts()->where('status', 'pending')->update(['status' => 'cancelled']);
            $order->items()->where('status', \App\Models\SubscriptionItem::STATUS_PENDING)
                ->update(['status' => \App\Models\SubscriptionItem::STATUS_CANCELLED, 'ended_at' => now()]);
            $order->delete();
        });

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

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $payment) {
            \App\Support\SubscriptionOrderLock::acquire($order);
            $payment = SubscriptionPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->payment_attempt_id) {
                throw ValidationException::withMessages(['order' => 'Ödeme denemesine bağlı tahsilat silinemez. İade veya düzeltme sürecini kullanın.']);
            }
            $payment->delete();
        });

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Ödeme pasif silindi.');
    }
}
