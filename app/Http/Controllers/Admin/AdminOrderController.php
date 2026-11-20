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
            ->when($status === 'deleted', fn ($query) => $query->onlyTrashed())
            ->commercial()
            ->when(! in_array($status, ['abandoned', 'deleted'], true), fn ($query) => $query->visibleInOrderList())
            ->with([
                'user',
                'subscription',
                'invoice',
                'items.apartment',
                'items.apartmentSubscription',
                'payments',
                'paymentAttempts' => fn ($query) => $query->latest('id')->limit(1),
            ])
            ->withSum('payments as collected_total', 'amount')
            ->withExists(['paymentAttempts as has_payment_review' => fn ($query) => $query->where('review_required', true)])
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
                if ($status === UserSubscription::STATUS_PENDING) $query->whereNull('checkout_abandoned_at');
            })
            ->when($status === 'abandoned', fn ($query) => $query->whereNotNull('checkout_abandoned_at'))
            ->when($payment === 'collected', function ($query) {
                $query->whereHas('payments');
            })
            ->when($payment === 'pending', function ($query) {
                $query->where('status', UserSubscription::STATUS_PENDING)
                    ->whereNull('checkout_abandoned_at')
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

        foreach ($orders as $order) {
            $order->can_invoice = ! $order->invoice && app(\App\Support\OrderInvoicing::class)->eligible($order);
        }

        return view('admin.orders.index', compact('orders', 'search', 'status', 'payment'));
    }

    public function show(UserSubscription $order)
    {
        $order->load([
            'user',
            'invoice',
            'subscription.apartment',
            'subscription.items',
            'items.apartment',
            'items.apartmentSubscription',
            'payments',
            'legalAcceptances.legalDocumentVersion',
            'paymentAttempts' => fn ($query) => $query->orderByDesc('id'),
            'paymentAttempts.notifications' => fn ($query) => $query->latest('updated_at')->latest('id')->limit(1),
        ]);

        $canCloseTest = app(\App\Support\Paytr\CloseTestCheckout::class)->eligible($order);

        $canInvoice = ! $order->invoice && app(\App\Support\OrderInvoicing::class)->eligible($order);

        return view('admin.orders.show', compact('order', 'canCloseTest', 'canInvoice'));
    }

    public function closeTest(Request $request, UserSubscription $order)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'test_close_reason' => ['required', 'string', 'max:1000'],
        ]);
        app(\App\Support\Paytr\CloseTestCheckout::class)->close($order, $request->user()->id, $data['test_close_reason']);

        return redirect()->route('admin.orders.show', $order)->with('status', 'Test girişimi kapatıldı. Yeni ödeme denemesi başlatılabilir.');
    }

    public function destroy(Request $request, UserSubscription $order)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'purge_order_action' => ['nullable', 'string'],
            'deletion_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $number = $order->order_number ?: 'Sipariş';
        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $request) {
            $order = \App\Support\SubscriptionOrderLock::acquire($order);
            if ($order->payments()->exists() || $order->paymentAttempts()->where('status', 'succeeded')->exists()) {
                throw ValidationException::withMessages(['order' => 'Tahsilat kaydı olan sipariş silinemez. İade veya düzeltme sürecini kullanın.']);
            }
            $unresolved = $order->paymentAttempts()->where(fn ($query) => $query
                ->whereIn('status', ['unknown', 'succeeded'])
                ->orWhere('review_required', true)
                ->orWhere(fn ($query) => $query->where('provider', 'paytr')->where('status', 'pending')))->exists();
            $order->update(['notes' => trim(($order->notes ?? '')."\nAdmin pasif silme: ".$request->user()->id.' · '.now()->toIso8601String().' · '.($request->input('deletion_reason') ?: 'Yönetici tarafından listeden kaldırıldı.'))]);
            if (! $unresolved) {
                $order->paymentAttempts()->where('status', 'pending')->update(['status' => 'cancelled']);
                $order->items()->where('status', \App\Models\SubscriptionItem::STATUS_PENDING)
                    ->update(['status' => \App\Models\SubscriptionItem::STATUS_CANCELLED, 'ended_at' => now()]);
            }
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
            if ($order->invoice()->exists()) {
                throw ValidationException::withMessages(['order' => 'Faturalandırılmış siparişin tahsilatı silinemez.']);
            }
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
