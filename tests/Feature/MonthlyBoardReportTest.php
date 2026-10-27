<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyBoardReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_board_always_shows_resident_account_types_without_a_visibility_control(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 2,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($apartment);

        $ownerUnit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $tenantUnit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '2']);
        $owner = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $ownerUnit->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Ali Malik',
            'is_active' => true,
        ]);
        $tenant = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $tenantUnit->id,
            'type' => Account::TYPE_TENANT,
            'name' => 'Ayşe Kiracı',
            'is_active' => true,
        ]);
        $ownerUnit->update(['owner_account_id' => $owner->id, 'occupant_account_id' => $owner->id]);
        $tenantUnit->update(['occupant_account_id' => $tenant->id]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('reports.monthly-board', [
                'month' => '2026-01',
                'type_filter' => 'resident',
                'show_account_type' => 0,
            ]))
            ->assertOk()
            ->assertSee('Ali Malik')
            ->assertSee('(Kat Maliki)')
            ->assertSee('Ayşe Kiracı')
            ->assertSee('(Kiracı)')
            ->assertDontSee('Hesap türünü göster')
            ->assertDontSee('name="show_account_type"', false);
    }

    public function test_monthly_board_limits_accounts_to_the_selected_residence_month_and_includes_deleted_accounts_when_requested(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Monthly report', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($apartment);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $create = fn (array $attributes) => Account::create(array_merge([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_TENANT,
            'is_active' => true,
        ], $attributes));
        $owner = $create(['name' => 'Existing owner', 'type' => Account::TYPE_OWNER]);
        $future = $create(['name' => 'June tenant', 'account_opening_date' => '2026-06-15']);
        $assignmentOnly = $create(['name' => 'Assignment tenant']);
        $assignmentOnly->tenantAssignments()->create([
            'apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'move_in_date' => '2026-06-01',
        ]);
        $former = $create(['name' => 'Former tenant', 'is_active' => false,
            'account_opening_date' => '2026-01-01', 'account_end_date' => '2026-02-10']);
        $former->delete();

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        foreach (['all', 'resident', 'tenant'] as $type) {
            $this->get(route('reports.monthly-board', [
                'month' => '2026-02', 'type_filter' => $type, 'status_filter' => 'all',
            ]))->assertOk()->assertDontSee('June tenant')->assertDontSee('Assignment tenant');
        }
        $this->get(route('reports.monthly-board', [
            'month' => '2026-02', 'type_filter' => 'all', 'status_filter' => 'inactive',
        ]))->assertOk()->assertSee('Former tenant')->assertDontSee('Existing owner');
        $this->get(route('reports.monthly-board', [
            'month' => '2026-06', 'type_filter' => 'all', 'status_filter' => 'all',
        ]))->assertOk()->assertSee('June tenant')->assertSee('Assignment tenant')->assertDontSee('Former tenant');
        $this->get(route('reports.monthly-board.export', [
            'type' => 'excel', 'month' => '2026-02', 'type_filter' => 'all', 'status_filter' => 'inactive',
        ]))->assertOk()->assertDownload();
    }

}
