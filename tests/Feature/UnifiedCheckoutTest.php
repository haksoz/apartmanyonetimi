<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\BillingProfile;
use App\Models\LegalAcceptance;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPaymentAttempt;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\CheckoutQuote;
use App\Support\FinalizeSubscriptionPayment;
use App\Support\LegalConsent;
use App\Support\PaymentAmount;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnifiedCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): array
    {
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create(['unit_count' => 10, 'setup_completed_at' => now()]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        app(SubscriptionCheckout::class)->openFree($user, $apartment);
        $payload = [
            'apartment_ids' => [$apartment->id], 'period' => 'monthly', 'payment_method' => 'havale',
            'accept_sales' => 1, 'checkout_token' => (string) Str::uuid(),
            'quote_signature' => app(CheckoutQuote::class)->signature($user, $apartment),
        ] + $this->billingSelection($user);
        $this->actingAs($user);
        return [$user, $apartment, $payload];
    }

    public function test_all_entry_points_use_one_popup_and_get_requests_create_no_order(): void
    {
        [$user, $apartment] = $this->customer();
        $this->get(route('subscriber.dashboard'))->assertOk()
            ->assertSee(route('subscriber.subscriptions.create', ['apartment_id' => $apartment->id]), false)
            ->assertDontSee('data-pay-modal', false);
        $this->get(route('subscriber.subscriptions.create', ['apartment_id' => $apartment->id]))
            ->assertOk()->assertSee('id="subscription-checkout"', false)
            ->assertSee('name="checkout_token"', false)->assertSee('name="quote_signature"', false)
            ->assertSee('value="kredi_kartı" class="mt-1" disabled', false)
            ->assertSee('Siparişi Onayla')->assertDontSee('id="sidebar"', false);
        $this->get(route('subscriber.subscriptions.create', ['type' => 'renew']))
            ->assertOk()->assertSee('id="subscription-checkout"', false);
        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->assertDatabaseCount('subscription_payment_attempts', 0);
    }

    public function test_repeated_post_returns_same_order_with_single_consent_pair_and_attempt(): void
    {
        [$user, $apartment, $payload] = $this->customer();
        $this->post(route('subscriber.subscriptions.store'), $payload)->assertRedirect();
        $order = UserSubscription::sole();
        $this->post(route('subscriber.subscriptions.store'), $payload)
            ->assertRedirect(route('subscriber.subscriptions.receipt', $order));
        $this->assertDatabaseCount('user_subscriptions', 1);
        $this->assertDatabaseCount('legal_acceptances', 2);
        $this->assertDatabaseCount('subscription_payment_attempts', 1);
        $attempt = SubscriptionPaymentAttempt::sole();
        $this->assertSame(PaymentAmount::minor($order->price), $attempt->amount_minor);
        $this->assertSame('bank_transfer', $attempt->provider);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{1,64}$/', $attempt->merchant_oid);
        $this->assertFalse($order->is_active);
        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_reused_token_with_changed_payload_is_rejected(): void
    {
        [, , $payload] = $this->customer();
        $this->post(route('subscriber.subscriptions.store'), $payload)->assertRedirect();
        $payload['period'] = 'yearly';
        $this->post(route('subscriber.subscriptions.store'), $payload)->assertSessionHasErrors('checkout_token');
        $this->assertDatabaseCount('user_subscriptions', 1);
    }

    public function test_price_change_requires_new_approval_and_creates_no_financial_records(): void
    {
        [$user, $apartment, $payload] = $this->customer();
        \App\Models\PriceBand::query()->update(['monthly_price' => 999]);
        $this->assertNotSame($payload['quote_signature'], app(CheckoutQuote::class)->signature($user, $apartment));
        $this->from(route('subscriber.subscriptions.create', ['apartment_id' => $apartment->id]))
            ->post(route('subscriber.subscriptions.store'), $payload)->assertSessionHasErrors('quote_signature');
        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->assertDatabaseCount('legal_acceptances', 0);
        $this->assertDatabaseCount('subscription_payment_attempts', 0);
    }

    public function test_consent_failure_rolls_back_order_profile_and_attempt(): void
    {
        [, , $payload] = $this->customer();
        $profileCount = BillingProfile::count();
        $payload = array_merge($payload, [
            'billing_mode' => 'new', 'billing_label' => 'Yeni profil', 'billing_party_type' => 'individual',
            'billing_legal_name' => 'Ödeyen Kişi', 'billing_identity_number' => '11111111111', 'billing_address' => 'Adres',
        ]);
        $this->partialMock(LegalConsent::class)->shouldReceive('recordSale')->once()->andThrow(new \RuntimeException('Simulated consent failure'));
        $this->withoutExceptionHandling();
        try {
            $this->post(route('subscriber.subscriptions.store'), $payload);
            $this->fail('The transaction must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated consent failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->assertDatabaseCount('subscription_payment_attempts', 0);
        $this->assertSame($profileCount, BillingProfile::count());
    }

    public function test_changed_sale_document_requires_renewed_consent(): void
    {
        [, , $payload] = $this->customer();
        \App\Models\LegalDocumentVersion::publish(LegalConsent::DISTANCE_SALES, 'checkout-new', 'Güncel sözleşme', 'Yeni sözleşme metni');
        $this->post(route('subscriber.subscriptions.store'), $payload)->assertSessionHasErrors('quote_signature');
        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->assertDatabaseCount('legal_acceptances', 0);
    }

    public function test_finalization_and_activation_are_idempotent_even_after_payment_soft_delete(): void
    {
        [, , $payload] = $this->customer();
        $this->post(route('subscriber.subscriptions.store'), $payload)->assertRedirect();
        $order = UserSubscription::sole();
        $service = app(FinalizeSubscriptionPayment::class);
        $payment = $service->manual($order, 'havale', 'REF');
        $end = $order->fresh()->expires_at->toDateTimeString();
        $this->assertSame($payment->id, $service->manual($order, 'havale', 'REF')->id);
        app(SubscriptionCheckout::class)->activate($order);
        $this->assertSame($end, $order->fresh()->expires_at->toDateTimeString());
        $payment->delete();
        $this->assertSame($payment->id, $service->manual($order, 'havale', 'REF')->id);
        $this->assertSame(1, SubscriptionPayment::withTrashed()->count());
        $this->assertSame('succeeded', SubscriptionPaymentAttempt::sole()->status);
    }

    public function test_cancelled_order_cannot_be_settled_and_attempt_is_cancelled(): void
    {
        [, , $payload] = $this->customer();
        $this->post(route('subscriber.subscriptions.store'), $payload)->assertRedirect();
        $order = UserSubscription::sole();
        $this->post(route('subscriber.subscriptions.cancel', $order))->assertRedirect();
        $this->assertSame('cancelled', SubscriptionPaymentAttempt::sole()->status);
        try {
            app(FinalizeSubscriptionPayment::class)->manual($order, 'havale');
            $this->fail('Cancelled orders cannot be settled.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('subscription', $exception->errors());
        }
        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_minor_unit_conversion_is_exact(): void
    {
        $this->assertSame(29, PaymentAmount::minor('0.29'));
        $this->assertSame(1001, PaymentAmount::minor('10.01'));
        $this->assertSame(1190, PaymentAmount::minor('11.9'));
        $this->assertSame(100, PaymentAmount::minor('1'));
    }

    public function test_new_token_for_an_apartment_with_a_pending_order_continues_that_order(): void
    {
        [, , $payload] = $this->customer();
        $this->post(route('subscriber.subscriptions.store'), $payload)->assertRedirect();
        $order = UserSubscription::sole();
        $payload['checkout_token'] = (string) Str::uuid();
        $this->post(route('subscriber.subscriptions.store'), $payload)
            ->assertRedirect(route('subscriber.subscriptions.receipt', $order));
        $this->assertDatabaseCount('user_subscriptions', 1);
        $this->assertDatabaseCount('subscription_payment_attempts', 1);
        $this->assertDatabaseCount('legal_acceptances', 2);
    }

    public function test_card_preparation_requires_payer_details_and_cannot_be_manually_settled_or_cancelled(): void
    {
        config(['payments.card_enabled' => true]);
        [, , $payload] = $this->customer();
        $payload['payment_method'] = 'kredi_kartı';
        $this->post(route('subscriber.subscriptions.store'), $payload)->assertSessionHasErrors(['payer_name', 'payer_email', 'payer_phone', 'payer_address']);
        $this->assertDatabaseCount('user_subscriptions', 0);
        $payload += ['payer_name' => 'Ödeyen Kişi', 'payer_email' => 'payer@example.com', 'payer_phone' => '05551112233', 'payer_address' => 'Ödeme adresi'];
        $this->post(route('subscriber.subscriptions.store'), $payload)->assertRedirect();
        $attempt = SubscriptionPaymentAttempt::sole();
        $this->assertSame('paytr', $attempt->provider);
        $this->assertSame('Ödeyen Kişi', $attempt->payer_snapshot['payer_name']);
        $this->assertStringNotContainsString('payer@example.com', $attempt->getRawOriginal('payer_snapshot'));
        $order = UserSubscription::sole();
        $this->post(route('subscriber.subscriptions.cancel', $order))->assertSessionHasErrors('subscription');
        try {
            app(FinalizeSubscriptionPayment::class)->manual($order, 'havale');
            $this->fail('Card payments need verified provider settlement.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('payment_method', $exception->errors());
        }
        $this->assertDatabaseCount('subscription_payments', 0);
        $this->assertFalse($order->fresh()->is_active);
    }

    public function test_unknown_attempt_is_reused_and_blocks_cancellation_and_deletion(): void
    {
        [, , $payload] = $this->customer();
        $this->post(route('subscriber.subscriptions.store'), $payload)->assertRedirect();
        $order = UserSubscription::sole();
        $attempt = SubscriptionPaymentAttempt::sole();
        $attempt->update(['status' => 'unknown']);
        $this->assertSame($attempt->id, app(\App\Support\SubscriptionPaymentAttempts::class)->open($order)->id);
        $this->post(route('subscriber.subscriptions.cancel', $order))->assertSessionHasErrors('subscription');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->delete(route('admin.orders.destroy', $order), ['current_password' => 'password'])
            ->assertSessionHasErrors('order');
        $this->assertNull($order->fresh()->deleted_at);
        $this->assertDatabaseCount('subscription_payment_attempts', 1);
    }
}
