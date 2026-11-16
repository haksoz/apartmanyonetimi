<?php

namespace App\Support\Paytr;

use App\Models\UserSubscription;

class PaymentState
{
    public function forOrder(UserSubscription $order): array
    {
        $attempt = $order->paymentAttempts()->where('provider', 'paytr')->latest('id')->first();
        $notification = $attempt?->notifications()->where('provider_status', 'failed')
            ->whereIn('outcome', ['failed', 'duplicate'])->oldest('id')->first();
        $failure = FailureMessage::forCode($notification?->failed_reason_code);
        $state = match (true) {
            $attempt?->token_failure_code && ! $attempt->iframe_token && $attempt->status === 'failed' => 'screen_failed',
            $attempt?->token_failure_code && ! $attempt->iframe_token && $attempt->status === 'unknown' => 'screen_unavailable',
            $order->paymentAttempts()->where('review_required', true)->exists() => 'review',
            $attempt?->status === 'failed' && $notification !== null => 'failed',
            $order->checkout_abandoned_at !== null => 'abandoned',
            $order->status === 'cancelled' => 'cancelled',
            $attempt?->status === 'succeeded' && $order->payments()->withTrashed()->where('payment_attempt_id', $attempt->id)->exists() => 'paid',
            $attempt?->status === 'failed' => 'failed',
            $attempt?->status === 'unknown' => 'waiting',
            $attempt?->submitted_at !== null && (! $attempt->token_expires_at || $attempt->token_expires_at->isPast()) => 'waiting',
            default => 'ready',
        };

        return ['state' => $state, 'title' => match ($state) {
            'paid' => 'Ödemeniz onaylandı',
            'failed' => $failure['title'],
            'waiting', 'ready' => 'Ödeme doğrulanıyor',
            'screen_failed', 'screen_unavailable' => 'Ödeme ekranı açılamadı',
            'review' => 'Ödeme kontrol ediliyor',
            'abandoned' => 'Ödeme tamamlanmadı',
            'cancelled' => 'İşlem kapatıldı',
        }, 'message' => match ($state) {
            'screen_failed' => 'Ödeme ekranı açılamadı. Lütfen daha sonra yeniden deneyin.',
            'screen_unavailable' => 'PayTR ödeme ekranı henüz açılamadı. Bağlantı sonucu doğrulanmadan yeni ödeme başlatılmayacak. Lütfen daha sonra durumunu kontrol edin.',
            'abandoned' => 'Bu ödeme ekranından çıkıldı. PayTR’den ulaşan kesin ödeme sonucu ayrıca işlenecektir.',
            'paid' => 'Ödemeniz onaylandı. Abonelik bilgilerinizi sipariş detayından görebilirsiniz.',
            'failed' => $failure['message'],
            'review' => 'Ödemeniz kontrol ediliyor. Tekrar ödeme yapmayın; destek ekibiyle iletişime geçebilirsiniz.',
            'cancelled' => 'Bu sipariş iptal edilmiş.',
            'waiting' => $attempt?->status === 'unknown' && ! $attempt->iframe_token
                ? 'PayTR ödeme ekranı henüz açılamadı. İşlem durumu doğrulanmadan tekrar ödeme başlatılmayacak. Lütfen destek alın.'
                : 'Ödeme sonucunuz bekleniyor. Tekrar ödeme yapmayın. Bildirim geldiğinde bu ekran güncellenecek.',
            default => 'Kartla ödeme işleminize güvenli ödeme ekranından devam edebilirsiniz.',
        }, 'can_restart' => ($state === 'screen_failed' || ($state === 'failed' && $failure['retry'] && $order->status === UserSubscription::STATUS_CANCELLED))
            && app(Access::class)->canStart($order->user),
            'can_start' => app(Access::class)->canStart($order->user) && $order->isPending()
            && (! config('paytr.test_accounts_only') || ! $attempt || $attempt->is_test)
            && ! $order->checkout_abandoned_at && ($state !== 'failed' || $failure['retry'])
            && in_array($state, ['ready', 'failed'], true)];
    }
}
