<?php

namespace App\Support\Paytr;

use App\Models\UserSubscription;
use App\Support\CancelSubscriptionOrder;
use App\Support\SubscriptionOrderLock;
use Illuminate\Support\Facades\DB;

class AbandonCheckout
{
    public function abandon(UserSubscription $order, bool $expired = false): void
    {
        DB::transaction(function () use ($order, $expired) {
            $order = SubscriptionOrderLock::acquire($order);
            if ($order->trashed() || ! $order->isPending() || $order->payments()->withTrashed()->exists()) return;
            if (! $order->checkout_abandoned_at) {
                $order->update(['checkout_abandoned_at' => now(),
                    'notes' => trim(($order->notes ?? '')."\n".($expired ? 'Ödeme ekranının süresi doldu: ' : 'Ödeme ekranından çıkıldı: ').now()->toIso8601String())]);
                if ($expired) {
                    app(\App\Support\CheckoutJourneyTracking::class)->orderEvent($order->id, 'card_expired', []);
                }
            }
            $attempts = $order->paymentAttempts()->lockForUpdate()->get();
            $unresolved = $attempts->contains(fn ($attempt) => $attempt->review_required || $attempt->status === 'succeeded'
                || ($attempt->submitted_at !== null && in_array($attempt->status, ['pending', 'unknown'], true)));
            if ($unresolved) return;
            $order->paymentAttempts()->where('status', 'pending')->whereNull('submitted_at')->update(['status' => 'cancelled']);
            $technicalFailure = $attempts->contains(fn ($attempt) => $attempt->token_failure_code !== null);
            app(CancelSubscriptionOrder::class)->cancel($order,
                $technicalFailure ? UserSubscription::CANCELLED_BY_SYSTEM : UserSubscription::CANCELLED_BY_CUSTOMER,
                $technicalFailure ? $order->notes : 'Kullanıcı ödeme işlemini tamamlamadan çıktı.');
        });
    }
}
