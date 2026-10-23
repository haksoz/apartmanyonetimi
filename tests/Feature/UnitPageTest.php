<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_fields_only_display_current_tenants(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test Apartment', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Resident Example',
            'is_active' => true,
        ]);
        $unit->update(['occupant_account_id' => $account->id]);

        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);

        foreach ([Account::TYPE_OWNER, Account::TYPE_TENANT] as $type) {
            $account->update(['type' => $type]);
            foreach ([route('units.index'), route('units.show', $unit)] as $url) {
                $response = $this->get($url)->assertOk();
                if ($type === Account::TYPE_TENANT) {
                    $response->assertSee('Resident Example');
                } else {
                    $response->assertDontSee('Resident Example');
                }
            }
        }

        $account->update(['is_active' => false, 'account_end_date' => today()]);
        $this->get(route('units.index'))->assertOk()->assertDontSee('Resident Example');
        $this->get(route('units.show', $unit))->assertOk()->assertDontSee('Resident Example');
    }

    public function test_active_assignment_is_shown_when_occupant_still_points_to_owner(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $owner = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER, 'name' => 'Owner', 'is_active' => true]);
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => Account::TYPE_TENANT, 'name' => 'Imported Tenant', 'is_active' => true]);
        $unit->update(['owner_account_id' => $owner->id, 'occupant_account_id' => $owner->id]);
        $assignment = $unit->tenantAssignments()->create(['apartment_id' => $apartment->id,
            'account_id' => $tenant->id, 'move_in_date' => today()->subYear()]);

        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);
        $this->get(route('units.index'))->assertOk()->assertSee('Imported Tenant');
        $this->get(route('units.show', $unit))->assertOk()->assertSee('Imported Tenant');

        $assignment->update(['move_out_date' => today()]);
        $this->get(route('units.index'))->assertOk()->assertDontSee('Imported Tenant');
        $this->get(route('units.show', $unit))->assertOk()->assertSee('Henüz kiracı atanmamış.');
    }
}
