<?php

namespace App\Support\Paytr;

use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\CheckoutJourneyTracking;
use App\Support\SubscriptionOrderLock;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SupersedeCheckout
{
    // Called inside checkout's transaction after user and apartment rows have been locked.
    public function replacePending(User $user, Collection $apartmentIds): void
    {
        $orders = UserSubscription::withTrashed()->where('status', 'pending')->where('payment_method', 'kredi_kartı')
            ->whereHas('items', fn ($query) => $query->whereIn('apartment_id', $apartmentIds)->where('status', 'pending'))
            ->orderBy('id')->get();
        foreach ($orders as $original) {
            $order = SubscriptionOrderLock::acquire($original);
            $attempts = $order->paymentAttempts()->lockForUpdate()->get();
            if ((int) $order->user_id !== (int) $user->id
                || $order->items()->whereNotIn('apartment_id', $apartmentIds)->exists()
                || $order->payments()->withTrashed()->exists() || $order->invoice()->exists()
                || $attempts->contains(fn ($attempt) => $attempt->review_required || $attempt->status === 'succeeded' || $attempt->provider !== 'paytr')) {
                throw ValidationException::withMessages(['payment_method' => 'Önceki ödeme kaydı incelenmeden yeni işlem başlatılamaz. Destek ekibiyle iletişime geçin.']);
            }
            app(CheckoutJourneyTracking::class)->attach($order, null);
            $order->items()->where('status', SubscriptionItem::STATUS_PENDING)
                ->update(['status' => SubscriptionItem::STATUS_CANCELLED, 'ended_at' => now()]);
            $order->update(['status' => UserSubscription::STATUS_CANCELLED, 'is_active' => false,
                'cancelled_by' => UserSubscription::CANCELLED_BY_CUSTOMER, 'ended_at' => now(),
                'checkout_abandoned_at' => $order->checkout_abandoned_at ?? now(),
                'notes' => trim(($order->notes ?? '')."\nMüşteri yeni ödeme denemesi başlattı; önceki süreç kapatıldı. PayTR sonucu ayrıca takip edilir.")]);
            // Do not invent a provider failure or change pending/unknown financial attempts.
            app(CheckoutJourneyTracking::class)->orderEvent($order->id, 'superseded', []);
        }
    }
}
