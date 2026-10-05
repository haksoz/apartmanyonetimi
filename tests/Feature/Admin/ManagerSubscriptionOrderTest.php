<?php

namespace Tests\Feature\Admin;

use App\Models\Apartment;
use App\Models\Package;
use App\Models\SubscriptionItem;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerSubscriptionOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_index_shows_pending_order_icon(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();
        $package = Package::factory()->create(['monthly_price' => 150]);

        UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'period' => 'monthly',
            'price' => 150,
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.managers.index'))
            ->assertOk()
            ->assertSee('Abonelik kalemi yok.')
            ->assertDontSee('Bekleyen sipariş var')
            ->assertDontSee($manager->name);
    }

    public function test_admin_index_tracks_a_pending_small_apartment_upgrade(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();
        $package = Package::factory()->create();
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => 'Küçük Apartman',
            'unit_count' => 12,
            'billing_plan' => 'free',
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);

        $pending = UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'period' => 'monthly',
            'price' => 150,
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'expires_at' => null,
        ]);

        SubscriptionItem::create([
            'subscription_id' => $pending->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => 'Küçük Apartman',
            'unit_count' => 12,
            'band_label' => '1–14 daire',
            'band_min_units' => 1,
            'band_max_units' => 14,
            'amount' => 150,
            'currency' => 'TRY',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.managers.index'))
            ->assertOk()
            ->assertSee('Küçük Apartman')
            ->assertSee('150')
            ->assertDontSee('Müşteriler')
            ->assertDontSee('Apartmanlar');

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'apartments']))
            ->assertOk()
            ->assertSee('Küçük Apartman')
            ->assertDontSee('Ücretli aç');

        $this->actingAs($admin)
            ->get(route('admin.managers.show', $manager))
            ->assertOk()
            ->assertSee('Küçük Apartman')
            ->assertSee('1–14 daire')
            ->assertDontSee('Yeni Paket Tanımla');
    }

    public function test_approving_an_item_order_does_not_close_another_apartment_period(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create();
        $package = Package::factory()->create();
        $kept = Apartment::factory()->forUser($manager)->create(['name' => 'Duran Apartman', 'unit_count' => 20]);
        $new = Apartment::factory()->forUser($manager)->create(['name' => 'Yeni Apartman', 'unit_count' => 12]);

        $active = UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'price' => 300,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ]);
        SubscriptionItem::create([
            'subscription_id' => $active->id,
            'apartment_id' => $kept->id,
            'apartment_name' => 'Duran Apartman',
            'unit_count' => 20,
            'band_label' => '15–32 daire',
            'band_min_units' => 15,
            'band_max_units' => 32,
            'amount' => 300,
            'currency' => 'TRY',
        ]);

        $pending = UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'price' => 150,
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'expires_at' => null,
        ]);
        SubscriptionItem::create([
            'subscription_id' => $pending->id,
            'apartment_id' => $new->id,
            'apartment_name' => 'Yeni Apartman',
            'unit_count' => 12,
            'band_label' => '1–14 daire',
            'band_min_units' => 1,
            'band_max_units' => 14,
            'amount' => 150,
            'currency' => 'TRY',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$manager, $pending]), [
                'payment_method' => 'havale',
                'reference_code' => 'HVL-2026-001',
            ])
            ->assertRedirect();

        $active->refresh();
        $pending->refresh();
        $this->assertTrue($active->is_active);
        $this->assertTrue($pending->is_active);
        $this->assertSame('paid', $new->fresh()->billing_plan);
        $this->assertSame('HVL-2026-001', $pending->payments()->first()->reference_code);
    }

    public function test_show_page_displays_receipt_reference_for_a_pending_order(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create();
        $package = Package::factory()->create();
        $pending = UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'expires_at' => null,
            'receipt_reference' => 'DEKONT-ABC',
            'payment_method' => 'havale',
        ]);
        $apartment = Apartment::factory()->forUser($manager)->create(['name' => 'Dekont Apartmanı', 'unit_count' => 8]);
        SubscriptionItem::create([
            'subscription_id' => $pending->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => 'Dekont Apartmanı',
            'unit_count' => 8,
            'band_label' => '1–14 daire',
            'band_min_units' => 1,
            'amount' => 150,
            'currency' => 'TRY',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.managers.show', $manager))
            ->assertOk()
            ->assertSee('Onay Bekliyor')
            ->assertSee('DEKONT-ABC');
    }

    public function test_free_apartment_is_listed_and_complimentary_months_open_paid_use(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['name' => 'Ucretsiz Yonetici']);
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => 'Ucretsiz Apartman',
            'unit_count' => 12,
            'billing_plan' => 'free',
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);
        Apartment::factory()->forUser($manager)->create([
            'name' => 'Kapali Apartman',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.managers.index'))
            ->assertOk()
            ->assertDontSee('Ucretsiz Apartman')
            ->assertDontSee('Ücretli aç')
            ->assertDontSee('Kapali Apartman');

        $this->actingAs($admin)
            ->post(route('admin.apartments.complimentary', $apartment), [
                'months' => 2,
                'user_id' => $manager->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $subscription = $manager->fresh()->subscriptions()->first();
        $this->assertNotNull($subscription);
        $this->assertSame('0.00', $subscription->price);
        $this->assertTrue($subscription->isCovering());
        $this->assertSame(0, $subscription->payments()->count());
        $this->assertSame(0, SubscriptionPayment::query()->count());
        $this->assertSame(2, $subscription->started_at->diff($subscription->expires_at)->m);
        $this->assertSame('paid', $apartment->fresh()->billing_plan);
        $this->assertTrue(FeatureGate::allows($apartment->fresh(), 'auto_dues', $manager));

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['search' => 'Ucretsiz Apartman']))
            ->assertOk()
            ->assertSee('Aktif')
            ->assertSee('Ücretli')
            ->assertSee('0 ₺')
            ->assertDontSee('Ücretli aç');

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['search' => 'Ucretsiz Yonetici']))
            ->assertOk()
            ->assertSee('Aktif');

        $this->actingAs($admin)
            ->post(route('admin.apartments.complimentary', $apartment), [
                'months' => 2,
                'user_id' => $manager->id,
            ])
            ->assertSessionHasErrors('months');
    }
}
