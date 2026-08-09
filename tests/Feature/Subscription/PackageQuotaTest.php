<?php

namespace Tests\Feature\Subscription;

use App\Models\Apartment;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        session()->flush();
    }

    private function createOwnedApartment(User $user): Apartment
    {
        $apartment = Apartment::factory()->forUser($user)->create();
        $user->apartments()->attach($apartment->id, ['role' => 'owner', 'is_active' => true]);
        session([\App\Support\CurrentApartment::SESSION_KEY => $apartment->id]);

        return $apartment;
    }

    public function test_manager_can_create_first_apartment_onboarding(): void
    {
        $package = Package::factory()->create(['apartment_limit' => 1]);
        $user = User::factory()->withSubscription($package)->create();

        $response = $this->actingAs($user)
            ->post(route('onboarding.store'), [
                'name' => 'Akbey Apartmanı',
                'address' => 'Adres',
                'unit_count' => 2,
                'manager_type' => 'external',
            ]);

        $apartment = Apartment::where('name', 'Akbey Apartmanı')->firstOrFail();
        $response->assertRedirect(route('apartments.wizard.cash-box', $apartment));

        $this->assertDatabaseCount('apartments', 1);
    }

    public function test_manager_cannot_exceed_apartment_quota(): void
    {
        $package = Package::factory()->create(['apartment_limit' => 1]);
        $user = User::factory()->withSubscription($package)->create();
        $this->createOwnedApartment($user);

        $this->actingAs($user)
            ->post(route('apartments.store'), [
                'name' => 'Yeni Apartman',
                'address' => 'Adres',
                'unit_count' => 2,
                'manager_unit_no' => 1,
                'account_opening_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('quota');

        $this->assertDatabaseCount('apartments', 1);
    }

    public function test_quota_override_allows_exceeding_package_limit(): void
    {
        $package = Package::factory()->create(['apartment_limit' => 1]);
        $user = User::factory()->withSubscription($package)->withApartmentQuota(3)->create();
        $this->createOwnedApartment($user);
        $this->createOwnedApartment($user);

        $response = $this->actingAs($user)
            ->post(route('apartments.store'), [
                'name' => 'Üçüncü Apartman',
                'address' => 'Adres',
                'unit_count' => 2,
                'manager_unit_no' => 1,
                'account_opening_date' => now()->format('Y-m-d'),
            ]);

        $apartment = Apartment::where('name', 'Üçüncü Apartman')->firstOrFail();
        $response->assertRedirect(route('apartments.wizard.cash-box', $apartment));

        $this->assertDatabaseCount('apartments', 3);
    }

    public function test_onboarding_blocked_without_subscription(): void
    {
        $user = User::factory()->create(['role' => 'manager']);

        $this->actingAs($user)
            ->post(route('onboarding.store'), [
                'name' => 'Yeni Apartman',
                'address' => 'Adres',
                'unit_count' => 2,
                'manager_type' => 'external',
            ])
            ->assertSessionHasErrors('quota');
    }
}
