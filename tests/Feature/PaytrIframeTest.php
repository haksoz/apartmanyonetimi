<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\SubscriptionPaymentAttempt;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\LegalConsent;
use App\Support\Paytr\StartIframe;
use App\Support\SubscriptionCheckout;
use App\Support\SubscriptionPaymentAttempts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaytrIframeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.card_enabled' => true, 'paytr.merchant_id' => '123456', 'paytr.merchant_key' => 'iframe-test-key',
            'paytr.merchant_salt' => 'iframe-test-salt', 'paytr.public_url' => 'https://payments.example.com',
            'paytr.test_mode' => true, 'paytr.allow_test_settlement' => true]);
        Http::preventStrayRequests();
    }

    private function order(): UserSubscription
    {
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create(['unit_count' => 10, 'setup_completed_at' => now()]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        $checkout = app(SubscriptionCheckout::class);
        $checkout->openFree($user, $apartment);
        $profile = $user->billingProfiles()->create(['label' => 'Ev', 'party_type' => 'individual', 'legal_name' => 'Ad Soyad', 'identity_number' => '12345678901', 'address' => 'Adres', 'is_active' => true]);
        $order = $checkout->openPending($user, collect([$apartment]), 'monthly', 'kredi_kartı', $profile);
        app(LegalConsent::class)->recordSale($user, $order, Request::create('/'));
        app(SubscriptionPaymentAttempts::class)->open($order)->update(['payer_snapshot' => [
            'payer_name' => 'Ad Soyad', 'payer_email' => 'payer@example.com', 'payer_phone' => '05551112233', 'payer_address' => 'Adres',
        ]]);
        $this->actingAs($user);

        return $order;
    }

    private function start(UserSubscription $order): SubscriptionPaymentAttempt
    {
        return app(StartIframe::class)->start($order, '8.8.8.8');
    }

    private function assertUnpaid(UserSubscription $order): void
    {
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertFalse($order->fresh()->is_active);
        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_token_request_is_signed_from_snapshots_and_sent_once_outside_reservation_transaction(): void
    {
        $order = $this->order();
        $level = DB::transactionLevel();
        Http::fake(function ($request) use ($order, $level) {
            $this->assertSame($level, DB::transactionLevel());
            $attempt = $order->paymentAttempts()->sole();
            $this->assertNotNull($attempt->submitted_at);
            $this->assertSame('unknown', $attempt->status);
            $this->assertSame(StartIframe::ENDPOINT, $request->url());
            $this->assertSame((string) $attempt->amount_minor, $request['payment_amount']);
            $this->assertSame('1', $request['no_installment']);
            $this->assertSame('TL', $request['currency']);
            $this->assertSame('1', $request['test_mode']);
            $this->assertSame('0', $request['debug_on']);
            $this->assertStringStartsWith('https://payments.example.com/', $request['merchant_ok_url']);
            $this->assertSame($request['merchant_ok_url'], $request['merchant_fail_url']);
            $basket = json_decode(base64_decode($request['user_basket']), true);
            $this->assertSame($order->price, $basket[0][1]);
            $input = '123456'.'8.8.8.8'.$attempt->merchant_oid.'payer@example.com'.$attempt->amount_minor.$request['user_basket'].'1'.'0'.'TL'.'1'.'iframe-test-salt';
            $this->assertSame(base64_encode(hash_hmac('sha256', $input, 'iframe-test-key', true)), $request['paytr_token']);

            return Http::response(['status' => 'success', 'token' => str_repeat('a', 56)]);
        });
        $attempt = $this->start($order);
        $this->assertSame('pending', $attempt->status);
        $this->assertSame(str_repeat('a', 56), $attempt->iframe_token);
        $this->assertNotSame($attempt->iframe_token, $attempt->getRawOriginal('iframe_token'));
        $this->assertArrayNotHasKey('iframe_token', $attempt->toArray());
        $this->assertArrayNotHasKey('payer_snapshot', $attempt->toArray());
        $this->start($order);
        Http::assertSentCount(1);
        $this->assertUnpaid($order);
    }

    public function test_final_checkout_post_continues_pending_order_directly_into_iframe(): void
    {
        $order = $this->order();
        $apartment = $order->items()->first()->apartment;
        Http::fake([StartIframe::ENDPOINT => Http::response(['status' => 'success', 'token' => str_repeat('e', 56)])]);
        $payload = ['apartment_ids' => [$apartment->id], 'period' => 'monthly', 'payment_method' => 'kredi_kartı',
            'billing_mode' => 'existing', 'billing_profile_id' => $order->billing_profile_id,
            'checkout_token' => (string) \Illuminate\Support\Str::uuid(),
            'quote_signature' => app(\App\Support\CheckoutQuote::class)->signature($order->user, $apartment),
            'payer_name' => 'Ad Soyad', 'payer_email' => 'payer@example.com', 'payer_phone' => '05551112233', 'payer_address' => 'Adres', 'accept_sales' => 1, 'checkout_return' => 'dashboard'];
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->post(route('subscriber.subscriptions.store'), $payload)
            ->assertRedirect(route('subscriber.subscriptions.card', $order));
        $this->post(route('subscriber.subscriptions.store'), $payload)
            ->assertRedirect(route('subscriber.subscriptions.card', $order));
        $this->get(route('subscriber.subscriptions.card', $order))->assertOk()->assertSee('PayTR güvenli kart ödeme formu')
            ->assertSee('id="apartment-setup-close" href="'.route('dashboard').'"', false);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('user_subscriptions', 1);
        $this->assertUnpaid($order);
    }

    public function test_missing_sale_acceptance_blocks_token_request(): void
    {
        $order = $this->order();
        $order->legalAcceptances()->delete();
        try {
            $this->start($order);
            $this->fail('Sale acceptance is required before external request.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('card_payment', $exception->errors());
        }
        Http::assertNothingSent();
        $this->assertNull($order->paymentAttempts()->sole()->submitted_at);
    }

    public function test_test_account_mode_cannot_launch_a_preexisting_live_attempt(): void
    {
        $order = $this->order();
        $order->user->update(['email' => 'aidatcep01@ko.com.tr']);
        config(['paytr.test_accounts_only' => true, 'paytr.test_account_emails' => ['aidatcep01@ko.com.tr']]);
        $order->paymentAttempts()->sole()->update(['is_test' => false]);
        try {
            $this->start($order);
            $this->fail('An old live attempt must not bypass test-only mode.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('card_payment', $exception->errors());
        }
        Http::assertNothingSent();
        $this->get(route('subscriber.subscriptions.card-status', $order))->assertJsonPath('can_start', false);
    }

    public function test_timeout_remains_unknown_and_never_reissues_http_or_new_attempt(): void
    {
        $order = $this->order();
        $requests = 0;
        Http::fake(function () use (&$requests) {
            $requests++;
            throw new ConnectionException('Do not log raw transport details');
        });
        $first = $this->start($order);
        $second = $this->start($order);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('unknown', $second->status);
        $this->assertSame(1, $requests);
        $this->assertNull($second->iframe_token);
        $this->assertDatabaseCount('subscription_payment_attempts', 1);
        $this->assertUnpaid($order);
    }

    public function test_token_rejection_allows_new_oid_and_copies_payer_for_explicit_retry(): void
    {
        $order = $this->order();
        Http::fakeSequence()->push(['status' => 'failed', 'reason' => 'Provider internal details'])
            ->push(['status' => 'success', 'token' => str_repeat('b', 56)]);
        $first = $this->start($order);
        $this->assertSame('failed', $first->status);
        $second = $this->start($order);
        $this->assertNotSame($first->merchant_oid, $second->merchant_oid);
        $this->assertSame('payer@example.com', $second->payer_snapshot['payer_email']);
        $this->assertSame('pending', $second->status);
        Http::assertSentCount(2);
        $this->assertUnpaid($order);
    }

    public function test_malformed_success_and_http_error_remain_unknown(): void
    {
        foreach ([['status' => 'success', 'token' => '../evil'], ['status' => 'unexpected'], null] as $result) {
            $order = $this->order();
            Http::fake(fn () => Http::response($result, $result === null ? 503 : 200));
            $this->assertSame('unknown', $this->start($order)->status);
            $this->assertUnpaid($order);
        }
    }

    public function test_missing_keys_or_non_public_origin_or_private_ip_block_external_requests(): void
    {
        foreach ([['paytr.merchant_key', null], ['paytr.public_url', 'http://kapitalonlineapartmanyonetim.test'],
            ['paytr.public_url', 'https://localhost'], ['paytr.public_url', 'https://127.0.0.1'],
            ['paytr.public_url', 'https://example.com/path'], ['payments.card_enabled', false]] as [$key, $value]) {
            $order = $this->order();
            $old = config($key);
            config([$key => $value]);
            try {
                $this->start($order);
                $this->fail('Unconfigured checkout must not call PayTR.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('card_payment', $exception->errors());
            } finally {
                config([$key => $old]);
            }
        }
        try {
            app(StartIframe::class)->start($this->order(), '127.0.0.1');
            $this->fail('Private client IP is not usable for PayTR.');
        } catch (ValidationException) {}
        Http::assertNothingSent();
    }

    public function test_payment_pages_are_read_only_and_private_and_cannot_forge_success(): void
    {
        $order = $this->order();
        $this->get(route('subscriber.subscriptions.card-result', $order).'?status=success')
            ->assertOk()->assertSee('Kartla ödeme işleminize')->assertDontSee('Ödemeniz onaylandı.');
        $this->get(route('subscriber.subscriptions.card-status', $order))->assertOk()->assertJsonPath('state', 'ready');
        $this->get(route('subscriber.subscriptions.card', $order))->assertRedirect(route('subscriber.subscriptions.card-result', $order));
        $this->assertUnpaid($order);
        Http::assertNothingSent();
        $this->actingAs(User::factory()->create());
        // Ownership is also checked independently of the commerce menu middleware.
        $this->get(route('subscriber.subscriptions.card-result', $order))->assertForbidden();
        $this->get(route('subscriber.subscriptions.card-status', $order))->assertForbidden();
        $this->post(route('subscriber.subscriptions.card.start', $order))->assertForbidden();
    }

    public function test_cached_iframe_is_reused_and_expired_token_never_starts_another_charge(): void
    {
        $order = $this->order();
        Http::fake([StartIframe::ENDPOINT => Http::response(['status' => 'success', 'token' => str_repeat('c', 56)])]);
        $attempt = $this->start($order);
        $this->get(route('subscriber.subscriptions.card', $order))->assertOk()
            ->assertSee('https://www.paytr.com/odeme/guvenli/'.str_repeat('c', 56), false)
            ->assertSee('PayTR güvenli kart ödeme formu')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->travel(31)->minutes();
        $this->get(route('subscriber.subscriptions.card', $order))->assertRedirect(route('subscriber.subscriptions.card-result', $order));
        $this->get(route('subscriber.subscriptions.card-status', $order))->assertJsonPath('state', 'waiting')->assertJsonPath('can_start', false);
        $this->start($order);
        Http::assertSentCount(1);
        $this->assertUnpaid($order);
    }

    public function test_callback_can_win_race_with_token_response_and_is_the_only_activation_source(): void
    {
        $order = $this->order();
        Http::fake(function ($request) use ($order) {
            $attempt = $order->paymentAttempts()->sole();
            $payload = ['merchant_oid' => $attempt->merchant_oid, 'status' => 'success', 'total_amount' => (string) $attempt->amount_minor,
                'payment_amount' => (string) $attempt->amount_minor, 'currency' => 'TL', 'payment_type' => 'card', 'test_mode' => '1'];
            $payload['hash'] = base64_encode(hash_hmac('sha256', $attempt->merchant_oid.'iframe-test-salt'.'success'.$attempt->amount_minor, 'iframe-test-key', true));
            $this->post(route('payments.paytr.callback'), $payload)->assertOk()->assertContent('OK');

            return Http::response(['status' => 'success', 'token' => str_repeat('d', 56)]);
        });
        $attempt = $this->start($order);
        $this->assertSame('succeeded', $attempt->status);
        $this->assertNull($attempt->iframe_token);
        $this->get(route('subscriber.subscriptions.card-status', $order))->assertJsonPath('state', 'paid')->assertJsonPath('can_start', false);
        $this->assertDatabaseCount('subscription_payments', 1);
    }
}
