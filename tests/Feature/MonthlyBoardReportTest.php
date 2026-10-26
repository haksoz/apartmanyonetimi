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
}
