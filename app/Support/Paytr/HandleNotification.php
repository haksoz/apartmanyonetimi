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

    public function handle(VerifiedNotification $notification, bool $countDelivery = true): bool
    {
        $attempt = SubscriptionPaymentAttempt::where('merchant_oid', $notification->merchantOid)->where('provider', 'paytr')->first();
        if (! $attempt) {
            throw new CallbackRejected(404, 'Payment attempt not found.');
        }

        return DB::transaction(function () use ($attempt, $notification, $countDelivery) {
            $order = SubscriptionOrderLock::acquire($attempt->subscription);
            $attempt = $order->paymentAttempts()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $attributes = $notification->auditAttributes();
            $fingerprint = hash('sha256', $attempt->id.'|'.json_encode($attributes, JSON_THROW_ON_ERROR));
            $audit = SubscriptionPaymentNotification::firstOrNew(['fingerprint' => $fingerprint]);
            $audit->fill($attributes + ['payment_attempt_id' => $attempt->id]);
            $audit->deliveries = $audit->exists ? $audit->deliveries + ($countDelivery ? 1 : 0) : 1;

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

            $recover = $this->canRecoverMissingFailureFields($attempt);
            if ($reason = $notification->mismatchReason($attempt, $recover)) {
                return $review($reason);
            }
            if ($order->payment_method !== 'kredi_kartı' || PaymentAmount::minor($order->price) !== $attempt->amount_minor) {
                return $review('order_amount_or_method_mismatch');
            }
            if ($attempt->status === 'failed') {
                $audit->fill(['outcome' => 'duplicate', 'reason' => null])->save();

                return true;
            }
            $superseded = $order->status === 'cancelled' && \App\Models\CheckoutJourney::where('order_id', $order->id)
                ->whereHas('events', fn ($query) => $query->where('type', 'superseded'))->exists();
            if (! in_array($attempt->status, ['pending', 'unknown'], true) || (! $order->isPending() && ! $superseded)
                || $order->payments()->withTrashed()->exists()) {
                return $review('terminal_attempt_conflict');
            }

            if ($order->paymentAttempts()->whereKeyNot($attempt->id)->where(fn ($query) => $query
                ->whereIn('status', ['pending', 'unknown', 'succeeded'])->orWhere('review_required', true))->exists()) {
                return $review('another_unresolved_attempt');
            }

            // A verified failure closes this checkout; any retry starts a separate journey and merchant_oid.
            $attempt->update(['status' => 'failed', 'settled_at' => now(), 'review_required' => false]);
            if ($recover) {
                $attempt->notifications()->where('outcome', 'review')->update(['outcome' => 'resolved', 'reason' => 'missing_failure_fields_resolved']);
                app(\App\Support\CheckoutJourneyTracking::class)->orderEvent($order->id, 'failure_review_resolved', ['attempt_id' => $attempt->id]);
            }
            if ($superseded) {
                // Only the old financial attempt is resolved. The replacement checkout is independent.
            } elseif ($order->trashed()) {
                // Hiding an unpaid order must not prevent an authenticated failure from resolving it.
                $unresolved = $order->paymentAttempts()->where(fn ($query) => $query
                    ->whereIn('status', ['pending', 'unknown', 'succeeded'])->orWhere('review_required', true))->exists();
                if (! $unresolved) {
                    $ids = $order->items()->pluck('apartment_id');
                    $order->items()->where('status', \App\Models\SubscriptionItem::STATUS_PENDING)
                        ->update(['status' => \App\Models\SubscriptionItem::STATUS_CANCELLED, 'ended_at' => now()]);
                    $order->update(['status' => \App\Models\UserSubscription::STATUS_CANCELLED, 'is_active' => false,
                        'cancelled_by' => \App\Models\UserSubscription::CANCELLED_BY_SYSTEM, 'ended_at' => now()]);
                    \App\Support\ApartmentCoverage::sync($ids);
                }
            } else {
                app(\App\Support\CancelSubscriptionOrder::class)->cancel($order,
                    \App\Models\UserSubscription::CANCELLED_BY_SYSTEM,
                    trim(($order->notes ?? '')."\nPayTR başarısız ödeme bildirimi alındı."));
            }
            $audit->fill(['outcome' => 'failed', 'reason' => null])->save();

            return true;
        }, 3);
    }

    public function canRecoverMissingFailureFields(SubscriptionPaymentAttempt $attempt): bool
    {
        if (! $attempt->review_required || ! in_array($attempt->status, ['pending', 'unknown'], true)) return false;
        $audits = $attempt->notifications()->get();
        return $audits->isNotEmpty() && $audits->every(fn ($audit) => $audit->provider_status === 'failed'
            && $audit->outcome === 'review'
            && VerifiedNotification::recordedFailure($audit)->mismatchReason($attempt, true) === null
            && match ($audit->reason) {
                'currency_mismatch' => $audit->reported_currency === null,
                'payment_amount_mismatch' => $audit->payment_amount_minor === null,
                'payment_type_mismatch' => $audit->reported_payment_type === null,
                default => false,
            });
    }
}
