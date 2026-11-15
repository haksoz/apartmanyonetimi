<?php

namespace App\Support\Paytr;

use App\Models\SubscriptionPaymentAttempt;
use Illuminate\Support\Facades\Validator;

final class VerifiedNotification
{
    private function __construct(
        public readonly string $merchantOid,
        public readonly string $status,
        public readonly int $totalAmount,
        public readonly ?int $paymentAmount,
        public readonly ?string $currency,
        public readonly ?string $paymentType,
        public readonly bool $isTest,
        public readonly ?string $failureCode,
    ) {}

    public static function verify(array $payload): self
    {
        $key = config('paytr.merchant_key');
        $salt = config('paytr.merchant_salt');
        if (! is_string($key) || $key === '' || ! is_string($salt) || $salt === '') {
            throw new CallbackRejected(503, 'Payment notification unavailable.');
        }

        $validator = Validator::make($payload, [
            'merchant_oid' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9]+$/D'],
            'status' => ['required', 'string', 'in:success,failed'],
            'total_amount' => ['required', 'string', 'regex:/^\d{1,12}$/D'],
            'hash' => ['required', 'string', 'size:44'],
            'payment_amount' => ['nullable', 'string', 'regex:/^\d{1,12}$/D'],
            'currency' => ['nullable', 'string', 'max:8'],
            'payment_type' => ['nullable', 'string', 'max:16'],
            'test_mode' => ['nullable', 'string', 'in:0,1'],
            'failed_reason_code' => ['nullable', 'string', 'max:32'],
        ]);
        if ($validator->fails()) {
            throw new CallbackRejected(400, 'Invalid payment notification.');
        }
        $data = $validator->validated();
        $expected = base64_encode(hash_hmac('sha256', $data['merchant_oid'].$salt.$data['status'].$data['total_amount'], $key, true));
        if (! hash_equals($expected, $data['hash'])) {
            throw new CallbackRejected(403, 'Invalid payment signature.');
        }

        return new self($data['merchant_oid'], $data['status'], (int) $data['total_amount'],
            isset($data['payment_amount']) ? (int) $data['payment_amount'] : null,
            $data['currency'] ?? null, $data['payment_type'] ?? null,
            ($data['test_mode'] ?? '0') === '1', $data['failed_reason_code'] ?? null);
    }

    public function mismatchReason(SubscriptionPaymentAttempt $attempt): ?string
    {
        // Only merchant_oid, status and total_amount are authenticated by PayTR's HMAC.
        // The local attempt is authoritative; unsigned metadata can only reject a result.
        return match (true) {
            $attempt->provider !== 'paytr', $attempt->merchant_oid !== $this->merchantOid => 'attempt_mismatch',
            $attempt->review_required => 'review_required',
            $attempt->submitted_at === null => 'attempt_not_submitted',
            $attempt->currency !== 'TRY', $this->currency !== 'TL' => 'currency_mismatch',
            $this->paymentType !== 'card' => 'payment_type_mismatch',
            $this->isTest !== $attempt->is_test => 'test_mode_mismatch',
            $attempt->is_test && ! app(Access::class)->canSettleTest($attempt->subscription->user) => 'test_settlement_disabled',
            $this->paymentAmount !== $attempt->amount_minor => 'payment_amount_mismatch',
            $this->status === 'success' && ($this->totalAmount !== $attempt->amount_minor || $attempt->amount_minor <= 0) => 'total_amount_mismatch',
            default => null,
        };
    }

    public function auditAttributes(): array
    {
        return [
            'provider_status' => $this->status,
            'total_amount_minor' => $this->totalAmount,
            'payment_amount_minor' => $this->paymentAmount,
            'reported_currency' => $this->currency,
            'reported_payment_type' => $this->paymentType,
            'reported_test_mode' => $this->isTest,
            'failed_reason_code' => $this->failureCode,
        ];
    }
}
