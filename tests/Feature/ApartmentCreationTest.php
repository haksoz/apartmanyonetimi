<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApartmentCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_apartment_with_units_and_accounts(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('apartments.store'), [
            'name' => 'Akbey Apartmanı',
            'address' => 'Adil Mah. Akbey Sk. No:10',
            'unit_count' => 12,
            'manager_unit_no' => 3,
        ]);

        $response->assertRedirect(route('apartments.index'));

        $apartment = Apartment::firstOrFail();

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

        $firstUnit = Unit::where('apartment_id', $apartment->id)->where('unit_no', '1')->firstOrFail();
        $this->assertNotNull($firstUnit->owner_account_id);
        $this->assertSame($firstUnit->owner_account_id, $firstUnit->occupant_account_id);

        $this->actingAs($user)
            ->get(route('apartments.show', $apartment))
            ->assertStatus(200)
            ->assertSee('Akbey Apartmanı');
    }
}
