<?php

namespace App\Support\Paytr;

use App\Models\UserSubscription;

class PaymentState
{
    public function forOrder(UserSubscription $order): array
    {
        $attempt = $order->paymentAttempts()->where('provider', 'paytr')->latest('id')->first();
        $state = match (true) {
            $attempt?->token_failure_code && ! $attempt->iframe_token && $attempt->status === 'failed' => 'screen_failed',
            $attempt?->token_failure_code && ! $attempt->iframe_token && $attempt->status === 'unknown' => 'screen_unavailable',
            $order->checkout_abandoned_at !== null => 'abandoned',
            $order->paymentAttempts()->where('review_required', true)->exists() => 'review',
            $order->status === 'cancelled' => 'cancelled',
            $attempt?->status === 'succeeded' && $order->payments()->withTrashed()->where('payment_attempt_id', $attempt->id)->exists() => 'paid',
            $attempt?->status === 'failed' => 'failed',
            $attempt?->status === 'unknown' => 'waiting',
            $attempt?->submitted_at !== null && (! $attempt->token_expires_at || $attempt->token_expires_at->isPast()) => 'waiting',
            default => 'ready',
        };

        return ['state' => $state, 'message' => match ($state) {
            'screen_failed' => 'Ödeme ekranı açılamadı. Lütfen daha sonra yeniden deneyin.',
            'screen_unavailable' => 'PayTR ödeme ekranı henüz açılamadı. Bağlantı sonucu doğrulanmadan yeni ödeme başlatılmayacak. Lütfen daha sonra durumunu kontrol edin.',
            'abandoned' => 'Bu ödeme ekranından çıkıldı. PayTR’den ulaşan kesin ödeme sonucu ayrıca işlenecektir.',
            'paid' => 'Ödemeniz onaylandı. Abonelik bilgilerinizi sipariş detayından görebilirsiniz.',
            'failed' => 'Ödeme tamamlanamadı. Kart bilgilerinizi kontrol ederek yeniden deneyebilirsiniz.',
            'review' => 'Ödemeniz kontrol ediliyor. Tekrar ödeme yapmayın; destek ekibiyle iletişime geçebilirsiniz.',
            'cancelled' => 'Bu sipariş iptal edilmiş.',
            'waiting' => $attempt?->status === 'unknown' && ! $attempt->iframe_token
                ? 'PayTR ödeme ekranı henüz açılamadı. İşlem durumu doğrulanmadan tekrar ödeme başlatılmayacak. Lütfen destek alın.'
                : 'Ödeme sonucunuz bekleniyor. Tekrar ödeme yapmayın. Bildirim geldiğinde bu ekran güncellenecek.',
            default => 'Kartla ödeme işleminize güvenli ödeme ekranından devam edebilirsiniz.',
        }, 'can_restart' => $state === 'screen_failed' && app(Access::class)->canStart($order->user),
            'can_start' => app(Access::class)->canStart($order->user) && $order->isPending()
            && (! config('paytr.test_accounts_only') || ! $attempt || $attempt->is_test)
            && in_array($state, ['ready', 'failed'], true)];
    }
}
