<?php

namespace App\Support\Paytr;

use App\Models\SubscriptionPaymentAttempt;
use App\Models\UserSubscription;
use App\Support\LegalConsent;
use App\Support\PaymentAmount;
use App\Support\SubscriptionOrderLock;
use App\Support\SubscriptionPaymentAttempts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class StartIframe
{
    public const ENDPOINT = 'https://www.paytr.com/odeme/api/get-token';

    public function __construct(private Settings $settings, private SubscriptionPaymentAttempts $attempts) {}

    public function start(UserSubscription $order, string $ip): SubscriptionPaymentAttempt
    {
        if (! app(Access::class)->canStart($order->user)) {
            $this->reject('Kartla ödeme şu anda kapalı.');
        }
        $this->settings->assertReady();
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $this->reject('Ödeme için bağlantı adresiniz doğrulanamadı. Lütfen HTTPS test adresinden tekrar deneyin.');
        }

        [$attempt, $payload] = DB::transaction(function () use ($order, $ip) {
            $order = SubscriptionOrderLock::acquire($order);
            if ($order->trashed() || ! $order->isPending() || $order->payment_method !== 'kredi_kartı'
                || $order->payments()->withTrashed()->exists()) {
                $this->reject('Bu sipariş kart ödemesine uygun değil.');
            }
            if ($order->legalAcceptances()->where('user_id', $order->user_id)->whereIn('document_key', LegalConsent::SALE_DOCUMENTS)
                ->distinct()->count('document_key') !== count(LegalConsent::SALE_DOCUMENTS)) {
                $this->reject('Siparişin satış sözleşmesi onayları eksik. Yeni siparişle devam edin.');
            }
            $previous = $order->paymentAttempts()->where('provider', 'paytr')->latest('id')->first();
            $attempt = $this->attempts->open($order);
            if (config('paytr.test_accounts_only') && ! $attempt->is_test) {
                $this->reject('Bu aşamada yalnızca test ödemesi başlatılabilir. Lütfen destek alın.');
            }
            if ($attempt->provider !== 'paytr' || $attempt->currency !== 'TRY'
                || $attempt->amount_minor !== PaymentAmount::minor($order->price) || $attempt->amount_minor <= 0) {
                $this->reject('Sipariş ödeme bilgileri eşleşmiyor. Lütfen destek alın.');
            }
            if ($attempt->submitted_at !== null) {
                return [$attempt, null]; // Never repeat an external request, including after timeout.
            }
            if ($attempt->is_test && ! app(Access::class)->canSettleTest($order->user)) {
                $this->reject('Test ödemeleri yalnızca etkinleştirilmiş izole test ortamında kullanılabilir.');
            }
            $payer = $attempt->payer_snapshot ?? $previous?->payer_snapshot;
            $validator = Validator::make($payer ?? [], [
                'payer_name' => ['required', 'string', 'max:60'],
                'payer_email' => ['required', 'email', 'max:100', 'regex:/^[\x00-\x7F]+$/'],
                'payer_phone' => ['required', 'string', 'max:20'],
                'payer_address' => ['required', 'string', 'max:400'],
            ]);
            if ($validator->fails()) {
                $this->reject('Kart ödemesi için ödeyen kişi bilgileri eksik. Lütfen destek alın.');
            }
            $items = $order->items()->get();
            if ($items->isEmpty() || $items->contains(fn ($item) => $item->status !== 'pending' || $item->currency !== 'TRY')
                || $items->sum(fn ($item) => PaymentAmount::minor($item->amount)) !== $attempt->amount_minor
                || $order->paymentAttempts()->whereKeyNot($attempt->id)->whereIn('status', ['pending', 'unknown', 'succeeded'])->exists()) {
                $this->reject('Sipariş kalemleri veya ödeme durumu değişti. Lütfen destek alın.');
            }
            $apartmentIds = $items->pluck('apartment_id')->filter()->unique();
            $subscriptionIds = $items->pluck('apartment_subscription_id')->filter()->unique();
            if ($apartmentIds->isEmpty() || $subscriptionIds->isEmpty()
                || \App\Models\Apartment::whereIn('id', $apartmentIds)->where('is_active', true)->count() !== $apartmentIds->count()
                || \App\Models\Subscription::whereIn('id', $subscriptionIds)->where('status', 'active')->count() !== $subscriptionIds->count()) {
                $this->reject('Apartmanın aboneliği ödeme için uygun değil. Lütfen destek alın.');
            }
            $basket = $items->map(fn ($item) => [$item->apartment_name.' · AidatCep '.($order->period === 'yearly' ? 'Yıllık' : 'Aylık'), (string) $item->amount, 1])->all();
            $origin = $this->settings->publicOrigin();
            $payload = [
                'merchant_id' => config('paytr.merchant_id'), 'user_ip' => $ip,
                'merchant_oid' => $attempt->merchant_oid, 'email' => $payer['payer_email'],
                'payment_amount' => (string) $attempt->amount_minor,
                'user_basket' => base64_encode(json_encode($basket, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                'no_installment' => '1', 'max_installment' => '0', 'currency' => 'TL',
                'test_mode' => $attempt->is_test ? '1' : '0', 'debug_on' => '0', 'lang' => 'tr',
                'timeout_limit' => (string) config('paytr.payment_timeout_minutes'),
                'user_name' => $payer['payer_name'], 'user_address' => $payer['payer_address'], 'user_phone' => $payer['payer_phone'],
                'merchant_ok_url' => $origin.route('subscriber.subscriptions.card-result', ['subscription' => $order->id], false),
                'merchant_fail_url' => $origin.route('subscriber.subscriptions.card-result', ['subscription' => $order->id], false),
            ];
            $input = '';
            foreach (['merchant_id', 'user_ip', 'merchant_oid', 'email', 'payment_amount', 'user_basket', 'no_installment', 'max_installment', 'currency', 'test_mode'] as $field) {
                $input .= $payload[$field];
            }
            $payload['paytr_token'] = base64_encode(hash_hmac('sha256', $input.config('paytr.merchant_salt'), config('paytr.merchant_key'), true));
            $attempt->update(['payer_snapshot' => $payer, 'submitted_at' => now(), 'status' => 'unknown']);

            return [$attempt, $payload];
        });

        if ($payload === null) {
            return $attempt;
        }
        // The reservation is committed before I/O. No retries, no redirects, TLS verification stays enabled.
        try {
            $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(20)
                ->withOptions(['allow_redirects' => false])->post(self::ENDPOINT, $payload);
            $result = $response->json();
            $kind = $response->successful() && is_array($result) && ($result['status'] ?? null) === 'failed' ? 'failed' : 'unknown';
            $token = is_array($result) ? ($result['token'] ?? null) : null;
            if ($response->successful() && is_array($result) && ($result['status'] ?? null) === 'success'
                && is_string($token) && preg_match('/^[a-zA-Z0-9]{1,128}$/D', $token)) {
                $kind = 'pending';
            }
        } catch (Throwable) {
            $kind = 'unknown';
            $token = null;
        }

        return DB::transaction(function () use ($order, $attempt, $kind, $token) {
            SubscriptionOrderLock::acquire($order);
            $attempt = SubscriptionPaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            // A callback can arrive before the token HTTP response. Never overwrite its result.
            if ($attempt->review_required || ! in_array($attempt->status, ['pending', 'unknown'], true)) {
                return $attempt;
            }
            $attempt->update(['status' => $kind] + ($kind === 'pending' ? [
                'iframe_token' => $token,
                'token_expires_at' => $attempt->submitted_at->copy()->addMinutes((int) config('paytr.payment_timeout_minutes'))->subSeconds(10),
            ] : ($kind === 'failed' ? ['settled_at' => now()] : [])));
            if ($kind !== 'pending') {
                Log::warning('PayTR iframe request did not provide a usable token.', ['attempt_id' => $attempt->id, 'outcome' => $kind]);
            }

            return $attempt;
        });
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['card_payment' => $message]);
    }
}
