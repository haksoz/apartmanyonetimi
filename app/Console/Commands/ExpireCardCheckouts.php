<?php

namespace App\Console\Commands;

use App\Models\UserSubscription;
use App\Support\Paytr\AbandonCheckout;
use App\Support\SubscriptionOrderLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireCardCheckouts extends Command
{
    protected $signature = 'paytr:expire-checkouts';
    protected $description = 'Süresi geçmiş kart ödeme ekranlarını müşterinin listesinden ayırır; tahsilat başlatmaz veya sonucu başarısız saymaz.';

    public function handle(AbandonCheckout $checkout): int
    {
        $count = 0;
        UserSubscription::where('payment_method', 'kredi_kartı')->where('status', 'pending')
            ->whereNull('checkout_abandoned_at')->orderBy('id')->chunkById(100, function ($orders) use ($checkout, &$count) {
                foreach ($orders as $order) {
                    DB::transaction(function () use ($order, $checkout, &$count) {
                        $order = SubscriptionOrderLock::acquire($order);
                        if (! $order->isPending() || $order->checkout_abandoned_at || $order->payments()->withTrashed()->exists()) return;
                        $attempt = $order->paymentAttempts()->latest('id')->lockForUpdate()->first();
                        // token_expires_at includes a UI safety margin; it is not PayTR's actual deadline.
                        $expired = ($attempt?->submitted_at ?? $order->created_at)
                            ->lte(now()->subMinutes((int) config('paytr.payment_timeout_minutes', 30)));
                        if ($expired) { $checkout->abandon($order, expired: true); $count++; }
                    });
                }
            });
        $this->info($count.' süresi geçmiş ödeme girişimi arşivlendi. Sağlayıcı sonuçları değiştirilmedi.');

        return self::SUCCESS;
    }
}
