<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\LegalAcceptance;
use App\Models\Payment;
use App\Models\QuoteRequest;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\ApartmentCommercial;
use App\Support\LegalConsent;
use App\Support\PriceQuote;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_hundred_units_open_a_free_apartment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('subscriber.apartments.store'), $this->payload('Yüz Daire', 100))
            ->assertRedirect();

        $apartment = Apartment::where('name', 'Yüz Daire')->firstOrFail();
        $this->assertSame('free', $apartment->billing_plan);
        $this->assertSame(100, $apartment->unit_count);
        $this->assertSame(1, Subscription::query()->count());
        $item = SubscriptionItem::query()->where('apartment_id', $apartment->id)->firstOrFail();
        $this->assertSame(SubscriptionItem::PLAN_FREE, $item->plan);
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $item->status);
        $this->assertSame(0, UserSubscription::query()->count());
        $this->assertSame(0, QuoteRequest::query()->count());
    }

    public function test_one_hundred_one_units_create_a_quote_request_without_commercial_records(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('subscriber.apartments.create'))
            ->post(route('subscriber.apartments.store'), $this->payload('Yüz Bir', 101, false))
            ->assertRedirect(route('subscriber.apartments.create'))
            ->assertSessionHas('status', ApartmentCommercial::QUOTE_MESSAGE);

        $this->assertDatabaseCount('apartments', 0);
        $this->assertDatabaseCount('quote_requests', 1);
        $this->assertDatabaseHas('quote_requests', [
            'user_id' => $user->id,
            'apartment_name' => 'Yüz Bir',
            'unit_count' => 101,
            'status' => QuoteRequest::STATUS_PENDING,
        ]);
        $this->assertSame(0, Subscription::query()->count());
        $this->assertSame(0, SubscriptionItem::query()->count());
        $this->assertSame(0, UserSubscription::query()->count());
        $this->assertSame(0, SubscriptionPayment::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, LegalAcceptance::query()->count());

        $quote = app(PriceQuote::class)->forUnits(101);
        $this->assertTrue($quote['requires_quote']);
        $this->assertNull($quote['amount']);
    }

    public function test_quote_scale_does_not_ask_for_a_sales_contract(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('subscriber.apartments.create'))
            ->assertOk()
            ->assertDontSee('Ücretli kullanım')
            ->assertDontSee('name="accept_sales"', false)
            ->assertDontSee('name="wants_paid"', false);

        $this->actingAs($user)
            ->post(route('subscriber.apartments.store'), [
                'name' => 'Sözleşmesiz Blok',
                'address' => 'Adres',
                'unit_count' => 120,
                'account_opening_date' => now()->toDateString(),
            ])
            ->assertSessionHas('status', ApartmentCommercial::QUOTE_MESSAGE)
            ->assertSessionHasNoErrors();

        $this->assertSame(0, LegalAcceptance::query()->whereIn('document_key', [
            LegalConsent::DISTANCE_SALES,
            LegalConsent::PRE_INFORMATION,
            LegalConsent::RESIDENT_DATA,
        ])->count());
    }

    public function test_pending_request_is_not_duplicated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('subscriber.apartments.store'), $this->payload('Aynı Blok', 110, false));
        $this->actingAs($user)
            ->post(route('subscriber.apartments.store'), $this->payload('Aynı Blok', 110, false))
            ->assertSessionHas('status', ApartmentCommercial::QUOTE_MESSAGE);

        $this->assertSame(1, QuoteRequest::query()->count());
        $this->assertSame(QuoteRequest::STATUS_PENDING, QuoteRequest::query()->first()->status);
    }

    public function test_contacted_request_is_not_duplicated(): void
    {
        $user = User::factory()->create();
        QuoteRequest::create([
            'user_id' => $user->id,
            'apartment_name' => 'Görüşülen Blok',
            'unit_count' => 130,
            'status' => QuoteRequest::STATUS_CONTACTED,
        ]);

        $this->actingAs($user)
            ->post(route('subscriber.apartments.store'), $this->payload('Görüşülen Blok', 130, false))
            ->assertSessionHas('status', ApartmentCommercial::QUOTE_MESSAGE);

        $this->assertSame(1, QuoteRequest::query()->count());
        $this->assertSame(QuoteRequest::STATUS_CONTACTED, QuoteRequest::query()->first()->status);
    }

    public function test_cancelled_request_does_not_block_a_new_one(): void
    {
        $user = User::factory()->create();
        QuoteRequest::create([
            'user_id' => $user->id,
            'apartment_name' => 'İptal Blok',
            'unit_count' => 140,
            'status' => QuoteRequest::STATUS_CANCELLED,
        ]);

        $this->actingAs($user)->post(route('subscriber.apartments.store'), $this->payload('İptal Blok', 140, false));

        $this->assertSame(2, QuoteRequest::query()->count());
        $this->assertDatabaseHas('quote_requests', [
            'apartment_name' => 'İptal Blok',
            'status' => QuoteRequest::STATUS_PENDING,
        ]);
    }

    public function test_converted_request_does_not_block_a_new_one(): void
    {
        $user = User::factory()->create();
        QuoteRequest::create([
            'user_id' => $user->id,
            'apartment_name' => 'Dönüşen Blok',
            'unit_count' => 150,
            'status' => QuoteRequest::STATUS_CONVERTED,
        ]);

        $this->actingAs($user)->post(route('subscriber.apartments.store'), $this->payload('Dönüşen Blok', 150, false));

        $this->assertSame(2, QuoteRequest::query()->count());
        $this->assertDatabaseHas('quote_requests', [
            'apartment_name' => 'Dönüşen Blok',
            'status' => QuoteRequest::STATUS_PENDING,
        ]);
    }

    public function test_existing_free_apartment_can_still_move_to_a_paid_order(): void
    {
        $user = User::factory()->create();
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
            ])
            ->assertRedirect();

        $order = UserSubscription::query()->firstOrFail();
        $this->assertTrue($order->isPending());
        $item = SubscriptionItem::query()->where('subscription_id', $order->id)->firstOrFail();
        $this->assertSame(SubscriptionItem::PLAN_PAID, $item->plan);
        $this->assertSame(10, $item->unit_count);
        $this->assertGreaterThan(0, (float) $item->amount);
    }

    public function test_quote_scale_apartment_cannot_open_a_member_order(): void
    {
        $user = User::factory()->create();
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

        $quote = app(PriceQuote::class)->forUnits(160, 'monthly', $apartment);
        $this->assertFalse($quote['requires_quote']);
        $this->assertEquals(1200, $quote['amount']);

        $this->actingAs($user)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertDontSee('Ücretliye Geç')
            ->assertSee('Özel Teklif');

        $this->actingAs($user)
            ->from(route('subscriber.subscriptions.create'))
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'monthly',
                'payment_method' => 'havale',
                'accept_sales' => '1',
            ])
            ->assertRedirect(route('subscriber.subscriptions.create'))
            ->assertSessionHasErrors('apartment_ids');

        $this->assertSame(0, UserSubscription::query()->count());
    }

    public function test_admin_can_list_quote_requests_and_change_status(): void
    {
        $user = User::factory()->create(['name' => 'Talep Eden']);
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
