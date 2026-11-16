<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\User;
use App\Support\CheckoutQuote;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_accepts_ten_digits_without_zero_and_rejects_other_formats(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $this->actingAs($user);
        foreach (['05551234567', '+905551234567', '555 123 4567', '555123456', '55512345678', 'abcdefghij', '0551234567'] as $phone) {
            $this->patch(route('profile.update'), ['name' => $user->name, 'phone' => $phone])
                ->assertSessionHasErrors('phone');
            $this->assertNull($user->fresh()->phone);
        }
        $this->patch(route('profile.update'), ['name' => $user->name, 'phone' => '5551234567'])
            ->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
        $this->assertSame('5551234567', $user->fresh()->phone);
    }

    public function test_profile_phone_stays_optional_and_email_cannot_be_changed_by_injected_input(): void
    {
        $user = User::factory()->create(['phone' => '5551234567']);
        $email = $user->email;
        $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'phone' => '', 'email' => 'not-an-email'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull($user->fresh()->phone);
        $this->assertSame($email, $user->fresh()->email);
    }

    public function test_checkout_rejects_invalid_phone_and_email_without_creating_order_or_calling_paytr(): void
    {
        config(['payments.card_enabled' => true]);
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create(['unit_count' => 10, 'setup_completed_at' => now()]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        app(SubscriptionCheckout::class)->openFree($user, $apartment);
        $payload = ['apartment_ids' => [$apartment->id], 'period' => 'monthly', 'payment_method' => 'kredi_kartı',
            'accept_sales' => 1, 'checkout_token' => (string) Str::uuid(), 'quote_signature' => app(CheckoutQuote::class)->signature($user, $apartment),
            'payer_name' => 'Ad Soyad', 'payer_email' => 'payer@example.com', 'payer_phone' => '5551234567', 'payer_address' => 'Adres']
            + $this->billingSelection($user);
        $this->actingAs($user);
        foreach (['05551234567', '+905551234567', '555 1234567', '555123456', 'phone'] as $phone) {
            $this->post(route('subscriber.subscriptions.store'), array_replace($payload, ['payer_phone' => $phone]))
                ->assertSessionHasErrors('payer_phone');
        }
        foreach (['not-an-email', 'user@', 'müşteri@example.com'] as $email) {
            $this->post(route('subscriber.subscriptions.store'), array_replace($payload, ['payer_email' => $email]))
                ->assertSessionHasErrors('payer_email');
        }
        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->assertDatabaseCount('subscription_payment_attempts', 0);
        Http::assertNothingSent();
    }
}
