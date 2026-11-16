<?php

namespace App\Support\Paytr;

use App\Models\SubscriptionItem;
use App\Models\UserSubscription;
use App\Support\ApartmentCoverage;
use App\Support\SubscriptionOrderLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseTestCheckout
{
    public function eligible(UserSubscription $order): bool
    {
        $attempts = $order->paymentAttempts()->get();

        return $order->isPending() && $order->payment_method === 'kredi_kartı'
            && ! $order->payments()->withTrashed()->exists()
            && $attempts->isNotEmpty()
            && $attempts->every(fn ($attempt) => $attempt->provider === 'paytr' && $attempt->is_test && $attempt->status !== 'succeeded');
    }

    public function close(UserSubscription $order, int $adminId, string $reason): void
    {
        DB::transaction(function () use ($order, $adminId, $reason) {
            $order = SubscriptionOrderLock::acquire($order);
            $order->paymentAttempts()->lockForUpdate()->get();
            if (! $this->eligible($order)) {
                throw ValidationException::withMessages(['test_checkout' => 'Yalnızca tahsilatı olmayan, bekleyen PayTR test siparişi kapatılabilir.']);
            }
            $order->paymentAttempts()->update(['status' => 'cancelled', 'settled_at' => now(), 'review_required' => false]);
            $ids = $order->items()->pluck('apartment_id');
            $order->items()->where('status', SubscriptionItem::STATUS_PENDING)
                ->update(['status' => SubscriptionItem::STATUS_CANCELLED, 'ended_at' => now()]);
            $order->update(['status' => UserSubscription::STATUS_CANCELLED, 'is_active' => false,
                'cancelled_by' => UserSubscription::CANCELLED_BY_ADMIN, 'ended_at' => now(),
                'notes' => trim(($order->notes ?? '')."\nPayTR test girişimi kapatıldı: ".$adminId.' · '.now()->toIso8601String().' · '.$reason)]);
            ApartmentCoverage::sync($ids);
        });
    }
}
