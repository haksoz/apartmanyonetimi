<?php

namespace App\Support;

use App\Models\SubscriptionItem;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelSubscriptionOrder
{
    public function cancel(UserSubscription $order, string $by, ?string $notes = null): void
    {
        DB::transaction(function () use ($order, $by, $notes) {
            $order = SubscriptionOrderLock::acquire($order);
            if ($order->trashed() || ! $order->isPending()) {
                throw ValidationException::withMessages(['subscription' => 'Yalnızca ödeme bekleyen sipariş iptal edilebilir.']);
            }
            if ($by === UserSubscription::CANCELLED_BY_CUSTOMER && $order->hasPaymentProof()) {
                throw ValidationException::withMessages(['subscription' => 'Ödeme bilgisi gönderildiği için sipariş iptal edilemez.']);
            }
            $attempts = $order->paymentAttempts()->lockForUpdate()->get();
            if ($attempts->contains(fn ($attempt) => $attempt->review_required || $attempt->status === 'unknown' || $attempt->status === 'succeeded'
                || ($attempt->provider === 'paytr' && $attempt->status === 'pending'))) {
                throw ValidationException::withMessages(['subscription' => 'Ödemenin sonucu kesinleşmeden sipariş iptal edilemez.']);
            }
            $ids = $order->items()->pluck('apartment_id');
            $order->items()->update(['status' => SubscriptionItem::STATUS_CANCELLED, 'ended_at' => now()]);
            $order->update(['status' => UserSubscription::STATUS_CANCELLED, 'cancelled_by' => $by,
                'is_active' => false, 'ended_at' => now(), 'notes' => $notes ?? $order->notes]);
            $order->paymentAttempts()->where('status', 'pending')->update(['status' => 'cancelled']);
            ApartmentCoverage::sync($ids);
        });
    }
}
