<?php

namespace Tests\Feature\Admin;

use App\Models\Apartment;
use App\Models\BillingProfile;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubscriptionCheckout;
use App\Support\SubscriptionPaymentAttempts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PendingCardSubscriptionDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function checkout(string $method = 'kredi_kartı'): array
    {
        $user = User::factory()->withBankTransfer()->create();
        $apartment = Apartment::factory()->forUser($user)->create(['name' => 'Kart Deneme Apartmanı', 'unit_count' => 10]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        $service = app(SubscriptionCheckout::class);
        $service->openFree($user, $apartment);
        $profile = $user->billingProfiles()->create(['label' => 'Ev', 'party_type' => BillingProfile::TYPE_INDIVIDUAL,
            'legal_name' => $user->name, 'address' => 'Adres', 'is_active' => true]);
        $order = $service->openPending($user, collect([$apartment]), 'monthly', $method, $profile);
        $record = Subscription::where('apartment_id', $apartment->id)->sole();

        return [$order, $record];
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_expired_card_screen_is_not_a_pending_subscription_order_even_with_legacy_order_number(bool $legacy): void
    {
        [$order, $record] = $this->checkout();
        if ($legacy) $order->update(['order_number' => 'SIP-26-OLDTEST']);
        $attempt = app(SubscriptionPaymentAttempts::class)->open($order);
        $attempt->update(['submitted_at' => now()->subMinutes(40), 'token_expires_at' => now()->subMinutes(10)]);
        $this->actingAs(User::factory()->admin()->create());
        foreach ([false, true] as $expire) {
            if ($expire) $this->artisan('paytr:expire-checkouts')->assertSuccessful();
            $this->get(route('admin.managers.index', ['view' => 'items']))->assertOk()
                ->assertSee('Kart Deneme Apartmanı')->assertSee('Ücretsiz')
                ->assertDontSee('Bekleyen sipariş')->assertDontSee('Ödeme bekliyor');
            $this->assertSame('free', $record->fresh()->currentItem()->plan);
            $this->get(route('admin.subscriptions.show', $record))->assertOk()->assertDontSee('Ödeme bekliyor');
            $this->get(route('admin.card-transactions.index'))->assertOk()->assertSee($attempt->transaction_number);
            $this->assertSame('pending', $attempt->fresh()->status);
            $this->assertSame('pending', $order->fresh()->status);
        }
        $this->assertNotNull($order->fresh()->checkout_abandoned_at);
    }

    public function test_bank_transfer_still_shows_real_pending_order(): void
    {
        [$order, $record] = $this->checkout('havale');
        $this->assertNotNull($order->order_number);
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.managers.index', ['view' => 'items']))->assertOk()->assertSee('Bekleyen sipariş');
    }

    public function test_pending_legacy_card_item_is_not_listed_as_a_subscription_period(): void
    {
        [$order] = $this->checkout();
        $order->items()->update(['apartment_subscription_id' => null]);
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.managers.index', ['view' => 'items']))->assertOk()
            ->assertViewHas('legacyItems', fn ($items) => $items->total() === 0)->assertDontSee('Ödeme bekliyor');
    }
}
