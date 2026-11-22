<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\CheckoutJourney;
use App\Models\User;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutContactCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function checkout(array $attributes = []): array
    {
        config(['payments.card_enabled' => true]);
        $user = User::factory()->create($attributes);
        $apartment = Apartment::factory()->forUser($user)->create(['unit_count' => 10, 'setup_completed_at' => now()]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        app(SubscriptionCheckout::class)->openFree($user, $apartment);
        $response = $this->actingAs($user)->get(route('subscriber.subscriptions.create', ['apartment_id' => $apartment->id]))->assertOk();

        return [$user, $apartment, $response->viewData('checkoutJourney'), $response];
    }

    public function test_missing_contact_is_completed_without_order_or_sensitive_tracking_values_and_refreshes_form_lease(): void
    {
        $this->freezeTime();
        [$user, , $journey, $response] = $this->checkout();
        $response->assertSee('İletişim bilgilerini tamamla')->assertSee('data-contact-field="phone"', false)
            ->assertSee('data-contact-field="address"', false);
        $this->travel(100)->seconds();
        $name = $user->name;
        $email = $user->email;
        $this->patchJson(route('subscriber.checkout-contact.update'), [
            'checkout_journey' => $journey->uuid, 'phone' => '5551234567', 'address' => 'PRIVATE-ADDRESS',
            'name' => 'FORGED-NAME', 'email' => 'forged@example.com', 'role' => 'admin', 'bank_transfer_allowed' => true,
        ])->assertOk()->assertJsonPath('contact.payer_phone', '5551234567')->assertJsonPath('contact.payer_address', 'PRIVATE-ADDRESS');
        $user->refresh();
        $this->assertSame('5551234567', $user->phone);
        $this->assertSame('PRIVATE-ADDRESS', $user->address);
        $this->assertSame($name, $user->name);
        $this->assertSame($email, $user->email);
        $this->assertSame('manager', $user->role);
        $this->assertFalse($user->bank_transfer_allowed);
        $this->assertNull($journey->fresh()->order_id);
        $this->assertSame('plan', $journey->fresh()->stage);
        $event = $journey->events()->where('type', 'contact_completed')->sole();
        $this->assertSame(['phone', 'address'], $event->details['fields']);
        $this->assertStringNotContainsString('PRIVATE-ADDRESS', $event->toJson());
        $this->assertStringNotContainsString('5551234567', $event->toJson());
        $this->travel(30)->seconds();
        $this->assertFalse($journey->fresh()->isFormTimedOut());
        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->assertDatabaseCount('subscription_payment_attempts', 0);
    }

    public function test_only_invalid_fields_are_shown_and_existing_valid_contact_cannot_be_overwritten(): void
    {
        [$user, , $journey, $response] = $this->checkout(['phone' => '05551234567', 'address' => 'Existing valid address']);
        $response->assertSee('data-contact-field="phone"', false)->assertDontSee('data-contact-field="address"', false);
        $this->patchJson(route('subscriber.checkout-contact.update'), ['checkout_journey' => $journey->uuid,
            'phone' => '5551234567', 'address' => 'FORGED-ADDRESS'])->assertOk();
        $this->assertSame('Existing valid address', $user->fresh()->address);
        $this->patchJson(route('subscriber.checkout-contact.update'), ['checkout_journey' => $journey->uuid,
            'phone' => '5557654321', 'address' => 'FORGED-ADDRESS'])->assertOk();
        $this->assertSame('5551234567', $user->fresh()->phone);
        $this->assertSame(1, $journey->events()->where('type', 'contact_completed')->count());
    }

    public function test_validation_is_atomic_and_does_not_save_partial_or_invalid_contact(): void
    {
        [$user, , $journey] = $this->checkout();
        $url = route('subscriber.checkout-contact.update');
        $this->patchJson($url, ['checkout_journey' => $journey->uuid, 'phone' => '05551234567', 'address' => 'Adres'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->patchJson($url, ['checkout_journey' => $journey->uuid, 'phone' => '5551234567', 'address' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('address');
        $this->patchJson($url, ['checkout_journey' => $journey->uuid, 'phone' => '5551234567', 'address' => str_repeat('A', 401)])
            ->assertUnprocessable()->assertJsonValidationErrors('address');
        $this->assertNull($user->fresh()->phone);
        $this->assertNull($user->fresh()->address);
        $this->assertFalse($journey->events()->where('type', 'contact_completed')->exists());
    }

    public function test_foreign_closed_expired_and_no_longer_managed_journeys_cannot_update_profile(): void
    {
        [$user, $apartment, $journey] = $this->checkout();
        $url = route('subscriber.checkout-contact.update');
        $payload = ['checkout_journey' => $journey->uuid, 'phone' => '5551234567', 'address' => 'Adres'];
        $this->checkout();
        $this->patchJson($url, $payload)->assertNotFound();
        $this->actingAs($user);
        $journey->update(['last_seen_at' => now()->subSeconds(121)]);
        $this->patchJson($url, $payload)->assertStatus(409)->assertJsonPath('renewal_required', true);
        $this->assertNull($user->fresh()->phone);
        $journey->update(['last_seen_at' => now(), 'closed_at' => now()]);
        $this->patchJson($url, $payload)->assertStatus(409);
        $journey->update(['closed_at' => null]);
        $apartment->members()->updateExistingPivot($user->id, ['is_active' => false]);
        $this->patchJson($url, $payload)->assertForbidden();
        $this->assertNull($user->fresh()->phone);
    }

    public function test_complete_profiles_keep_read_only_summary_without_completion_editor(): void
    {
        [, , , $response] = $this->checkout(['phone' => '5551234567', 'address' => 'Profil adresi']);
        $response->assertSee('Profil adresi')->assertDontSee('İletişim bilgilerini tamamla')
            ->assertDontSee('data-contact-field="phone"', false)->assertDontSee('data-contact-field="address"', false);
    }

    public function test_submitted_journey_cannot_rewrite_customer_contact(): void
    {
        [$user, $apartment, $journey] = $this->checkout();
        $selection = $this->billingSelection($user);
        $order = app(SubscriptionCheckout::class)->openPending($user, collect([$apartment]), 'monthly', 'kredi_kartı',
            $user->billingProfiles()->findOrFail($selection['billing_profile_id']));
        app(\App\Support\CheckoutJourneyTracking::class)->attach($order, $journey->uuid);
        $this->patchJson(route('subscriber.checkout-contact.update'), ['checkout_journey' => $journey->uuid,
            'phone' => '5551234567', 'address' => 'Adres'])->assertStatus(409);
        $this->assertNull($user->fresh()->phone);
        $this->assertFalse($journey->events()->where('type', 'contact_completed')->exists());
    }
}
