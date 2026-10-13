<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserQuotaOverride;
use App\Models\UserSubscription;
use App\Support\CurrentApartment;
use App\Support\UserApartmentQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApartmentCreateQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_succeeds_when_the_active_count_is_under_the_limit(): void
    {
        $user = User::factory()->create();
        $this->limit($user, 2);

        $this->postCreate($user)->assertRedirect();
        $this->assertSame(1, $this->activeCount($user));

        $created = Apartment::query()->where('user_id', $user->id)->firstOrFail();
        $this->actingAs($user)
            ->withSession([CurrentApartment::SESSION_KEY => $created->id])
            ->post(route('apartments.store'), [
                'name' => 'İkinci Apartman',
                'address' => 'Adres satırı',
                'unit_count' => 4,
                'account_opening_date' => now()->toDateString(),
                'accept_resident_data' => '1',
                'accept_privacy' => '1',
            ])->assertRedirect();
        $this->assertSame(2, $this->activeCount($user));
    }

    public function test_onboarding_can_create_the_first_apartment_under_the_limit(): void
    {
        $user = User::factory()->create();
        $this->limit($user, 2);

        $this->actingAs($user)->post(route('onboarding.store'), [
            'name' => 'İlk Apartman',
            'unit_count' => 4,
            'manager_type' => 'external',
            'accept_resident_data' => '1',
            'accept_privacy' => '1',
        ])->assertRedirect();

        $this->assertSame(1, $this->activeCount($user));
    }

    public function test_creation_is_blocked_when_the_active_count_reaches_the_limit(): void
    {
        $user = User::factory()->create();
        $this->limit($user, 2);
        $this->apartment($user, true, 'free');
        $kept = $this->apartment($user, true, 'paid');

        $this->postCreate($user)
            ->assertSessionHasErrors('name');

        $this->assertSame(2, $this->activeCount($user));
        $this->assertTrue($kept->fresh()->is_active);

        $this->actingAs($user)
            ->get(route('subscriber.apartments.create'))
            ->assertOk()
            ->assertSee(UserApartmentQuota::LIMIT_MESSAGE)
            ->assertDontSee('Apartman Adı');

        $this->actingAs($user)
            ->withSession([CurrentApartment::SESSION_KEY => $kept->id])
            ->get(route('apartments.index'))
            ->assertOk()
            ->assertSee($kept->name);
    }

    public function test_inactive_apartments_and_other_memberships_do_not_fill_the_quota(): void
    {
        $user = User::factory()->create();
        $this->limit($user, 2);
        $this->apartment($user, true, 'free');
        $this->apartment($user, false, 'paid');

        $otherOwner = User::factory()->create();
        $other = $this->apartment($otherOwner, true, 'free');
        $other->members()->attach($user->id, ['role' => 'member', 'is_active' => true]);

        $this->postCreate($user)->assertRedirect();
        $this->assertSame(2, $this->activeCount($user));
        $this->assertTrue($user->apartments()->whereKey($other->id)->exists());
    }

    public function test_admin_can_raise_and_lower_the_limit_without_closing_apartments(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->limit($user, 1);
        $apartment = $this->apartment($user, true, 'free');

        $this->actingAs($admin)
            ->patch(route('admin.customers.quota.update', $user), ['max_apartments' => 2])
            ->assertRedirect(route('admin.customers.show', $user));

        $this->assertSame(2, $user->fresh()->quotaOverride->max_apartments);
        $this->postCreate($user)->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('admin.customers.quota.update', $user), ['max_apartments' => 1])
            ->assertRedirect();

        $this->assertSame(1, $user->fresh()->quotaOverride->max_apartments);
        $this->assertSame(2, $this->activeCount($user));
        $this->assertTrue($apartment->fresh()->is_active);
        $this->postCreate($user)->assertSessionHasErrors('name');
    }

    public function test_setup_renewal_can_open_a_new_apartment_while_the_quota_is_full(): void
    {
        $user = User::factory()->create();
        $this->limit($user, 1);
        $apartment = Apartment::factory()->forUser($user)->create([
            'name' => 'Ücretli Apartman',
            'unit_count' => 10,
            'is_active' => true,
            'address' => 'Adres',
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        $order = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'started_at' => now()->subMonth(),
            'expires_at' => now()->addYear(),
        ]);
        SubscriptionItem::query()->create([
            'subscription_id' => $order->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => 10,
            'band_min_units' => 1,
            'band_max_units' => 14,
            'band_label' => '1–14',
            'amount' => 100,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subMonth(),
            'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($user)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('apartments.renew-setup', $apartment), [
                'name' => 'Yeni Kurulum',
                'address' => 'Adres',
                'account_opening_date' => now()->toDateString(),
                'accept_resident_data' => '1',
                'accept_privacy' => '1',
                'current_password' => 'password',
                'unit_count' => 20,
                'confirmation' => 'kurulumun yenilenmesini kabul ediyorum',
                'accept_new_free_apartment' => '1',
                'skip_archive_check' => '1',
            ])->assertRedirect();

        $this->assertFalse($apartment->fresh()->is_active);
        $this->assertSame(1, $this->activeCount($user));
        $this->assertNotNull(Apartment::query()->where('user_id', $user->id)->where('is_active', true)->where('name', 'Yeni Kurulum')->first());
    }

    public function test_users_without_a_defined_limit_can_still_create(): void
    {
        $user = User::factory()->create();

        $this->assertNull(app(UserApartmentQuota::class)->definedLimit($user));
        $this->postCreate($user)->assertRedirect();
    }

    private function limit(User $user, int $max): void
    {
        UserQuotaOverride::query()->create([
            'user_id' => $user->id,
            'max_apartments' => $max,
        ]);
    }

    private function apartment(User $user, bool $active, string $plan): Apartment
    {
        $apartment = Apartment::factory()->forUser($user)->create([
            'is_active' => $active,
            'billing_plan' => $plan,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

        return $apartment;
    }

    private function activeCount(User $user): int
    {
        return app(UserApartmentQuota::class)->currentCount($user);
    }

    private function postCreate(User $user)
    {
        return $this->actingAs($user)->post(route('subscriber.apartments.store'), [
            'name' => 'Kota Apartmanı '.$user->id.'-'.Apartment::query()->count(),
            'address' => 'Adres satırı',
            'unit_count' => 4,
            'account_opening_date' => now()->toDateString(),
            'accept_resident_data' => '1',
            'accept_privacy' => '1',
        ]);
    }
}
