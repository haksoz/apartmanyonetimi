<?php

namespace App\Support;

use App\Models\SubscriptionPayment;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeSubscriptionPayment
{
    public function manual(UserSubscription $order, string $method, ?string $reference = null, ?string $notes = null): SubscriptionPayment
    {
        return DB::transaction(function () use ($order, $method, $reference, $notes) {
            $order = SubscriptionOrderLock::acquire($order);
            $attempt = $order->paymentAttempts()->whereIn('status', ['pending', 'unknown', 'succeeded'])->lockForUpdate()->first();
            $existing = $order->payments()->withTrashed()->first();
            if ($existing && ! $order->trashed() && $order->status === UserSubscription::STATUS_ACTIVE) {
                return $existing;
            }
            if ($order->trashed() || ! $order->isPending() || $existing || $attempt?->status === 'succeeded') {
                throw ValidationException::withMessages(['subscription' => 'Sipariş tahsilata uygun değil.']);
            }
            if ($order->payment_method === 'kredi_kartı' || $attempt?->provider === 'paytr' || $attempt?->status === 'unknown') {
                throw ValidationException::withMessages(['payment_method' => 'Kart veya sonucu belirsiz ödeme manuel onaylanamaz.']);
            }
            if ($order->hasPaymentProof()) {
                $method = $order->payment_method ?? 'havale';
                $reference = $order->receipt_reference ?? $reference;
            }
            if (! in_array($method, ['havale', 'nakit'], true)) {
                throw ValidationException::withMessages(['payment_method' => 'Geçersiz ödeme yöntemi.']);
            }
            if ($attempt && $attempt->amount_minor !== PaymentAmount::minor($order->price)) {
                throw ValidationException::withMessages(['subscription' => 'Ödeme denemesi ve sipariş tutarı eşleşmiyor.']);
            }
            if ($order->items()->exists()) {
                app(SubscriptionCheckout::class)->activate($order);
            } else {
                UserSubscription::where('user_id', $order->user_id)->where('is_active', true)->whereKeyNot($order->id)
                    ->whereDoesntHave('items')->update(['is_active' => false, 'status' => UserSubscription::STATUS_CANCELLED, 'ended_at' => now()]);
                $order->update(['status' => UserSubscription::STATUS_ACTIVE, 'is_active' => true, 'started_at' => now(),
                    'expires_at' => $order->period === 'yearly' ? now()->addYear() : now()->addMonth()]);
            }
            $payment = $order->payments()->create([
                'payment_attempt_id' => $attempt?->id,
                'amount' => $order->price, 'payment_date' => now(),
                'payment_method' => $method, 'reference_code' => $reference, 'notes' => $notes,
            ]);
            $attempt?->update(['status' => 'succeeded', 'settled_at' => now()]);

            return $payment;
        });
    }
}
