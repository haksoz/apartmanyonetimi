<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\PriceBand;
use App\Models\QuoteRequest;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserQuotaOverride;
use App\Models\UserSubscription;
use App\Support\PriceQuote;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_apartment_opens_free_without_a_quote_request(): void
    {
        $user = User::factory()->withBankTransfer()->create();
        UserQuotaOverride::create(['user_id' => $user->id, 'max_apartments' => 2]);
        $this->actingAs($user)->post(route('subscriber.apartments.store'), $this->payload('Büyük Blok', 120))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('apartments', ['name' => 'Büyük Blok', 'unit_count' => 120, 'billing_plan' => 'free']);
        $this->assertDatabaseCount('quote_requests', 0);
        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->assertDatabaseHas('subscription_items', ['plan' => SubscriptionItem::PLAN_FREE]);
    }

    public function test_large_apartment_requires_resident_consent(): void
    {
        $user = User::factory()->withBankTransfer()->create();
        UserQuotaOverride::create(['user_id' => $user->id, 'max_apartments' => 2]);
        $this->actingAs($user)->post(route('subscriber.apartments.store'), $this->payload('Büyük Blok', 120, false))
            ->assertSessionHasErrors('accept_resident_data');
        $this->assertDatabaseCount('apartments', 0);
        $this->assertDatabaseCount('quote_requests', 0);
    }

    public function test_existing_free_apartment_can_still_move_to_a_paid_order(): void
    {
        $user = User::factory()->withBankTransfer()->create();
        $apartment = Apartment::factory()->forUser($user)->create([
            'name' => 'Küçük Blok',
            'unit_count' => 10,
            'billing_plan' => 'free',
            'is_active' => true,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        app(SubscriptionCheckout::class)->openFree($user, $apartment);

        $this->actingAs($user)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSee('Ücretliye Geç');

        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'monthly',
                'payment_method' => 'havale',
                'accept_sales' => '1',
            ] + $this->billingSelection($user))
            ->assertRedirect();

        $order = UserSubscription::query()->firstOrFail();
        $this->assertTrue($order->isPending());
        $item = SubscriptionItem::query()->where('subscription_id', $order->id)->firstOrFail();
        $this->assertSame(SubscriptionItem::PLAN_PAID, $item->plan);
        $this->assertSame(10, $item->unit_count);
        $this->assertGreaterThan(0, (float) $item->amount);
    }

    public function test_large_apartment_can_open_a_member_order(): void
    {
        $user = User::factory()->withBankTransfer()->create();
        $apartment = Apartment::factory()->forUser($user)->create([
            'name' => 'Büyük Blok',
            'unit_count' => 160,
            'billing_plan' => 'free',
            'is_active' => true,
            'custom_monthly_price' => 1200,
            'custom_yearly_price' => 12000,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        app(SubscriptionCheckout::class)->openFree($user, $apartment);

        PriceBand::where('per_unit', true)->update(['monthly_price' => 7.5, 'yearly_price' => 75]);
        $quote = app(PriceQuote::class)->forUnits(160, 'monthly', $apartment);
        $this->assertFalse($quote['requires_quote']);
        $this->assertEquals(1200, $quote['net_amount']);

        $this->actingAs($user)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSee('Ücretliye Geç')
            ->assertDontSee('Özel Teklif');

        $this->actingAs($user)
            ->from(route('subscriber.subscriptions.create'))
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'monthly',
                'payment_method' => 'havale',
                'accept_sales' => '1',
            ] + $this->billingSelection($user))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, UserSubscription::query()->count());
    }

    public function test_admin_can_list_quote_requests_and_change_status(): void
    {
        $user = User::factory()->withBankTransfer()->create(['name' => 'Talep Eden']);
        $request = QuoteRequest::create([
            'user_id' => $user->id,
            'apartment_name' => 'Admin Blok',
            'unit_count' => 180,
            'status' => QuoteRequest::STATUS_PENDING,
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('admin.quote-requests.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.quote-requests.index'))
            ->assertOk()
            ->assertSee('Talep Eden')
            ->assertSee('Admin Blok')
            ->assertSee('180')
            ->assertSee('Bekliyor')
            ->assertSee('Görüşüldü')
            ->assertSee('Dönüştürüldü')
            ->assertSee('İptal');

        $this->actingAs($admin)
            ->patch(route('admin.quote-requests.update', $request), [
                'status' => QuoteRequest::STATUS_CONTACTED,
            ])
            ->assertRedirect();

        $this->assertSame(QuoteRequest::STATUS_CONTACTED, $request->fresh()->status);
    }

    private function payload(string $name, int $unitCount, bool $resident = true): array
    {
        return [
            'name' => $name,
            'address' => 'Adres',
            'unit_count' => $unitCount,
            'account_opening_date' => now()->toDateString(),
            'accept_resident_data' => $resident ? '1' : '0',
            'accept_privacy' => $resident ? '1' : '0',
        ];
    }
}
