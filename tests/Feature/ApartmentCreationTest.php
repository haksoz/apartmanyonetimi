<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Package;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApartmentCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_apartment_with_units_and_accounts(): void
    {
        $package = Package::factory()->create(['apartment_limit' => 5]);
        $user = User::factory()->withSubscription($package)->create();
        $existingApartment = Apartment::factory()->forUser($user)->create();
        $user->apartments()->attach($existingApartment->id, ['role' => 'owner', 'is_active' => true]);

        $response = $this->withSession([CurrentApartment::SESSION_KEY => $existingApartment->id])
            ->actingAs($user)
            ->post(route('apartments.store'), [
                'name' => 'Akbey Apartmanı',
                'address' => 'Adil Mah. Akbey Sk. No:10',
                'unit_count' => 12,
                'manager_unit_no' => 3,
                'account_opening_date' => now()->format('Y-m-d'),
            ]);

        $response->assertRedirect(route('apartments.index'));

        $apartment = Apartment::where('name', 'Akbey Apartmanı')->firstOrFail();

        $this->assertSame($user->id, $apartment->user_id);
        $this->assertNotNull($apartment->manager_unit_id);
        $this->assertDatabaseHas('apartment_user', [
            'apartment_id' => $apartment->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertSame(12, Unit::where('apartment_id', $apartment->id)->count());
        $this->assertSame(12, Account::where('apartment_id', $apartment->id)->count());
        $this->assertSame(12, Account::where('apartment_id', $apartment->id)->where('type', Account::TYPE_OWNER)->count());

        $firstUnit = Unit::where('apartment_id', $apartment->id)->where('unit_no', '01')->firstOrFail();
        $this->assertNotNull($firstUnit->owner_account_id);
        $this->assertSame($firstUnit->owner_account_id, $firstUnit->occupant_account_id);

        $this->actingAs($user)
            ->get(route('apartments.show', $apartment))
            ->assertStatus(200)
            ->assertSee('Akbey Apartmanı');
    }
}
