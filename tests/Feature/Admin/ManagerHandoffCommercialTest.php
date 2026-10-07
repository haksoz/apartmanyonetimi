<?php

namespace Tests\Feature\Admin;

use App\Models\Apartment;
use App\Models\Package;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\CurrentApartment;
use App\Support\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerHandoffCommercialTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_access_follows_the_active_item_not_billing_plan(): void
    {
        $payer = User::factory()->create();
        $apartment = Apartment::factory()->forUser($payer)->create([
            'name' => 'C Apartmanı',
            'unit_count' => 70,
            'billing_plan' => 'free',
        ]);
        $apartment->members()->attach($payer->id, ['role' => 'owner', 'is_active' => true]);
        $this->cover($payer, $apartment, 900, now()->addYear());

        $this->assertTrue(FeatureGate::allows($apartment->fresh(), 'auto_dues', $payer));
    }

    public function test_a_period_that_has_not_started_is_not_paid_coverage(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['name' => 'Gelecek Yonetici']);
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => 'Gelecek Apartman',
            'unit_count' => 10,
            'billing_plan' => 'paid',
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);

        $subscription = $this->cover($manager, $apartment, 150, now()->addYear());
        $subscription->update(['started_at' => now()->addMonth()]);
        $subscription->items()->update(['started_at' => now()->addMonth()]);

        $this->assertFalse($subscription->fresh()->isCovering());
        $this->assertFalse(FeatureGate::allows($apartment->fresh(), 'auto_dues', $manager));

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['search' => 'Gelecek']))
            ->assertOk()
            ->assertSee('Gelecek Apartman')
            ->assertSee('Ücretli');
    }

    public function test_manager_change_keeps_the_purchase_with_the_payer_and_history_on_the_apartment(): void
    {
        $admin = User::factory()->admin()->create();
        $hasan = User::factory()->create(['name' => 'Hasan Yonetici']);
        $mehmet = User::factory()->create(['name' => 'Mehmet Yonetici']);
        $apartment = Apartment::factory()->forUser($hasan)->create([
            'name' => 'C Apartmanı',
            'unit_count' => 70,
            'billing_plan' => 'free',
            'setup_completed_at' => now(),
        ]);
        $apartment->members()->attach($hasan->id, ['role' => 'owner', 'is_active' => true]);

        $hasanOrder = $this->cover($hasan, $apartment, 900, now()->addYear());
        $hasanOrder->update(['order_number' => 'SIP-26-HASAN1']);

        \App\Models\Subscription::create([
            'apartment_id' => $apartment->id,
            'status' => \App\Models\Subscription::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        $apartment->members()->updateExistingPivot($hasan->id, ['role' => 'member', 'is_active' => false]);
        $apartment->members()->attach($mehmet->id, ['role' => 'owner', 'is_active' => true]);

        $this->actingAs($mehmet)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSee('Bu hizmet C Apartmanı için Hasan Yonetici tarafından satın alınmıştır.')
            ->assertDontSee('Ödemeyi tamamla');

        $this->actingAs($mehmet)
            ->get(route('subscriber.subscriptions.index'))
            ->assertOk()
            ->assertDontSee('SIP-26-HASAN1');

        $this->actingAs($mehmet)
            ->get(route('subscriber.subscriptions.receipt', $hasanOrder))
            ->assertForbidden();

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($mehmet)
            ->get(route('apartments.show', $apartment))
            ->assertOk()
            ->assertSee('Hizmet geçmişi')
            ->assertSee('Hasan Yonetici')
            ->assertSee('Aktif')
            ->assertDontSee('Dekont');

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['search' => 'Hasan']))
            ->assertOk()
            ->assertSee('C Apartmanı')
            ->assertSee('SIP-26-HASAN1')
            ->assertSee('Mehmet Yonetici');

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['search' => 'Mehmet']))
            ->assertOk()
            ->assertSee('C Apartmanı')
            ->assertSee('Hasan Yonetici')
            ->assertSee('Aktif');

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'customers', 'search' => 'C Apartmanı']))
            ->assertOk()
            ->assertSee('Hasan Yonetici')
            ->assertSee('Mehmet Yonetici')
            ->assertSee('Ücretli');

        $this->actingAs($mehmet)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'yearly',
                'payment_method' => 'havale',
            ])
            ->assertRedirect();

        $mehmetOrder = $mehmet->fresh()->subscriptions()->pending()->first();
        $this->assertNotNull($mehmetOrder);
        $this->assertSame($mehmet->id, $mehmetOrder->user_id);
        $this->assertSame($apartment->id, $mehmetOrder->items()->first()->apartment_id);

        $hasanOrder->refresh();
        $this->assertSame($hasan->id, $hasanOrder->user_id);
        $this->assertSame('SIP-26-HASAN1', $hasanOrder->order_number);
        $this->assertTrue($hasanOrder->is_active);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($mehmet)
            ->get(route('apartments.show', $apartment))
            ->assertOk()
            ->assertSee('Hasan Yonetici')
            ->assertSee('Mehmet Yonetici');
    }

    private function cover(User $payer, Apartment $apartment, float $amount, $expiresAt): UserSubscription
    {
        $subscription = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'package_id' => Package::factory()->create()->id,
            'period' => 'yearly',
            'price' => $amount,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'expires_at' => $expiresAt,
            'started_at' => now()->subDay(),
        ]);

        SubscriptionItem::create([
            'subscription_id' => $subscription->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => $apartment->unit_count,
            'band_label' => '65–150 daire',
            'band_min_units' => 65,
            'band_max_units' => 150,
            'amount' => $amount,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
            'expires_at' => $expiresAt,
        ]);

        return $subscription;
    }
}
