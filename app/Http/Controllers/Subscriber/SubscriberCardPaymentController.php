<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
use App\Support\Paytr\PaymentState;
use App\Support\Paytr\StartIframe;
use Illuminate\Http\Request;

class SubscriberCardPaymentController extends Controller
{
    private function owned(UserSubscription $subscription): void
    {
        abort_unless((int) $subscription->user_id === (int) auth()->id() && $subscription->payment_method === 'kredi_kartı', 403);
    }

    public function start(Request $request, UserSubscription $subscription, StartIframe $iframe)
    {
        $this->owned($subscription);
        $iframe->start($subscription, (string) $request->ip());

        return redirect()->route('subscriber.subscriptions.card', $subscription);
    }

    public function show(UserSubscription $subscription, PaymentState $states)
    {
        $this->owned($subscription);
        if ($subscription->checkout_abandoned_at) {
            return redirect()->route('subscriber.subscriptions.create', ['apartment_id' => $subscription->items()->value('apartment_id')]);
        }
        $state = $states->forOrder($subscription);
        $attempt = $subscription->paymentAttempts()->where('provider', 'paytr')->latest('id')->first();
        if ($state['state'] !== 'ready' || ! app(\App\Support\Paytr\Access::class)->canStart(auth()->user()) || ! $attempt?->iframe_token
            || (config('paytr.test_accounts_only') && ! $attempt->is_test)
            || ! $attempt->token_expires_at?->isFuture()) {
            return redirect()->route('subscriber.subscriptions.card-result', $subscription);
        }

        app(\App\Support\CheckoutJourneyTracking::class)->orderEvent($subscription->id, 'card_screen', ['attempt_id' => $attempt->id]);

        return response()->view('subscriber.subscriptions.card', compact('subscription', 'attempt'))
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function result(UserSubscription $subscription, PaymentState $states)
    {
        $this->owned($subscription);
        $paymentState = $states->forOrder($subscription);

        return response()->view('subscriber.subscriptions.card-result', compact('subscription', 'paymentState'))
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function status(UserSubscription $subscription, PaymentState $states)
    {
        $this->owned($subscription);

        return response()->json($states->forOrder($subscription))->header('Cache-Control', 'no-store, private');
    }

    public function abandon(Request $request, UserSubscription $subscription, \App\Support\Paytr\AbandonCheckout $checkout)
    {
        $this->owned($subscription);
        $data = $request->validate(['reason' => ['nullable', \Illuminate\Validation\Rule::in(['timeout', 'timeout_dismissed'])]]);
        $checkout->abandon($subscription);
        $subscription->refresh();
        if ($subscription->checkout_abandoned_at && isset($data['reason'])) {
            $type = $data['reason'] === 'timeout' ? 'card_timeout_closed' : 'card_timeout_dismissed';
            foreach (\App\Models\CheckoutJourney::where('order_id', $subscription->id)->get() as $journey) {
                $journey->events()->firstOrCreate(['type' => $type], ['source' => 'browser', 'details' => []]);
            }
        }
        $redirect = $subscription->status === UserSubscription::STATUS_ACTIVE
            ? route('subscriber.subscriptions.receipt', $subscription)
            : route(session('subscription_return.'.$subscription->id) === 'dashboard' ? 'dashboard' : 'subscriber.dashboard');

        return $request->expectsJson() ? response()->json(['redirect' => $redirect]) : redirect($redirect);
    }

    public function timer(Request $request, UserSubscription $subscription, PaymentState $states)
    {
        $this->owned($subscription);
        $data = $request->validate(['type' => ['required', \Illuminate\Validation\Rule::in(['card_timeout_prompt', 'card_timeout_renewed'])]]);
        if ($states->forOrder($subscription)['state'] !== 'ready') {
            return response()->json(['redirect' => route('subscriber.subscriptions.card-result', $subscription)], 409);
        }
        app(\App\Support\CheckoutJourneyTracking::class)->orderEvent($subscription->id, $data['type'], []);

        return response()->noContent();
    }
}
