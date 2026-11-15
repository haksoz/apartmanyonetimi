<?php

namespace App\Support;

use App\Models\SubscriptionPaymentAttempt;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionPaymentAttempts
{
    public function open(UserSubscription $order): SubscriptionPaymentAttempt
    {
        return DB::transaction(function () use ($order) {
            $order = SubscriptionOrderLock::acquire($order);
            if ($order->trashed() || ! $order->isPending()) {
                throw ValidationException::withMessages(['subscription' => 'Bu sipariş için ödeme başlatılamaz.']);
            }
            if ($order->paymentAttempts()->where('review_required', true)->exists()) {
                throw ValidationException::withMessages(['subscription' => 'Ödeme incelemesi tamamlanmadan yeni deneme başlatılamaz.']);
            }
            $existing = $order->paymentAttempts()->whereIn('status', ['pending', 'unknown', 'succeeded'])->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            return $order->paymentAttempts()->create([
                'provider' => $order->payment_method === 'kredi_kartı' ? 'paytr' : 'bank_transfer',
                'merchant_oid' => 'AC'.strtoupper(Str::random(30)),
                'amount_minor' => PaymentAmount::minor($order->price),
                'currency' => 'TRY',
                'is_test' => $order->payment_method === 'kredi_kartı' && (bool) config('paytr.test_mode'),
                'status' => 'pending',
            ]);
        });
    }
}
