<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\CheckoutJourney;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\CheckoutJourneyTracking;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function setupJourney(): array
    {
        $user = User::factory()->withBankTransfer()->create();
        $apartment = Apartment::factory()->forUser($user)->create(['unit_count' => 10, 'is_active' => true, 'setup_completed_at' => now()]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        app(SubscriptionCheckout::class)->openFree($user, $apartment);
        $response = $this->actingAs($user)->get(route('subscriber.subscriptions.create', ['apartment_id' => $apartment->id]))->assertOk();

        return [$user, $apartment, $response->viewData('checkoutJourney')];
    }

    public function test_form_steps_are_tracked_before_any_order_is_created_and_private_fields_are_ignored(): void
    {
        [$user, , $journey] = $this->setupJourney();
        $billing = $this->billingSelection($user);
        $this->postJson(route('subscriber.checkout-journeys.events', $journey->uuid), [
            'type' => 'changed', 'stage' => 'plan', 'field' => 'period', 'period' => 'yearly', 'payment_method' => 'havale',
            'billing_mode' => 'existing', 'billing_profile_id' => $billing['billing_profile_id'],
            'identity_number' => 'PRIVATE-IDENTITY', 'payer_address' => 'PRIVATE-ADDRESS', 'amount' => 1,
        ])->assertNoContent();
        $this->assertNull($journey->fresh()->payment_method);
        $this->assertNull($journey->fresh()->billing_profile_id);
        $planEvent = $journey->events()->where('type', 'changed')->sole();
        $this->assertArrayNotHasKey('payment_method', $planEvent->details);
        $this->assertArrayNotHasKey('billing_profile_id', $planEvent->details);
        $this->postJson(route('subscriber.checkout-journeys.events', $journey->uuid), ['type' => 'viewed', 'stage' => 'billing', 'billing_profile_id' => $billing['billing_profile_id'], 'payment_method' => 'havale'])->assertNoContent();
        $this->assertNull($journey->fresh()->payment_method);
        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->assertSame('billing', $journey->fresh()->stage);
        $this->assertSame('yearly', $journey->fresh()->period);
        $this->assertNotSame('1.00', $journey->fresh()->quoted_amount);
        $events = $journey->events()->get()->toJson();
        $this->assertStringNotContainsString('PRIVATE-', $events);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.checkout-journeys.show', $journey))
            ->assertOk()->assertSee('Yıllık')->assertDontSee('Ödeme yöntemi: Havale / EFT')->assertSee('Fatura bilgileri');
    }

    public function test_payment_selection_is_tracked_at_payment_stage_and_old_hidden_defaults_are_not_displayed(): void
    {
        [, , $journey] = $this->setupJourney();
        app(CheckoutJourneyTracking::class)->event($journey, 'viewed', 'browser', ['stage' => 'plan', 'period' => 'monthly', 'payment_method' => 'kredi_kartı', 'billing_profile_id' => 999]);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.checkout-journeys.show', $journey))
            ->assertOk()->assertDontSee('Ödeme yöntemi: Kredi kartı')->assertDontSee('Fatura profili kayıt no: 999');
        $this->actingAs($journey->user)->postJson(route('subscriber.checkout-journeys.events', $journey->uuid), [
            'type' => 'changed', 'stage' => 'payment', 'field' => 'payment_method', 'payment_method' => 'havale',
        ])->assertNoContent();
        $this->assertSame('havale', $journey->fresh()->payment_method);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.checkout-journeys.show', $journey))
            ->assertOk()->assertSee('Ödeme yöntemi: Havale / EFT');
    }

    public function test_havale_submission_and_activation_share_one_journey(): void
    {
        [$user, $apartment, $journey] = $this->setupJourney();
        $this->post(route('subscriber.subscriptions.store'), [
            'checkout_journey' => $journey->uuid, 'apartment_ids' => [$apartment->id],
            'period' => 'monthly', 'payment_method' => 'havale', 'accept_sales' => 1,
        ] + $this->billingSelection($user))->assertSessionHasNoErrors()->assertRedirect();
        $order = UserSubscription::sole();
        $this->assertNotNull($order->order_number);
        $this->assertSame($order->id, $journey->fresh()->order_id);
        $this->assertDatabaseCount('checkout_journeys', 1);
        app(\App\Support\FinalizeSubscriptionPayment::class)->manual($order, 'havale', 'TEST');
        $this->assertSame('Etkinleşti', $journey->fresh()->progress_label);
        $this->assertTrue($journey->events()->where('type', 'order_updated')->exists());
        $this->assertTrue($journey->events()->where('type', 'payment_attempt')->exists());
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.checkout-journeys.index', ['method' => 'havale', 'search' => $journey->reference]))
            ->assertOk()->assertSee($journey->reference)->assertSee('Etkinleşti');
    }

    public function test_stale_and_explicitly_closed_forms_are_different_and_browser_events_cannot_rewrite_a_submitted_journey(): void
    {
        [$user, $apartment, $journey] = $this->setupJourney();
        $this->travel(31)->minutes();
        $this->assertSame('Tamamlanmadı', $journey->fresh()->progress_label);
        $this->postJson(route('subscriber.checkout-journeys.events', $journey->uuid), ['type' => 'closed', 'stage' => 'billing'])->assertNoContent();
        $this->assertSame('Ekrandan çıkıldı', $journey->fresh()->progress_label);
        $other = app(CheckoutJourneyTracking::class)->begin($user, $apartment);
        $order = UserSubscription::factory()->create(['user_id' => $user->id, 'status' => 'pending']);
        $other->update(['order_id' => $order->id, 'stage' => 'submitted']);
        $this->postJson(route('subscriber.checkout-journeys.events', $other->uuid), ['type' => 'closed', 'stage' => 'plan'])->assertNoContent();
        $this->assertNull($other->fresh()->closed_at);
        $this->assertSame('submitted', $other->fresh()->stage);
    }

    public function test_foreign_users_and_profiles_are_rejected_and_logs_are_admin_only(): void
    {
        [$user, , $journey] = $this->setupJourney();
        $other = User::factory()->withBankTransfer()->create();
        $profile = $this->billingSelection($other);
        $this->postJson(route('subscriber.checkout-journeys.events', $journey->uuid), ['type' => 'changed', 'stage' => 'billing', 'billing_profile_id' => $profile['billing_profile_id']])->assertStatus(422);
        $this->get(route('admin.checkout-journeys.index'))->assertForbidden();
        $this->get(route('admin.checkout-journeys.show', $journey))->assertForbidden();
        $this->actingAs($other)->postJson(route('subscriber.checkout-journeys.events', $journey->uuid), ['type' => 'closed', 'stage' => 'plan'])->assertNotFound();
        $this->assertNull($journey->fresh()->closed_at);
    }

    public function test_validation_errors_record_field_names_without_submitted_sensitive_values(): void
    {
        [, , $journey] = $this->setupJourney();
        $this->post(route('subscriber.subscriptions.store'), ['checkout_journey' => $journey->uuid, 'payment_method' => 'havale', 'payer_name' => 'PRIVATE-NAME'])
            ->assertSessionHasErrors();
        $event = $journey->events()->where('type', 'validation_failed')->sole();
        $this->assertSame('server', $event->source);
        $this->assertContains('period', $event->details['fields']);
        $this->assertStringNotContainsString('PRIVATE-NAME', $event->toJson());
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.checkout-journeys.show', $journey))->assertOk()->assertSee('Form doğrulaması başarısız');
    }

    public function test_new_billing_profile_is_logged_by_server_and_form_retry_reuses_journey(): void
    {
        [$user, $apartment, $journey] = $this->setupJourney();
        $this->postJson(route('subscriber.billing-profiles.store'), [
            'checkout_journey' => $journey->uuid, 'label' => 'Yeni profil', 'party_type' => 'individual',
            'legal_name' => 'Test', 'identity_number' => '12345678901', 'address' => 'PRIVATE-ADDRESS',
        ])->assertOk();
        $event = $journey->events()->where('type', 'billing_saved')->sole();
        $this->assertSame('server', $event->source);
        $this->assertArrayHasKey('billing_profile_id', $event->details);
        $this->assertStringNotContainsString('PRIVATE-ADDRESS', $event->toJson());
        $this->assertSame($journey->id, app(CheckoutJourneyTracking::class)->begin($user, $apartment, $journey->uuid)->id);
    }
}
