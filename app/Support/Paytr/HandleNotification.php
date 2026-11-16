<?php

namespace App\Support\Paytr;

use App\Models\SubscriptionPaymentAttempt;
use App\Models\SubscriptionPaymentNotification;
use App\Support\FinalizeSubscriptionPayment;
use App\Support\PaymentAmount;
use App\Support\SubscriptionOrderLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class HandleNotification
{
    public function __construct(private FinalizeSubscriptionPayment $finalize) {}

    public function handle(VerifiedNotification $notification): bool
    {
        $attempt = SubscriptionPaymentAttempt::where('merchant_oid', $notification->merchantOid)->where('provider', 'paytr')->first();
        if (! $attempt) {
            throw new CallbackRejected(404, 'Payment attempt not found.');
        }

        return DB::transaction(function () use ($attempt, $notification) {
            $order = SubscriptionOrderLock::acquire($attempt->subscription);
            $attempt = $order->paymentAttempts()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $attributes = $notification->auditAttributes();
            $fingerprint = hash('sha256', $attempt->id.'|'.json_encode($attributes, JSON_THROW_ON_ERROR));
            $audit = SubscriptionPaymentNotification::firstOrNew(['fingerprint' => $fingerprint]);
            $audit->fill($attributes + ['payment_attempt_id' => $attempt->id]);
            $audit->deliveries = $audit->exists ? $audit->deliveries + 1 : 1;

            $review = function (string $reason) use ($attempt, $audit): bool {
                if ($reason === 'review_required' && $audit->exists && $audit->reason) {
                    $reason = $audit->reason;
                }
                $firstReview = ! $attempt->review_required;
                $attempt->update(['review_required' => true] + (in_array($attempt->status, ['pending', 'unknown'], true) ? ['status' => 'unknown'] : []));
                $audit->fill(['outcome' => 'review', 'reason' => $reason])->save();
                if ($firstReview) {
                    Log::warning('PayTR payment requires review.', ['attempt_id' => $attempt->id, 'reason' => $reason]);
                }

                return false;
            };

            if ($notification->status === 'success') {
                $duplicate = $attempt->status === 'succeeded';
                try {
                    $this->finalize->paytr($attempt, $notification);
                } catch (ValidationException $exception) {
                    return $review($exception->errors()['payment'][0] ?? 'activation_rejected');
                }
                $audit->fill(['outcome' => $duplicate ? 'duplicate' : 'succeeded', 'reason' => null])->save();

                return true;
            }

            if ($reason = $notification->mismatchReason($attempt)) {
                return $review($reason);
            }
            if ($order->payment_method !== 'kredi_kartı' || PaymentAmount::minor($order->price) !== $attempt->amount_minor) {
                return $review('order_amount_or_method_mismatch');
            }
            if ($attempt->status === 'failed') {
                $audit->fill(['outcome' => 'duplicate', 'reason' => null])->save();

                return true;
            }
            if (! in_array($attempt->status, ['pending', 'unknown'], true) || $order->trashed() || ! $order->isPending()
                || $order->payments()->withTrashed()->exists()) {
                return $review('terminal_attempt_conflict');
            }

            // A declined card ends this attempt. The unpaid order can be retried with a new merchant_oid.
            $attempt->update(['status' => 'failed', 'settled_at' => now()]);
            if ($order->checkout_abandoned_at) {
                app(AbandonCheckout::class)->abandon($order);
            }
            $audit->fill(['outcome' => 'failed', 'reason' => null])->save();

            return true;
        }, 3);
    }
}
