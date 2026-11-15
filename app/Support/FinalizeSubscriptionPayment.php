<?php

namespace App\Support;

use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPaymentAttempt;
use App\Models\UserSubscription;
use App\Support\Paytr\VerifiedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeSubscriptionPayment
{
    public function paytr(SubscriptionPaymentAttempt $attempt, VerifiedNotification $notification): SubscriptionPayment
    {
        return DB::transaction(function () use ($attempt, $notification) {
            $order = SubscriptionOrderLock::acquire($attempt->subscription);
            $attempt = $order->paymentAttempts()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $reject = function (string $reason): never {
                throw ValidationException::withMessages(['payment' => $reason]);
            };
            if ($reason = $notification->mismatchReason($attempt)) {
                $reject($reason);
            }
            if ($notification->status !== 'success' || $order->payment_method !== 'kredi_kartı'
                || PaymentAmount::minor($order->price) !== $attempt->amount_minor) {
                $reject('order_amount_or_method_mismatch');
            }
            $payment = $order->payments()->withTrashed()->where('payment_attempt_id', $attempt->id)->first();
            if ($attempt->status === 'succeeded') {
                if (! $payment || PaymentAmount::minor($payment->amount) !== $attempt->amount_minor) {
                    $reject('settled_payment_missing_or_mismatched');
                }

                // A later administrative cancellation must not reactivate the order on replay.
                return $payment;
            }
            if (! in_array($attempt->status, ['pending', 'unknown'], true)) {
                $reject('terminal_attempt_conflict');
            }
            if ($order->trashed() || ! $order->isPending() || $order->payments()->withTrashed()->exists()) {
                $reject('order_not_payable');
            }
            if ($order->paymentAttempts()->whereKeyNot($attempt->id)->where(fn ($query) => $query
                ->whereIn('status', ['pending', 'unknown', 'succeeded'])->orWhere('review_required', true))->exists()) {
                $reject('another_unresolved_attempt');
            }
            $items = $order->items()->get();
            if ($items->isEmpty() || $items->contains(fn ($item) => $item->status !== 'pending' || $item->currency !== 'TRY'
                    || ! $item->apartment_id || ! $item->apartment_subscription_id)
                || $items->sum(fn ($item) => PaymentAmount::minor($item->amount)) !== $attempt->amount_minor) {
                $reject('order_items_mismatch');
            }
            $apartmentIds = $items->pluck('apartment_id')->unique();
            $subscriptionIds = $items->pluck('apartment_subscription_id')->unique();
            if (\App\Models\Apartment::whereIn('id', $apartmentIds)->where('is_active', true)->count() !== $apartmentIds->count()
                || \App\Models\Subscription::whereIn('id', $subscriptionIds)->where('status', \App\Models\Subscription::STATUS_ACTIVE)->count() !== $subscriptionIds->count()) {
                $reject('apartment_subscription_unavailable');
            }

            app(SubscriptionCheckout::class)->activate($order);
            $payment = $order->payments()->create([
                'payment_attempt_id' => $attempt->id,
                'amount' => $order->price,
                'payment_date' => now(),
                'payment_method' => 'kredi_kartı',
                'reference_code' => $attempt->merchant_oid,
                'notes' => $attempt->is_test ? 'PayTR test ödemesi (izole test ortamı).' : 'PayTR doğrulanmış ödeme bildirimi.',
            ]);
            $attempt->update(['status' => 'succeeded', 'settled_at' => now()]);

            return $payment;
        });
    }

    public function manual(UserSubscription $order, string $method, ?string $reference = null, ?string $notes = null): SubscriptionPayment
    {
        return DB::transaction(function () use ($order, $method, $reference, $notes) {
            $order = SubscriptionOrderLock::acquire($order);
            if ($order->paymentAttempts()->where('review_required', true)->exists()) {
                throw ValidationException::withMessages(['subscription' => 'Ödeme incelemesi tamamlanmadan işlem yapılamaz.']);
            }
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
