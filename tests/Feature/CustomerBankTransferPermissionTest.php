<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\User;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBankTransferPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): array
    {
        config(['payments.card_enabled' => true]);
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create(['unit_count' => 10, 'setup_completed_at' => now()]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        app(SubscriptionCheckout::class)->openFree($user, $apartment);
        $payload = ['apartment_ids' => [$apartment->id], 'period' => 'monthly', 'payment_method' => 'havale', 'accept_sales' => 1]
            + $this->billingSelection($user);
        $this->actingAs($user);

        return [$user, $apartment, $payload];
    }

    public function test_default_customer_has_card_only_and_cannot_forge_bank_transfer(): void
    {
        [$user, $apartment, $payload] = $this->customer();
        $this->assertFalse($user->fresh()->bank_transfer_allowed);
        $this->get(route('subscriber.subscriptions.create', ['apartment_id' => $apartment->id]))->assertOk()
            ->assertSee('type="hidden" name="payment_method" value="kredi_kartı"', false)
            ->assertDontSee('value="havale"', false)->assertDontSee('name="payment_method" value="kredi_kartı" class="mt-1"', false);
        $this->post(route('subscriber.subscriptions.store'), $payload + ['bank_transfer_allowed' => 1])
            ->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('user_subscriptions', 0);
    }

    public function test_admin_can_enable_transfer_for_one_customer_then_revoke_new_purchase_permission(): void
    {
        [$user, $apartment, $payload] = $this->customer();
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $url = route('admin.customers.payment-settings.update', $user);
        $this->actingAs($admin)->get(route('admin.customers.show', $user))->assertOk()->assertSee('Havale / EFT ile ödeme izni');
        $this->patch($url, ['bank_transfer_allowed' => 1])->assertRedirect(route('admin.customers.show', $user));
        $this->assertTrue($user->fresh()->bank_transfer_allowed);
        $this->assertFalse($other->fresh()->bank_transfer_allowed);
        $this->actingAs($user->fresh())->get(route('subscriber.subscriptions.create', ['apartment_id' => $apartment->id]))
            ->assertOk()->assertSee('value="havale"', false)->assertSee('value="kredi_kartı"', false);
        $this->post(route('subscriber.subscriptions.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('user_subscriptions', 1);
        $this->actingAs($admin)->patch($url, ['bank_transfer_allowed' => 0])->assertRedirect();
        $this->assertFalse($user->fresh()->bank_transfer_allowed);
        $order = $user->subscriptions()->firstOrFail();
        $this->actingAs($user->fresh())->get(route('subscriber.subscriptions.receipt', $order))->assertOk();
        $this->post(route('subscriber.subscriptions.payment-info', $order), ['reference_code' => 'HVL-EXISTING'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('HVL-EXISTING', $order->fresh()->receipt_reference);
    }

    public function test_customer_cannot_change_permission_and_no_method_is_offered_when_card_is_closed(): void
    {
        [$user, $apartment] = $this->customer();
        $this->patch(route('admin.customers.payment-settings.update', $user), ['bank_transfer_allowed' => 1])->assertForbidden();
        $this->assertFalse($user->fresh()->bank_transfer_allowed);
        config(['payments.card_enabled' => false]);
        $this->get(route('subscriber.subscriptions.create', ['apartment_id' => $apartment->id]))->assertOk()
            ->assertSee('Kartla ödeme şu anda kullanılamıyor.')->assertSee('data-payment-unavailable disabled', false)
            ->assertDontSee('value="havale"', false);
    }

    public function test_permission_is_rechecked_under_lock_when_auth_model_is_stale(): void
    {
        [$user, , $payload] = $this->customer();
        $user->bank_transfer_allowed = true; // Stale in-memory permission; persisted permission is still false.
        $this->post(route('subscriber.subscriptions.store'), $payload)->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('user_subscriptions', 0);
    }
}
