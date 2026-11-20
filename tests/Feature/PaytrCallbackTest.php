<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\BillingProfile;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPaymentAttempt;
use App\Models\SubscriptionPaymentNotification;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\CancelSubscriptionOrder;
use App\Support\SubscriptionCheckout;
use App\Support\SubscriptionPaymentAttempts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaytrCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['paytr.merchant_key' => 'callback-test-key', 'paytr.merchant_salt' => 'callback-test-salt',
            'paytr.test_mode' => true, 'paytr.allow_test_settlement' => true]);
    }

    private function attempt(bool $isTest = true): SubscriptionPaymentAttempt
    {
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create(['unit_count' => 10, 'setup_completed_at' => now()]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        $checkout = app(SubscriptionCheckout::class);
        $checkout->openFree($user, $apartment);
        $profile = $user->billingProfiles()->create(['label' => 'Ev', 'party_type' => BillingProfile::TYPE_INDIVIDUAL,
            'legal_name' => 'Test Kullanıcı', 'identity_number' => '12345678901', 'address' => 'Adres', 'is_active' => true]);
        $order = $checkout->openPending($user, collect([$apartment]), 'monthly', 'kredi_kartı', $profile);
        app(\App\Support\CheckoutJourneyTracking::class)->attach($order, null);
        $attempt = app(SubscriptionPaymentAttempts::class)->open($order);
        $attempt->update(['submitted_at' => now(), 'is_test' => $isTest]);

        return $attempt;
    }

    private function payload(SubscriptionPaymentAttempt $attempt, array $overrides = []): array
    {
        $payload = array_replace([
            'merchant_oid' => $attempt->merchant_oid, 'status' => 'success',
            'total_amount' => (string) $attempt->amount_minor,
            'payment_amount' => (string) $attempt->amount_minor,
            'currency' => 'TL', 'payment_type' => 'card', 'test_mode' => $attempt->is_test ? '1' : '0',
        ], $overrides);
        $payload['hash'] = base64_encode(hash_hmac('sha256', $payload['merchant_oid'].config('paytr.merchant_salt').$payload['status'].$payload['total_amount'], config('paytr.merchant_key'), true));

        return $payload;
    }

    private function send(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('payments.paytr.callback'), $payload);
    }

    private function assertUnpaid(SubscriptionPaymentAttempt $attempt): void
    {
        $this->assertDatabaseCount('subscription_payments', 0);
        $this->assertSame('pending', $attempt->subscription->fresh()->status);
        $this->assertFalse($attempt->subscription->fresh()->is_active);
        $this->assertSame('pending', $attempt->subscription->items()->first()->status);
    }

    public function test_verified_card_result_is_recorded_in_journey_and_replay_preserves_one_order_number(): void
    {
        $attempt = $this->attempt();
        $journey = \App\Models\CheckoutJourney::where('order_id', $attempt->subscription_id)->sole();
        $this->assertNull($attempt->subscription->order_number);
        $this->send($this->payload($attempt))->assertOk();
        $number = $attempt->subscription->fresh()->order_number;
        $this->assertNotNull($number);
        $this->send($this->payload($attempt))->assertOk();
        $this->assertSame($number, $attempt->subscription->fresh()->order_number);
        $this->assertSame('Etkinleşti', $journey->fresh()->progress_label);
        $this->assertSame(2, $journey->events()->where('type', 'payment_notification')->count());
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.checkout-journeys.show', $journey))
            ->assertOk()->assertSee('Ödeme bildirimi alındı')->assertSee('Tekrarlanan bildirim')->assertSee($number);
    }

    public function test_card_checkout_gets_attempt_reference_first_and_order_number_only_on_verified_success(): void
    {
        $attempt = $this->attempt();
        $order = $attempt->subscription;
        $this->assertNull($order->order_number);
        $reference = $attempt->transaction_number;
        $this->assertMatchesRegularExpression('/^ODM-\d{2}-\d{6,}$/', $reference);
        $this->assertSame($reference, $order->payment_reference);
        $this->assertSame($reference, $attempt->fresh()->transaction_number);
        $forged = $this->payload($attempt);
        $forged['hash'] = str_repeat('A', 44);
        $this->send($forged)->assertForbidden();
        $this->assertNull($order->fresh()->order_number);
        $this->send($this->payload($attempt))->assertOk();
        $number = $order->fresh()->order_number;
        $this->assertMatchesRegularExpression('/^SIP-\d{2}-[A-Z0-9]+$/', $number);
        $this->assertSame($number, $order->fresh()->payment_reference);
        $this->send($this->payload($attempt))->assertOk();
        $this->assertSame($number, $order->fresh()->order_number);
        $this->assertSame($reference, $attempt->fresh()->transaction_number);
    }

    public function test_failed_card_checkout_stays_unnumbered_and_legacy_order_number_is_preserved(): void
    {
        $failed = $this->attempt();
        $this->send($this->payload($failed, ['status' => 'failed', 'total_amount' => '0', 'failed_reason_code' => '6']))->assertOk();
        $this->assertNull($failed->subscription->fresh()->order_number);
        $legacy = $this->attempt();
        $legacy->subscription->update(['order_number' => 'SIP-26-LEGACY']);
        $this->send($this->payload($legacy))->assertOk();
        $this->assertSame('SIP-26-LEGACY', $legacy->subscription->fresh()->order_number);
    }

    public function test_guest_callback_atomically_settles_and_replays_without_extending_service_twice(): void
    {
        $attempt = $this->attempt();
        $payload = $this->payload($attempt);
        $this->send($payload)->assertOk()->assertContent('OK')->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $order = $attempt->subscription->fresh();
        $this->assertSame('active', $order->status);
        $this->assertTrue($order->is_active);
        $this->assertSame('active', $order->items()->first()->status);
        $this->assertSame('succeeded', $attempt->fresh()->status);
        $this->assertFalse($attempt->fresh()->review_required);
        $payment = SubscriptionPayment::sole();
        $this->assertSame($attempt->id, $payment->payment_attempt_id);
        $this->assertSame($order->price, $payment->amount);
        $this->assertSame('kredi_kartı', $payment->payment_method);
        $end = $order->expires_at->toISOString();
        $payment->delete();
        $this->travel(2)->days();
        $this->send($payload)->assertOk()->assertContent('OK');
        $this->assertSame($end, $order->fresh()->expires_at->toISOString());
        $this->assertSame(1, SubscriptionPayment::withTrashed()->count());
        $this->assertDatabaseCount('subscription_payment_notifications', 1);
        $this->assertSame(2, SubscriptionPaymentNotification::sole()->deliveries);
    }

    public function test_missing_configuration_fails_closed_and_card_ui_stays_disabled(): void
    {
        $attempt = $this->attempt();
        $payload = $this->payload($attempt);
        config(['paytr.merchant_key' => null]);
        $this->send($payload)->assertStatus(503);
        $this->assertUnpaid($attempt);
        $this->assertFalse(config('payments.card_enabled'));
    }

    public function test_production_accepts_only_allowlisted_test_order_owners_with_signup_closed(): void
    {
        config(['registration.enabled' => false, 'paytr.test_accounts_only' => true,
            'paytr.test_account_emails' => ['aidatcep01@ko.com.tr', 'aidatcep02@ko.com.tr']]);
        app()->instance('env', 'production');
        foreach (config('paytr.test_account_emails') as $email) {
            $attempt = $this->attempt();
            $attempt->subscription->user->update(['email' => $email]);
            $this->send($this->payload($attempt))->assertOk()->assertContent('OK');
            $this->assertTrue($attempt->subscription->fresh()->is_active);
        }
        $other = $this->attempt();
        $other->update(['payer_snapshot' => ['payer_email' => 'aidatcep01@ko.com.tr']]);
        $this->send($this->payload($other))->assertStatus(409);
        $this->assertFalse($other->subscription->fresh()->is_active);
        $this->assertDatabaseCount('subscription_payments', 2);
    }

    public function test_callback_has_no_browser_middleware_and_validates_without_csrf_or_session(): void
    {
        $route = app('router')->getRoutes()->getByName('payments.paytr.callback');
        $this->assertSame([], $route->gatherMiddleware());
        $this->send($this->payload($this->attempt()))->assertOk()->assertContent('OK');
    }

    public function test_renewal_extends_current_period_once_and_replay_after_cancellation_does_not_reactivate(): void
    {
        $first = $this->attempt();
        $this->send($this->payload($first))->assertOk();
        $firstOrder = $first->subscription->fresh();
        $apartment = $firstOrder->items()->first()->apartment;
        $renewal = app(SubscriptionCheckout::class)->openPending($firstOrder->user, collect([$apartment]), 'monthly', 'kredi_kartı', $firstOrder->billingProfile);
        $attempt = app(SubscriptionPaymentAttempts::class)->open($renewal);
        $attempt->update(['submitted_at' => now()]);
        $payload = $this->payload($attempt);
        $this->send($payload)->assertOk();
        $renewal->refresh();
        $this->assertTrue($renewal->started_at->equalTo($firstOrder->expires_at));
        $end = $renewal->expires_at->toISOString();
        $this->send($payload)->assertOk();
        $this->assertSame($end, $renewal->fresh()->expires_at->toISOString());
        $this->assertDatabaseCount('subscription_payments', 2);
        $renewal->update(['status' => 'cancelled', 'is_active' => false]);
        $this->send($payload)->assertOk();
        $this->assertSame('cancelled', $renewal->fresh()->status);
        $this->assertFalse($renewal->fresh()->is_active);
    }

    public function test_two_unresolved_attempts_require_review_instead_of_double_settlement(): void
    {
        $attempt = $this->attempt();
        $other = $attempt->subscription->paymentAttempts()->create([
            'provider' => 'paytr', 'merchant_oid' => 'ACSECOND123', 'amount_minor' => $attempt->amount_minor,
            'currency' => 'TRY', 'is_test' => true, 'submitted_at' => now(), 'status' => 'pending',
        ]);
        $this->send($this->payload($attempt))->assertStatus(409);
        $this->send($this->payload($other))->assertStatus(409);
        $this->assertUnpaid($attempt);
        $this->assertTrue($attempt->fresh()->review_required);
        $this->assertTrue($other->fresh()->review_required);
    }

    public function test_invalid_hash_and_tampered_signed_amount_cannot_change_an_attempt(): void
    {
        $attempt = $this->attempt();
        $payload = $this->payload($attempt);
        $payload['total_amount'] = '1';
        $this->send($payload)->assertForbidden();
        $payload = $this->payload($attempt);
        $payload['hash'] = str_repeat('x', 44);
        $this->send($payload)->assertForbidden();
        $this->assertUnpaid($attempt);
        $this->assertFalse($attempt->fresh()->review_required);
        $this->assertDatabaseCount('subscription_payment_notifications', 0);
    }

    public function test_invalid_shapes_and_query_string_only_are_rejected(): void
    {
        $attempt = $this->attempt();
        foreach ([['status' => 'approved'], ['total_amount' => '-1'], ['total_amount' => '18.00'],
            ['total_amount' => '9999999999999999999999'], ['merchant_oid' => ['bad']], ['hash' => ['bad']]] as $override) {
            $payload = array_replace($this->payload($attempt), $override);
            $this->send($payload)->assertBadRequest();
        }
        $this->post(route('payments.paytr.callback').'?'.http_build_query($this->payload($attempt)))
            ->assertBadRequest();
        $this->get(route('payments.paytr.callback'))->assertStatus(405);
        $this->assertUnpaid($attempt);
    }

    public function test_unknown_oid_and_bank_transfer_attempt_cannot_be_settled(): void
    {
        $attempt = $this->attempt();
        $this->send($this->payload($attempt, ['merchant_oid' => 'UNKNOWN123']))->assertNotFound();
        $attempt->update(['provider' => 'bank_transfer']);
        $this->send($this->payload($attempt))->assertNotFound();
        $this->assertUnpaid($attempt);
    }

    public function test_paytr_timeout_notification_closes_checkout_and_releases_apartment_even_when_soft_deleted(): void
    {
        foreach ([false, true] as $deleted) {
            $attempt = $this->attempt();
            $order = $attempt->subscription;
            if ($deleted) $order->delete();
            $payload = $this->payload($attempt, ['status' => 'failed', 'total_amount' => '0', 'failed_reason_code' => '6']);
            $this->send($payload)->assertOk()->assertContent('OK');
            $this->send($payload)->assertOk()->assertContent('OK');
            $closed = UserSubscription::withTrashed()->findOrFail($order->id);
            $this->assertSame('cancelled', $closed->status);
            $this->assertSame($deleted, $closed->trashed());
            $this->assertSame('failed', $attempt->fresh()->status);
            $this->assertFalse($attempt->fresh()->review_required);
            $this->assertSame('cancelled', $closed->items()->first()->status);
            $this->assertSame('6', $attempt->notifications()->first()->failed_reason_code);
            $new = app(SubscriptionCheckout::class)->openPending($order->user, collect([$order->items()->first()->apartment]),
                'monthly', 'kredi_kartı', $order->user->billingProfiles()->first());
            $this->assertNotSame($order->id, $new->id);
        }
    }

    public function test_timeout_failure_code_alone_does_not_override_signature_or_success_status(): void
    {
        $attempt = $this->attempt();
        $forged = $this->payload($attempt, ['failed_reason_code' => '6']);
        $forged['status'] = 'failed';
        $this->send($forged)->assertForbidden();
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->send($this->payload($attempt, ['failed_reason_code' => '6']))->assertOk();
        $this->assertSame('succeeded', $attempt->fresh()->status);
    }

    public function test_documented_failure_codes_have_safe_customer_messages_and_matching_actions(): void
    {
        config(['payments.card_enabled' => true, 'paytr.test_accounts_only' => false]);
        $cases = [
            ['0', 'Ödeme tamamlanamadı', true],
            ['1', 'Doğrulama tamamlanmadı', true],
            ['2', 'Doğrulama başarısız', true],
            ['3', 'Ödeme onaylanmadı', false],
            ['6', 'Ödeme tamamlanmadı', true],
            ['8', 'Taksit seçimi uygun değil', true],
            ['9', 'Kartla ödeme onaylanmadı', true],
            ['10', '3D Secure doğrulaması gerekli', false],
            ['11', 'Ödeme onaylanmadı', false],
            ['99', 'Ödeme tamamlanamadı', false],
            ['unknown', 'Ödeme tamamlanamadı', true],
        ];
        foreach ($cases as [$code, $title, $retry]) {
            $attempt = $this->attempt();
            $order = $attempt->subscription;
            app(\App\Support\CheckoutJourneyTracking::class)->attach($order, null);
            $journey = \App\Models\CheckoutJourney::where('order_id', $order->id)->sole();
            $this->send($this->payload($attempt, ['status' => 'failed', 'total_amount' => '0',
                'failed_reason_code' => $code, 'failed_reason_msg' => 'Unsafe provider detail']))->assertOk();
            $this->assertSame('Ödeme başarısız', $journey->fresh()->progress_label);
            $this->assertSame('cancelled', $order->fresh()->status);
            $this->assertNull($order->fresh()->order_number);
            $this->assertSame(0, $order->payments()->count());
            $this->actingAs(User::factory()->admin()->create())
                ->get(route('admin.checkout-journeys.index', ['search' => $journey->reference, 'status' => 'failed']))->assertOk()
                ->assertViewHas('journeys', fn ($rows) => $rows->total() === 1)->assertSee('Ödeme başarısız');
            $this->actingAs($order->user);
            $this->get(route('subscriber.subscriptions.card-status', $order))->assertOk()
                ->assertJsonPath('state', 'failed')->assertJsonPath('title', $title)
                ->assertJsonPath('can_start', false)
                ->assertJsonPath('can_restart', $retry);
            $this->get(route('subscriber.subscriptions.card-result', $order))->assertOk()
                ->assertSee($title)->assertDontSee('Unsafe provider detail');
        }
    }

    public function test_conflicting_notification_shows_review_instead_of_failure_or_retry(): void
    {
        $attempt = $this->attempt();
        $this->send($this->payload($attempt, ['status' => 'failed', 'total_amount' => '0', 'failed_reason_code' => '6']))->assertOk();
        $this->send($this->payload($attempt))->assertStatus(409);
        $this->actingAs($attempt->subscription->user);
        $this->get(route('subscriber.subscriptions.card-status', $attempt->subscription))->assertOk()
            ->assertJsonPath('state', 'review')->assertJsonPath('can_start', false)->assertJsonPath('can_restart', false);
    }

    public function test_new_checkout_keeps_old_history_and_accepts_late_failure_without_touching_replacement(): void
    {
        $attempt = $this->attempt();
        $order = $attempt->subscription;
        $apartment = $order->items()->first()->apartment;
        $new = app(SubscriptionCheckout::class)->openPending($order->user, collect([$apartment]),
            'monthly', 'kredi_kartı', $order->user->billingProfiles()->first());
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('pending', $attempt->fresh()->status);
        $oldJourney = \App\Models\CheckoutJourney::where('order_id', $order->id)->sole();
        $this->assertSame('Yeni denemeyle değiştirildi', $oldJourney->progress_label);
        $this->actingAs($order->user)->get(route('subscriber.subscriptions.card-status', $order))->assertOk()
            ->assertJsonPath('state', 'superseded')->assertJsonPath('can_start', false)->assertJsonPath('can_restart', false);
        app(\App\Support\CheckoutJourneyTracking::class)->attach($new, null);
        $newJourney = \App\Models\CheckoutJourney::where('order_id', $new->id)->sole();
        $this->assertNotSame($oldJourney->reference, $newJourney->reference);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.checkout-journeys.index', ['status' => 'superseded']))
            ->assertOk()->assertSee($oldJourney->reference)->assertDontSee($newJourney->reference);
        $payload = $this->payload($attempt, ['status' => 'failed', 'total_amount' => '0', 'failed_reason_code' => '6']);
        $this->send($payload)->assertOk()->assertContent('OK');
        $this->send($payload)->assertOk()->assertContent('OK');
        $this->assertSame('failed', $attempt->fresh()->status);
        $this->assertFalse($attempt->fresh()->review_required);
        $this->assertSame('pending', $new->fresh()->status);
        $this->assertSame('pending', $new->items()->first()->status);
        $this->assertSame('Ödeme başarısız', $oldJourney->fresh()->progress_label);
    }

    public function test_late_success_for_replaced_attempt_is_audited_for_review_and_does_not_activate_either_checkout(): void
    {
        $attempt = $this->attempt();
        $order = $attempt->subscription;
        $new = app(SubscriptionCheckout::class)->openPending($order->user, collect([$order->items()->first()->apartment]),
            'monthly', 'kredi_kartı', $order->user->billingProfiles()->first());
        $this->send($this->payload($attempt))->assertStatus(409);
        $this->assertTrue($attempt->fresh()->review_required);
        $this->assertSame('success', $attempt->notifications()->sole()->provider_status);
        $this->assertSame('review', $attempt->notifications()->sole()->outcome);
        $this->assertSame('pending', $new->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_another_customers_attempt_is_not_replaced_even_if_new_user_also_manages_apartment(): void
    {
        $attempt = $this->attempt();
        $order = $attempt->subscription;
        $apartment = $order->items()->first()->apartment;
        $other = User::factory()->create();
        $apartment->members()->attach($other->id, ['role' => 'owner', 'is_active' => true]);
        $profile = $other->billingProfiles()->create(['label' => 'Ev', 'party_type' => 'individual',
            'legal_name' => 'Diğer kişi', 'address' => 'Adres', 'is_active' => true]);
        try {
            app(SubscriptionCheckout::class)->openPending($other, collect([$apartment]), 'monthly', 'kredi_kartı', $profile);
            $this->fail('Another customer payment history must not be superseded.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_method', $exception->errors());
        }
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->checkout_abandoned_at);
    }

    public function test_invalid_new_billing_profile_does_not_close_previous_checkout(): void
    {
        $attempt = $this->attempt();
        $order = $attempt->subscription;
        $profile = $order->user->billingProfiles()->first();
        $profile->update(['is_active' => false]);
        try {
            app(SubscriptionCheckout::class)->openPending($order->user, collect([$order->items()->first()->apartment]),
                'monthly', 'kredi_kartı', $profile);
            $this->fail('Invalid replacement must leave old checkout unchanged.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('billing_profile_id', $exception->errors());
        }
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->checkout_abandoned_at);
    }

    public function test_reviewed_attempt_is_not_automatically_replaced(): void
    {
        $attempt = $this->attempt();
        $attempt->update(['review_required' => true]);
        $order = $attempt->subscription;
        try {
            app(SubscriptionCheckout::class)->openPending($order->user, collect([$order->items()->first()->apartment]),
                'monthly', 'kredi_kartı', $order->user->billingProfiles()->first());
            $this->fail('Financial review must be resolved before replacement.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_method', $exception->errors());
        }
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->checkout_abandoned_at);
    }

    public function test_decline_closes_checkout_and_retry_has_a_new_checkout_and_oid(): void
    {
        $attempt = $this->attempt();
        $payload = $this->payload($attempt, ['status' => 'failed', 'total_amount' => '0', 'failed_reason_code' => '0', 'failed_reason_msg' => 'Never store this raw message']);
        $this->send($payload)->assertOk()->assertContent('OK');
        $this->send($payload)->assertOk()->assertContent('OK');
        $this->assertSame(0, $attempt->subscription->payments()->count());
        $this->assertSame('cancelled', $attempt->subscription->fresh()->status);
        $this->assertSame('cancelled', $attempt->subscription->items()->first()->status);
        $this->assertSame('failed', $attempt->fresh()->status);
        $this->assertFalse($attempt->fresh()->review_required);
        $this->assertSame('0', SubscriptionPaymentNotification::sole()->failed_reason_code);
        $order = $attempt->subscription;
        $new = app(SubscriptionCheckout::class)->openPending($order->user, collect([$order->items()->first()->apartment]),
            'monthly', 'kredi_kartı', $order->user->billingProfiles()->first());
        $this->assertNotSame($order->id, $new->id);
        $retry = app(SubscriptionPaymentAttempts::class)->open($new);
        $this->assertNotSame($attempt->merchant_oid, $retry->merchant_oid);
        $retry->update(['submitted_at' => now()]);
        $this->send($payload)->assertOk();
        $this->send($this->payload($retry))->assertOk();
        $this->assertDatabaseCount('subscription_payments', 1);
    }

    public function test_signed_wrong_totals_are_held_for_review_and_not_released_by_a_later_callback(): void
    {
        foreach ([-1, 1] as $difference) {
            $attempt = $this->attempt();
            $this->send($this->payload($attempt, ['total_amount' => (string) ($attempt->amount_minor + $difference)]))->assertStatus(409);
            $this->send($this->payload($attempt))->assertStatus(409);
            $this->assertUnpaid($attempt);
            $this->assertSame('unknown', $attempt->fresh()->status);
            $this->assertTrue($attempt->fresh()->review_required);
            try {
                app(SubscriptionPaymentAttempts::class)->open($attempt->subscription);
                $this->fail('Reviewed attempts must block retries.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('subscription', $exception->errors());
            }
        }
    }

    public function test_unsigned_metadata_never_overrides_local_attempt_values(): void
    {
        foreach ([['currency' => 'USD'], ['payment_amount' => '1'], ['test_mode' => '0'], ['payment_type' => 'eft']] as $override) {
            $attempt = $this->attempt();
            $payload = array_replace($this->payload($attempt), $override); // Original valid HMAC still matches.
            $this->send($payload)->assertStatus(409);
            $this->assertUnpaid($attempt);
            $this->assertTrue($attempt->fresh()->review_required);
        }
    }

    public function test_test_settlement_requires_explicit_isolated_environment_and_never_activates_production(): void
    {
        $attempt = $this->attempt();
        config(['paytr.allow_test_settlement' => false]);
        $this->send($this->payload($attempt))->assertStatus(409);
        $this->assertUnpaid($attempt);
        config(['paytr.allow_test_settlement' => true]);
        $productionAttempt = $this->attempt();
        app()->instance('env', 'production');
        $this->send($this->payload($productionAttempt))->assertStatus(409);
        $this->assertUnpaid($productionAttempt);
    }

    public function test_live_attempt_accepts_omitted_optional_test_mode_and_ignores_current_launch_switch(): void
    {
        $attempt = $this->attempt(false);
        $payload = $this->payload($attempt);
        unset($payload['test_mode']);
        config(['payments.card_enabled' => false, 'paytr.test_mode' => true]);
        $this->send($payload)->assertOk()->assertContent('OK');
        $this->assertSame('succeeded', $attempt->fresh()->status);
    }

    public function test_conflicting_results_do_not_reverse_success_or_activate_failed_attempt(): void
    {
        $success = $this->attempt();
        $this->send($this->payload($success))->assertOk();
        $end = $success->subscription->fresh()->expires_at->toISOString();
        $this->send($this->payload($success, ['status' => 'failed', 'total_amount' => '0']))->assertStatus(409);
        $this->assertSame('succeeded', $success->fresh()->status);
        $this->assertTrue($success->fresh()->review_required);
        $this->assertSame($end, $success->subscription->fresh()->expires_at->toISOString());
        $failed = $this->attempt();
        $this->send($this->payload($failed, ['status' => 'failed', 'total_amount' => '0']))->assertOk();
        $this->send($this->payload($failed))->assertStatus(409);
        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertTrue($failed->fresh()->review_required);
        $this->assertFalse($failed->subscription->fresh()->is_active);
        $this->assertDatabaseCount('subscription_payments', 1);
    }

    public function test_unsubmitted_cancelled_deleted_or_changed_order_cannot_be_activated(): void
    {
        foreach (['unsubmitted', 'cancelled', 'deleted', 'price', 'items', 'method', 'archived_apartment', 'ended_subscription'] as $case) {
            $attempt = $this->attempt();
            $order = $attempt->subscription;
            match ($case) {
                'unsubmitted' => $attempt->update(['submitted_at' => null]),
                'cancelled' => $order->update(['status' => UserSubscription::STATUS_CANCELLED]),
                'deleted' => $order->delete(),
                'price' => $order->update(['price' => '0.01']),
                'items' => $order->items()->update(['amount' => '0.01']),
                'method' => $order->update(['payment_method' => 'havale']),
                'archived_apartment' => $order->items()->first()->apartment->update(['is_active' => false]),
                'ended_subscription' => $order->items()->first()->apartmentSubscription->update(['status' => 'ended']),
            };
            $this->send($this->payload($attempt))->assertStatus(409);
            $this->assertFalse($order->fresh()->is_active);
            $this->assertTrue($attempt->fresh()->review_required);
        }
        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_activation_and_payment_roll_back_if_notification_audit_cannot_be_saved(): void
    {
        $attempt = $this->attempt();
        SubscriptionPaymentNotification::creating(function () { throw new \RuntimeException('Simulated audit storage failure'); });
        try {
            $payload = $this->payload($attempt);
            $this->send($payload)->assertStatus(503);
            $this->assertUnpaid($attempt);
            $this->assertSame('pending', $attempt->fresh()->status);
        } finally {
            SubscriptionPaymentNotification::flushEventListeners();
        }
        $this->send($payload)->assertOk()->assertContent('OK');
        $this->assertDatabaseCount('subscription_payments', 1);
    }

    public function test_reviewed_failed_attempt_cannot_be_cancelled_manually(): void
    {
        $attempt = $this->attempt();
        $this->send($this->payload($attempt, ['status' => 'failed', 'total_amount' => '0']))->assertOk();
        $this->send($this->payload($attempt))->assertStatus(409);
        $this->expectException(ValidationException::class);
        app(CancelSubscriptionOrder::class)->cancel($attempt->subscription, UserSubscription::CANCELLED_BY_ADMIN);
    }

    public function test_soft_deleting_pending_paytr_order_requires_password_and_new_checkout_preserves_old_attempt(): void
    {
        $attempt = $this->attempt();
        $order = $attempt->subscription;
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $this->delete(route('admin.orders.destroy', $order), ['current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        $this->assertNotSoftDeleted($order);
        $this->delete(route('admin.orders.destroy', $order), ['current_password' => 'password'])
            ->assertRedirect(route('admin.orders.index'));
        $this->assertSoftDeleted($order);
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertStringContainsString('Admin pasif silme: '.$admin->id, UserSubscription::withTrashed()->findOrFail($order->id)->notes);
        $this->get(route('admin.orders.index'))->assertViewHas('orders', fn ($orders) => $orders->total() === 0);
        $this->get(route('subscriber.subscriptions.receipt', $order))->assertNotFound();

        $new = app(SubscriptionCheckout::class)->openPending($order->user, collect([$order->items()->first()->apartment]),
            'monthly', 'kredi_kartı', $order->user->billingProfiles()->first());
        $this->assertNotSame($new->id, $order->id);
        $this->assertSoftDeleted($order);
        $this->assertSame('pending', $attempt->fresh()->status);
    }

    public function test_admin_closes_deleted_test_order_and_can_start_again_while_late_success_requires_review(): void
    {
        $attempt = $this->attempt();
        $order = $attempt->subscription;
        $order->delete();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $this->get(route('admin.orders.index', ['status' => 'deleted']))
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 1);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Test girişimini kapat');
        $this->post(route('admin.orders.close-test', $order), ['current_password' => 'password', 'test_close_reason' => 'Eski test'])
            ->assertRedirect(route('admin.orders.show', $order));
        $closed = UserSubscription::withTrashed()->findOrFail($order->id);
        $this->assertTrue($closed->trashed());
        $this->assertSame('cancelled', $closed->status);
        $this->assertSame('cancelled', $attempt->fresh()->status);
        $this->assertSame('cancelled', $closed->items()->first()->status);
        $this->assertStringContainsString('Eski test', $closed->notes);
        $new = app(SubscriptionCheckout::class)->openPending($order->user, collect([$order->items()->first()->apartment]),
            'monthly', 'kredi_kartı', $order->user->billingProfiles()->first());
        $this->assertNotSame($order->id, $new->id);
        $this->send($this->payload($attempt))->assertStatus(409);
        $this->assertTrue($attempt->fresh()->review_required);
        $this->assertSame(0, $closed->payments()->count());
        $this->assertSame('pending', $new->fresh()->status);
    }

    public function test_test_close_requires_admin_password_reason_and_rejects_live_or_paid_attempts(): void
    {
        $attempt = $this->attempt();
        $order = $attempt->subscription;
        $url = route('admin.orders.close-test', $order);
        $this->actingAs($order->user)->post($url, ['current_password' => 'password', 'test_close_reason' => 'Test'])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create());
        $this->post($url, ['current_password' => 'wrong', 'test_close_reason' => 'Test'])->assertSessionHasErrors('current_password');
        $this->post($url, ['current_password' => 'password'])->assertSessionHasErrors('test_close_reason');
        $attempt->update(['is_test' => false]);
        $this->post($url, ['current_password' => 'password', 'test_close_reason' => 'Test'])->assertSessionHasErrors('test_checkout');
        $this->assertSame('pending', $attempt->fresh()->status);
        $attempt->update(['is_test' => true]);
        $this->send($this->payload($attempt))->assertOk();
        $this->post($url, ['current_password' => 'password', 'test_close_reason' => 'Test'])->assertSessionHasErrors('test_checkout');
        $this->assertSame('succeeded', $attempt->fresh()->status);
    }

    public function test_admin_can_soft_delete_reviewed_order_without_losing_payment_evidence(): void
    {
        $attempt = $this->attempt();
        $this->send($this->payload($attempt, ['total_amount' => '1']))->assertStatus(409);
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.orders.show', $attempt->subscription))->assertOk()
            ->assertSee('PayTR ödeme işlemleri')->assertSee('Ödeme incelemesi gerekiyor.')
            ->assertSee('total_amount_mismatch')->assertSee($attempt->merchant_oid);
        $order = $attempt->subscription;
        $this->delete(route('admin.orders.destroy', $order), ['current_password' => 'password', 'deletion_reason' => 'Eski test kaydı'])
            ->assertRedirect(route('admin.orders.index'));
        $this->assertSoftDeleted($order);
        $this->assertSame('unknown', $attempt->fresh()->status);
        $this->assertTrue($attempt->fresh()->review_required);
        $this->assertStringContainsString('Eski test kaydı', UserSubscription::withTrashed()->findOrFail($order->id)->notes);
        $this->assertSame('pending', $order->items()->first()->status);
        $this->send($this->payload($attempt))->assertStatus(409);
        $this->assertSame(0, $order->payments()->count());
    }
}
