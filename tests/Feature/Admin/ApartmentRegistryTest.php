<?php

namespace Tests\Feature\Admin;

use App\Models\Apartment;
use App\Models\ManagementOffer;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApartmentRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_apartment_registry_shows_current_managers_instead_of_assuming_the_founder_is_manager(): void
    {
        $admin = User::factory()->admin()->create();
        $founder = User::factory()->create(['name' => 'Eski Kurucu']);
        $current = User::factory()->create(['name' => 'Yeni Yonetici']);
        $pending = User::factory()->create(['name' => 'Onaysiz Uye']);
        $apartment = Apartment::factory()->forUser($founder)->create(['name' => 'Kimlik Apartmani', 'code' => 'AB12']);
        $apartment->members()->attach($founder->id, ['role' => 'owner', 'is_active' => false]);
        $apartment->members()->attach($current->id, ['role' => 'owner', 'is_active' => true]);
        $apartment->members()->attach($pending->id, ['role' => 'member', 'is_active' => true]);
        $subscription = Subscription::create(['apartment_id' => $apartment->id, 'subscription_no' => 'ABN-26-123456', 'status' => 'active']);
        $this->actingAs($admin)->get(route('admin.apartments.index'))->assertOk()
            ->assertSee('Apartmanlar')->assertSee('Kimlik Apartmani')->assertSee('AB12')
            ->assertSee('Yeni Yonetici')->assertDontSee('Eski Kurucu')->assertDontSee('Onaysiz Uye')
            ->assertSee(route('admin.customers.show', $current), false)
            ->assertSee(route('admin.apartments.show', $apartment), false)
            ->assertSee($subscription->subscription_no);
        $this->get(route('admin.apartments.show', $apartment))->assertOk()
            ->assertSee('Kurucu')->assertSee('Eski Kurucu')->assertSee('Yeni Yonetici')
            ->assertViewHas('apartment', fn ($row) => $row->managers->modelKeys() === [$current->id]);
    }

    public function test_management_acceptance_keeps_apartment_subscription_and_old_order_identities(): void
    {
        $founder = User::factory()->create(['name' => 'Ilk Musteri']);
        $newManager = User::factory()->create(['name' => 'Yeni Musteri']);
        $apartment = Apartment::factory()->forUser($founder)->create(['unit_count' => 8, 'code' => 'CD34']);
        $apartment->members()->attach($founder->id, ['role' => 'owner', 'is_active' => true]);
        $apartment->members()->attach($newManager->id, ['role' => 'member', 'is_active' => true]);
        $subscription = Subscription::create(['apartment_id' => $apartment->id, 'subscription_no' => 'ABN-26-654321', 'status' => 'active']);
        $oldOrder = UserSubscription::factory()->create([
            'user_id' => $founder->id, 'subscription_id' => $subscription->id,
            'order_number' => 'SIP-26-ESKI', 'price' => 120,
        ]);
        $offer = ManagementOffer::create([
            'apartment_id' => $apartment->id, 'user_id' => $newManager->id, 'offered_by' => $founder->id,
            'status' => 'pending', 'expires_at' => now()->addDays(7),
        ]);
        $this->actingAs($newManager)->post(route('management-offers.accept', $offer), [
            'accept_resident_data' => 1, 'accept_privacy' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('subscriber.dashboard'));
        $this->assertDatabaseCount('apartments', 1);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertSame('CD34', $apartment->fresh()->code);
        $this->assertSame($founder->id, $apartment->fresh()->user_id);
        $this->assertSame($apartment->id, $subscription->fresh()->apartment_id);
        $this->assertSame('ABN-26-654321', $subscription->fresh()->subscription_no);
        $this->assertSame($founder->id, $oldOrder->fresh()->user_id);
        $this->assertSame(1, $apartment->managers()->count());
        $this->assertFalse((bool) $apartment->members()->whereKey($founder->id)->first()->pivot->is_active);
        $newOrder = UserSubscription::factory()->create([
            'user_id' => $newManager->id, 'subscription_id' => $subscription->id, 'order_number' => 'SIP-26-YENI',
        ]);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.apartments.show', $apartment))->assertOk()
            ->assertSee('SIP-26-ESKI')->assertSee('SIP-26-YENI')->assertSee('Ilk Musteri')->assertSee('Yeni Musteri')
            ->assertSee('Kabul edildi')->assertSee($subscription->subscription_no)
            ->assertViewHas('apartment', fn ($row) => $row->managers->modelKeys() === [$newManager->id])
            ->assertViewHas('orders', fn ($orders) => $orders->modelKeys() === [$newOrder->id, $oldOrder->id]);
    }

    public function test_registry_filters_by_identity_current_manager_and_status(): void
    {
        $manager = User::factory()->create(['name' => 'Aranan Yonetici']);
        $first = Apartment::factory()->create(['name' => 'Aranan Apartman', 'code' => 'EF56', 'is_active' => true]);
        $first->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);
        $second = Apartment::factory()->create(['name' => 'Diger Apartman', 'is_active' => false]);
        $second->members()->attach($manager->id, ['role' => 'member', 'is_active' => true]);
        Subscription::create(['apartment_id' => $first->id, 'subscription_no' => 'ABN-26-111111', 'status' => 'active']);
        $this->actingAs(User::factory()->admin()->create());
        foreach (['EF56', 'ABN-26-111111', 'Aranan Yonetici', $manager->email] as $search) {
            $this->get(route('admin.apartments.index', ['search' => $search]))->assertOk()
                ->assertSee('Aranan Apartman')->assertDontSee('Diger Apartman');
        }
        $this->get(route('admin.apartments.index', ['status' => 'inactive']))->assertOk()
            ->assertSee('Diger Apartman')->assertDontSee('Aranan Apartman');
        $second->delete();
        $this->get(route('admin.apartments.show', $second))->assertOk()->assertSee('Silinmiş kayıt');
    }

    public function test_non_admin_cannot_open_registry_or_details(): void
    {
        $apartment = Apartment::factory()->create();
        $this->get(route('admin.apartments.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.apartments.index'))->assertForbidden();
        $this->get(route('admin.apartments.show', $apartment))->assertForbidden();
    }
}
