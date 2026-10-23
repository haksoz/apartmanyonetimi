<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportTenantOccupantTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_current_tenant_replaces_owner_as_unit_occupant(): void
    {
        $this->importTenant('tenant', false);
    }

    public function test_mapped_current_tenant_updates_unit_occupant(): void
    {
        $this->importTenant('tenant', true);
    }

    public function test_former_tenant_does_not_replace_current_unit_occupant(): void
    {
        $this->importTenant('former_tenant', false);
    }

    private function importTenant(string $type, bool $mapped): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create(['unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $owner = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'owner', 'name' => 'Owner', 'is_active' => true]);
        $unit->update(['owner_account_id' => $owner->id, 'occupant_account_id' => $owner->id]);
        $tenant = $mapped ? Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'tenant', 'name' => 'Tenant', 'is_active' => true]) : null;
        $date = today()->subYear()->toDateString();

        $this->actingAs($user)->withSession([
            CurrentApartment::SESSION_KEY => $apartment->id,
            'bulk_import_apartment_id' => $apartment->id,
            'bulk_import_accounts' => ['tenant-key' => ['name' => 'Tenant', 'unit_no' => '1', 'unit_id' => $unit->id]],
            'bulk_import_transactions' => [[
                'account_key' => 'tenant-key', 'date' => $date, 'category_name' => 'Aidat',
                'description' => 'Imported due', 'debit' => 500, 'credit' => 0,
            ]],
        ])->post(route('accounts.bulk-import-confirm'), [
            'account_types' => ['tenant-key' => $type],
            'account_mapping' => $tenant ? ['tenant-key' => $tenant->id] : [],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $tenant ??= Account::where('apartment_id', $apartment->id)->where('name', 'Tenant')->firstOrFail();
        $this->assertSame($type === 'tenant' ? $tenant->id : $owner->id, $unit->fresh()->occupant_account_id);
        $this->assertSame($owner->id, $unit->fresh()->owner_account_id);
        $this->assertDatabaseHas('tenant_assignments', [
            'unit_id' => $unit->id, 'account_id' => $tenant->id,
        ]);
        $assignment = $tenant->tenantAssignments()->firstOrFail();
        $this->assertSame($type === 'former_tenant' ? $date : null, $assignment->move_out_date?->toDateString());
    }
}
