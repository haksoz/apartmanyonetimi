<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\PriceBand;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\CurrentApartment;
use App\Support\FeatureGate;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class CommercialBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_free_apartment_stays_usable_without_a_subscription(): void
    {
        $user = User::factory()->create();
        $apartment = $this->own($user, ['name' => 'Mevcut Apartman', 'unit_count' => 8, 'billing_plan' => 'free']);

        $this->actingAs($user)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('dues.index'))
            ->assertOk();

        $this->actingAs($user)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('expenses.index'))
            ->assertOk();

        $this->actingAs($user)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('payments.index'))
            ->assertOk();

        $this->actingAs($user)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('cash.index'))
            ->assertOk();

        $this->actingAs($user)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('ledger.index'))
            ->assertOk();

        $this->actingAs($user)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('reports.due-collection'))
            ->assertForbidden();

        $this->assertFalse(FeatureGate::allows($apartment, 'report_pdf', $user));
        $this->assertFalse(FeatureGate::allows($apartment, 'auto_dues', $user));
    }

    public function test_one_hundred_units_can_be_created_free(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('subscriber.apartments.store'), $this->payload('Yüz Daire', 100))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('apartments', [
            'name' => 'Yüz Daire',
            'unit_count' => 100,
            'billing_plan' => 'free',
        ]);

        $apartment = Apartment::where('name', 'Yüz Daire')->firstOrFail();
        $item = SubscriptionItem::query()->where('apartment_id', $apartment->id)->firstOrFail();
        $this->assertSame(SubscriptionItem::PLAN_FREE, $item->plan);
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $item->status);
        $this->assertEquals(0, (float) $item->amount);
        $this->assertNull($item->expires_at);
        $this->assertNull($item->subscription_id);
        $this->assertNull($item->ended_at);
        $this->assertNotNull($item->started_at);
        $this->assertSame(1, Subscription::query()->count());
        $this->assertSame(0, UserSubscription::query()->count());

        $record = Subscription::query()->firstOrFail();
        $this->assertSame($apartment->id, $record->apartment_id);
        $this->assertSame(Subscription::STATUS_ACTIVE, $record->status);
        $this->assertNotNull($record->subscription_no);
        $this->assertNotNull($record->uuid);
        $this->assertNull($record->ended_at);
        $this->assertTrue($record->started_at->equalTo($item->started_at));
        $this->assertSame($record->id, $item->apartment_subscription_id);
    }

    public function test_each_create_flow_opens_a_free_period_and_factory_does_not(): void
    {
        $user = User::factory()->create();
        Apartment::factory()->forUser($user)->create(['name' => 'Fabrika']);
        $this->assertDatabaseCount('subscription_items', 0);
        $this->assertDatabaseCount('subscriptions', 0);

        $this->actingAs($user)
            ->post(route('onboarding.store'), [
                'name' => 'Ilk Apartman',
                'address' => 'Adres',
                'unit_count' => 2,
                'manager_type' => 'external',
                'accept_resident_data' => '1',
                'accept_privacy' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('apartments.store'), [
                'name' => 'Ikinci Apartman',
                'address' => 'Adres',
                'unit_count' => 2,
                'account_opening_date' => now()->format('Y-m-d'),
                'accept_resident_data' => '1',
                'accept_privacy' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('subscriber.apartments.store'), $this->payload('Ucuncu Apartman', 8))
            ->assertSessionHasNoErrors();

        foreach (['Ilk Apartman', 'Ikinci Apartman', 'Ucuncu Apartman'] as $name) {
            $apartment = Apartment::where('name', $name)->firstOrFail();
            $item = SubscriptionItem::query()->where('apartment_id', $apartment->id)->firstOrFail();
            $this->assertNull($item->subscription_id);
            $this->assertSame(SubscriptionItem::PLAN_FREE, $item->plan);
            $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $item->status);
            $this->assertNull($item->expires_at);
            $this->assertNull($item->ended_at);
            $this->assertNotNull($item->started_at);
            $this->assertNotNull($item->apartmentSubscription);
            $this->assertSame($apartment->id, $item->apartmentSubscription->apartment_id);
            $this->assertSame($item->apartmentSubscription->id, $item->apartment_subscription_id);
            $this->assertNotNull($item->apartmentSubscription->subscription_no);
            $this->assertNotNull($item->apartmentSubscription->uuid);
        }

        $this->assertSame(3, Subscription::query()->count());
        $this->assertSame(0, UserSubscription::query()->count());
    }

    public function test_admin_apartment_create_uses_the_shared_free_subscription(): void
    {
        $admin = User::factory()->admin()->create();
        Apartment::factory()->create(['name' => 'Mevcut Kayit', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('apartments.store'), [
                'name' => 'Admin Apartmani',
                'address' => 'Admin adres',
                'unit_count' => 10,
                'account_opening_date' => now()->toDateString(),
                'accept_resident_data' => '1',
                'accept_privacy' => '1',
            ])
            ->assertSessionHasNoErrors();

        $apartment = Apartment::where('name', 'Admin Apartmani')->firstOrFail();
        $record = Subscription::query()->firstOrFail();
        $item = SubscriptionItem::query()->where('apartment_id', $apartment->id)->firstOrFail();

        $this->assertSame(1, Subscription::query()->count());
        $this->assertSame(0, UserSubscription::query()->count());
        $this->assertSame($apartment->id, $record->apartment_id);
        $this->assertNotNull($record->subscription_no);
        $this->assertNotNull($record->uuid);
        $this->assertSame($record->id, $item->apartment_subscription_id);
        $this->assertNull($item->subscription_id);
        $this->assertSame(SubscriptionItem::PLAN_FREE, $item->plan);
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $item->status);
    }

    public function test_a_failed_apartment_create_rolls_back_the_free_subscription(): void
    {
        $user = User::factory()->create();
        $apartment = $this->own($user, ['name' => 'Yarim Apartman', 'unit_count' => 4, 'billing_plan' => 'free']);

        try {
            DB::transaction(function () use ($user, $apartment) {
                app(SubscriptionCheckout::class)->openFree($user, $apartment);
                throw new RuntimeException('Apartman kaydı tamamlanamadı.');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, Subscription::query()->count());
        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->assertDatabaseCount('subscription_items', 0);
        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_one_hundred_one_units_cannot_be_created_free(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('subscriber.apartments.create'))
            ->post(route('subscriber.apartments.store'), $this->payload('Yüz Bir', 101))
            ->assertRedirect(route('subscriber.apartments.create'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('apartments', 0);
        $this->assertDatabaseCount('quote_requests', 1);
    }

    public function test_one_hundred_one_units_do_not_open_a_paid_order(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('subscriber.apartments.store'), $this->payload('Ücretli Blok', 101, true))
            ->assertRedirect();

        $this->assertDatabaseMissing('apartments', ['name' => 'Ücretli Blok']);
        $this->assertDatabaseCount('quote_requests', 1);
        $this->assertSame(0, Subscription::query()->count());
        $this->assertSame(0, UserSubscription::query()->count());
        $this->assertSame(0, SubscriptionItem::query()->count());
    }

    public function test_same_user_can_keep_a_free_apartment_and_a_quote_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('subscriber.apartments.store'), $this->payload('Ücretsiz Blok', 12));
        $this->actingAs($user)->post(route('subscriber.apartments.store'), $this->payload('Ücretli Blok', 101, true));

        $this->assertDatabaseHas('apartments', ['name' => 'Ücretsiz Blok', 'billing_plan' => 'free']);
        $this->assertDatabaseMissing('apartments', ['name' => 'Ücretli Blok']);
        $this->assertDatabaseHas('quote_requests', ['apartment_name' => 'Ücretli Blok', 'unit_count' => 101]);
    }

    public function test_item_amount_does_not_change_when_the_apartment_is_renamed(): void
    {
        $user = User::factory()->create();
        $apartment = $this->own($user, ['name' => 'Eski Ad', 'unit_count' => 10, 'billing_plan' => 'free']);
        Subscription::create([
            'apartment_id' => $apartment->id,
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'yearly',
                'payment_method' => 'havale',
                'accept_sales' => '1',
            ])
            ->assertRedirect();

        $item = SubscriptionItem::query()->where('apartment_id', $apartment->id)->firstOrFail();
        $this->assertSame('Eski Ad', $item->apartment_name);
        $this->assertEquals(1500, (float) $item->amount);

        $apartment->update(['name' => 'Yeni Ad', 'unit_count' => 40]);
        $item->refresh();

        $this->assertSame('Eski Ad', $item->apartment_name);
        $this->assertSame(10, $item->unit_count);
        $this->assertEquals(1500, (float) $item->amount);
    }

    public function test_admin_can_edit_a_band_and_store_a_custom_quote(): void
    {
        $admin = User::factory()->admin()->create();
        $band = PriceBand::query()->where('min_units', 1)->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.commercial.index'))
            ->assertOk()
            ->assertSee('1–14 daire')
            ->assertSee('Otomatik aidat');

        $this->actingAs($admin)
            ->patch(route('admin.commercial.bands.update', $band), [
                'monthly_price' => 175,
                'yearly_price' => 1750,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertEquals(175, (float) $band->fresh()->monthly_price);

        $manager = User::factory()->create();
        $apartment = Apartment::factory()->forUser($manager)->create(['unit_count' => 160]);

        $this->actingAs($admin)
            ->patch(route('admin.apartments.price', $apartment), [
                'custom_monthly_price' => 1200,
                'custom_yearly_price' => 12000,
            ])
            ->assertRedirect();

        $apartment->refresh();
        $this->assertEquals(1200, (float) $apartment->custom_monthly_price);
        $this->assertEquals(12000, (float) $apartment->custom_yearly_price);
    }

    private function own(User $user, array $attributes): Apartment
    {
        $apartment = Apartment::factory()->forUser($user)->create($attributes);
        $user->apartments()->attach($apartment->id, ['role' => 'owner', 'is_active' => true]);

        return $apartment;
    }

    private function payload(string $name, int $unitCount, bool $paid = false): array
    {
        return [
            'name' => $name,
            'address' => 'Adres',
            'unit_count' => $unitCount,
            'account_opening_date' => now()->toDateString(),
            'wants_paid' => $paid ? '1' : '0',
            'accept_sales' => $paid ? '1' : '0',
            'accept_resident_data' => '1',
            'accept_privacy' => '1',
        ];
    }
}
