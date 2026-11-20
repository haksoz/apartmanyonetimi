<?php

namespace App\Observers;

use App\Models\SubscriptionPaymentAttempt;
use App\Models\SubscriptionPaymentNotification;
use App\Models\UserSubscription;
use App\Support\CheckoutJourneyTracking;
use Illuminate\Database\Eloquent\Model;

class CheckoutAuditObserver
{
    public function created(Model $model): void
    {
        $this->record($model, true);
    }

    public function updated(Model $model): void
    {
        $this->record($model, false);
    }

    private function record(Model $model, bool $created): void
    {
        $tracking = app(CheckoutJourneyTracking::class);
        if ($model instanceof UserSubscription && $model->wasChanged(['status', 'checkout_abandoned_at', 'order_number'])) {
            $tracking->orderEvent($model->id, 'order_updated', $model->only(['status', 'order_number', 'checkout_abandoned_at']));
        } elseif ($model instanceof SubscriptionPaymentAttempt && ($created || $model->wasChanged(['status', 'submitted_at', 'review_required', 'token_failure_code']))) {
            $tracking->orderEvent($model->subscription_id, 'payment_attempt', $model->only(['id', 'provider', 'status', 'is_test', 'submitted_at', 'review_required', 'token_failure_code']));
        } elseif ($model instanceof SubscriptionPaymentNotification) {
            $orderId = $model->attempt?->subscription_id;
            if ($orderId) {
                $tracking->orderEvent($orderId, 'payment_notification', $model->only(['provider_status', 'outcome', 'reason', 'failed_reason_code', 'deliveries']));
            }
        }
    }
}
