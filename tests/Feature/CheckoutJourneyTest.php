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

    public function test_timeout_popup_is_logged_once_per_timeout_and_its_close_action_is_explicit(): void
    {
        $this->freezeTime();
        [, , $journey] = $this->setupJourney();
        $url = route('subscriber.checkout-journeys.events', $journey->uuid);
        $this->postJson($url, ['type' => 'timeout_prompt', 'stage' => 'plan'])->assertNoContent();
        $this->assertSame(0, $journey->events()->where('type', 'timeout_prompt')->count());
        $this->travel(120)->seconds();
        $seen = $journey->fresh()->last_seen_at;
        foreach (range(1, 2) as $repeat) {
            $this->postJson($url, ['type' => 'timeout_prompt', 'stage' => 'plan'])->assertNoContent();
        }
        $this->assertSame(1, $journey->events()->where('type', 'timeout_prompt')->count());
        $this->assertTrue($seen->equalTo($journey->fresh()->last_seen_at));
        $this->postJson($url, ['type' => 'renewed', 'stage' => 'plan'])->assertNoContent();
        $this->travel(120)->seconds();
        $this->postJson($url, ['type' => 'timeout_prompt', 'stage' => 'plan'])->assertNoContent();
        $this->assertSame(2, $journey->events()->where('type', 'timeout_prompt')->count());
        $this->postJson($url, ['type' => 'timeout_dismissed', 'stage' => 'plan'])->assertNoContent();
        $this->assertNotNull($journey->fresh()->closed_at);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.checkout-journeys.show', $journey))
            ->assertOk()->assertSee('Süre uzatma penceresi görüntülendi')->assertSee('Kullanıcı süre uzatma penceresinden işlemi kapattı');
    }

    public function test_single_payment_option_and_contract_acceptance_have_explicit_descriptions(): void
    {
        [, , $journey] = $this->setupJourney();
        $url = route('subscriber.checkout-journeys.events', $journey->uuid);
        $this->postJson($url, ['type' => 'viewed', 'stage' => 'payment', 'payment_method' => 'havale'])->assertNoContent();
        $this->assertTrue($journey->events()->where('type', 'viewed')->sole()->details['payment_method_automatic']);
        $this->postJson($url, ['type' => 'accepted', 'stage' => 'distance'])->assertNoContent();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.checkout-journeys.show', $journey))
            ->assertOk()->assertSee('Aşama: Ödeme (görüntülendi)')
            ->assertSee('Ödeme yöntemi: Havale / EFT (Tek seçim otomatik tanımlandı)')
            ->assertSee('Satış sözleşmesi (Onay verildi)')->assertDontSee('Aşama: Satış sözleşmesi (Onay verildi)');
    }

    public function test_history_uses_stage_action_titles_and_only_that_stages_selection_snapshot(): void
    {
        [, , $journey] = $this->setupJourney();
        $tracking = app(CheckoutJourneyTracking::class);
        $snapshot = ['period' => 'yearly', 'amount' => 1800, 'billing_mode' => 'existing',
            'billing_profile_label' => 'Tarzan Apartmanı Bireysel Fatura', 'billing_party_type' => 'individual', 'payment_method' => 'havale'];
        $tracking->event($journey, 'viewed', 'browser', ['stage' => 'plan', 'period' => 'monthly', 'amount' => 180] + $snapshot);
        $tracking->event($journey, 'changed', 'browser', ['stage' => 'plan', 'field' => 'period'] + $snapshot);
        $tracking->event($journey, 'viewed', 'browser', ['stage' => 'billing'] + $snapshot);
        $tracking->event($journey, 'viewed', 'browser', ['stage' => 'payment'] + $snapshot);
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.checkout-journeys.show', $journey))
            ->assertOk()->assertSee('Aşama: Dönem seçimi (görüntülendi)')->assertSee('Aşama: Dönem seçimi (Seçim değiştirildi)')
            ->assertSee('Aşama: Fatura bilgileri (görüntülendi)')->assertSee('Aşama: Ödeme (görüntülendi)');
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'Dönem: Aylık'));
        $this->assertSame(1, substr_count($html, 'Dönem: Yıllık'));
        $this->assertSame(1, substr_count($html, 'Tutar: 1.800,00'));
        $this->assertSame(1, substr_count($html, 'Profil adı: Tarzan Apartmanı Bireysel Fatura'));
        $this->assertSame(1, substr_count($html, 'Ödeme yöntemi: Havale / EFT'));
    }

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

    public function test_forgotten_paytr_screen_stays_in_same_journey_with_expiry_event_instead_of_pending_order_label(): void
    {
        [$user, $apartment, $journey] = $this->setupJourney();
        $selection = $this->billingSelection($user);
        $profile = $user->billingProfiles()->findOrFail($selection['billing_profile_id']);
        $order = app(SubscriptionCheckout::class)->openPending($user, collect([$apartment]), 'monthly', 'kredi_kartı', $profile);
        app(CheckoutJourneyTracking::class)->attach($order, $journey->uuid);
        $attempt = app(\App\Support\SubscriptionPaymentAttempts::class)->open($order);
        $attempt->update(['submitted_at' => now()->subMinutes(40), 'status' => 'pending']);
        $this->actingAs(User::factory()->admin()->create());
        foreach ([false, true] as $runScheduler) {
            if ($runScheduler) $this->artisan('paytr:expire-checkouts')->assertSuccessful();
            $this->get(route('admin.checkout-journeys.index', ['status' => 'expired']))->assertOk()
                ->assertViewHas('journeys', fn ($journeys) => $journeys->total() === 1)
                ->assertSee($journey->reference)->assertSee('Ödeme ekranının süresi doldu')->assertSee('Sipariş oluşmadı');
            $this->get(route('admin.checkout-journeys.index', ['status' => 'pending']))
                ->assertViewHas('journeys', fn ($journeys) => $journeys->total() === 0);
        }
        $this->get(route('admin.checkout-journeys.show', $journey))->assertOk()
            ->assertSee('PayTR ödeme ekranının süresi doldu')->assertSee($attempt->transaction_number);
        $this->artisan('paytr:expire-checkouts')->assertSuccessful();
        $this->assertSame(1, $journey->events()->where('type', 'card_expired')->count());
        $this->assertDatabaseCount('checkout_journeys', 1);
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertNull($order->fresh()->order_number);
        $order->update(['status' => 'active', 'is_active' => true]);
        $this->assertSame('Etkinleşti', $journey->fresh()->progress_label);
        $this->get(route('admin.checkout-journeys.index', ['status' => 'expired']))
            ->assertViewHas('journeys', fn ($journeys) => $journeys->total() === 0);
    }

    public function test_explicit_card_exit_is_not_misreported_as_timeout_later(): void
    {
        [$user, $apartment, $journey] = $this->setupJourney();
        $selection = $this->billingSelection($user);
        $order = app(SubscriptionCheckout::class)->openPending($user, collect([$apartment]), 'monthly', 'kredi_kartı',
            $user->billingProfiles()->findOrFail($selection['billing_profile_id']));
        app(CheckoutJourneyTracking::class)->attach($order, $journey->uuid);
        $attempt = app(\App\Support\SubscriptionPaymentAttempts::class)->open($order);
        $attempt->update(['submitted_at' => now()]);
        app(\App\Support\Paytr\AbandonCheckout::class)->abandon($order);
        $this->travel(40)->minutes();
        $this->assertSame('Ekrandan çıkıldı', $journey->fresh()->progress_label);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.checkout-journeys.index', ['status' => 'expired']))
            ->assertViewHas('journeys', fn ($journeys) => $journeys->total() === 0);
    }

    public function test_form_times_out_at_two_minutes_and_only_explicit_renewal_reactivates_it(): void
    {
        $this->freezeTime();
        [$user, , $journey] = $this->setupJourney();
        $url = route('subscriber.checkout-journeys.events', $journey->uuid);
        $this->postJson($url, ['type' => 'viewed', 'stage' => 'billing'])->assertNoContent();
        $seen = $journey->fresh()->last_seen_at;
        $this->travel(119)->seconds();
        $this->assertSame('Devam ediyor', $journey->fresh()->progress_label);
        $this->travel(1)->seconds();
        $this->assertSame('Tamamlanmadı', $journey->fresh()->progress_label);
        $this->postJson($url, ['type' => 'timed_out', 'stage' => 'billing'])->assertNoContent();
        $this->postJson($url, ['type' => 'timed_out', 'stage' => 'billing'])->assertNoContent();
        $this->assertSame(1, $journey->events()->where('type', 'timed_out')->count());
        $this->assertTrue($seen->equalTo($journey->fresh()->last_seen_at));
        $this->postJson($url, ['type' => 'activity', 'stage' => 'billing'])->assertStatus(409);
        $this->postJson($url, ['type' => 'viewed', 'stage' => 'payment'])->assertStatus(409);
        $this->assertSame('billing', $journey->fresh()->stage);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.checkout-journeys.index', ['status' => 'incomplete']))
            ->assertOk()->assertSee($journey->reference);
        $this->actingAs($user)->postJson($url, ['type' => 'renewed', 'stage' => 'payment', 'period' => 'yearly'])->assertNoContent();
        $this->assertSame('Devam ediyor', $journey->fresh()->progress_label);
        $this->assertSame('billing', $journey->fresh()->stage);
        $this->assertNull($journey->fresh()->period);
        $this->assertSame(1, $journey->events()->where('type', 'renewed')->count());
        $this->travel(119)->seconds();
        $this->assertSame('Devam ediyor', $journey->fresh()->progress_label);
        $this->travel(1)->seconds();
        $this->assertSame('Tamamlanmadı', $journey->fresh()->progress_label);
    }

    public function test_expired_form_cannot_submit_until_renewed_and_payment_stage_has_separate_timeout(): void
    {
        $this->freezeTime();
        [$user, $apartment, $journey] = $this->setupJourney();
        $payload = ['checkout_journey' => $journey->uuid, 'apartment_ids' => [$apartment->id],
            'period' => 'monthly', 'payment_method' => 'havale', 'accept_sales' => 1] + $this->billingSelection($user);
        $this->travel(120)->seconds();
        $this->post(route('subscriber.subscriptions.store'), $payload)->assertSessionHasErrors('checkout_journey');
        $this->assertNull($journey->fresh()->order_id);
        $this->assertDatabaseCount('user_subscriptions', 0);
        $url = route('subscriber.checkout-journeys.events', $journey->uuid);
        $this->postJson($url, ['type' => 'renewed', 'stage' => 'payment'])->assertNoContent();
        $this->post(route('subscriber.subscriptions.store'), $payload)->assertRedirect();
        $this->assertNotNull($journey->fresh()->order_id);
        $this->travel(10)->minutes();
        $this->assertFalse($journey->fresh()->isFormTimedOut());
        $this->assertSame('Ödeme bekleniyor', $journey->fresh()->progress_label);
        $this->postJson($url, ['type' => 'renewed', 'stage' => 'payment'])->assertStatus(409);
    }

    public function test_unanswered_renewal_can_close_after_twenty_seconds_and_cannot_reopen_closed_process(): void
    {
        $this->freezeTime();
        [, , $journey] = $this->setupJourney();
        $url = route('subscriber.checkout-journeys.events', $journey->uuid);
        $this->travel(120)->seconds();
        $this->postJson($url, ['type' => 'timed_out', 'stage' => 'plan'])->assertNoContent();
        $this->travel(19)->seconds();
        $this->postJson($url, ['type' => 'timeout_closed', 'stage' => 'plan'])->assertStatus(409);
        $this->assertNull($journey->fresh()->closed_at);
        $this->travel(1)->seconds();
        $this->postJson($url, ['type' => 'timeout_closed', 'stage' => 'plan'])->assertNoContent();
        $this->assertNotNull($journey->fresh()->closed_at);
        $this->assertSame('Süre dolduğu için kapatıldı', $journey->fresh()->progress_label);
        $this->postJson($url, ['type' => 'renewed', 'stage' => 'plan'])->assertStatus(409);
        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.checkout-journeys.show', $journey))->assertOk()
            ->assertSee('Süre yenileme sorusuna yanıt verilmedi; işlem kapatıldı');
    }

    public function test_activity_refreshes_open_form_but_closed_journey_cannot_be_renewed(): void
    {
        [$user, , $journey] = $this->setupJourney();
        $url = route('subscriber.checkout-journeys.events', $journey->uuid);
        $this->travel(100)->seconds();
        $this->postJson($url, ['type' => 'activity', 'stage' => 'plan'])->assertNoContent();
        $this->travel(30)->seconds();
        $this->assertSame('Devam ediyor', $journey->fresh()->progress_label);
        $this->postJson($url, ['type' => 'closed', 'stage' => 'plan'])->assertNoContent();
        $this->postJson($url, ['type' => 'renewed', 'stage' => 'plan'])->assertStatus(409);
        $this->assertSame('Ekrandan çıkıldı', $journey->fresh()->progress_label);
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
