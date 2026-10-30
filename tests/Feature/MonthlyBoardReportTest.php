<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Due;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyBoardReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_mode_cards_and_period_rules_follow_the_current_month(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-29 12:00:00'));
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Dönem Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($apartment);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER, 'name' => 'Dönem Maliki', 'is_active' => true]);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);

        $current = $this->get(route('reports.monthly-board', [
            'view_mode' => 'current', 'month' => '2026-01',
        ]))->assertOk()
            ->assertSee('Güncel Borç Tablosu')
            ->assertSee('Bu ayın borçlarını, geçmişten kalan borçları ve bugün itibarıyla ödeme durumunu gösterir.')
            ->assertSee('Dönemsel Borç Tablosu')
            ->assertSee('Seçilen ayın devreden borçlarını, yeni borçlarını, tahsilatlarını ve dönem sonu durumunu gösterir.')
            ->assertSee('id="board-filter-form"', false)
            ->assertSee('Rapor hazırlanıyor…')
            ->assertSee('filterForm.requestSubmit();', false)
            ->assertSee('name="view_mode" value="current"', false)
            ->assertSee('name="view_mode" value="movements"', false);
        $this->assertSame('2026-10', $current->viewData('month'));
        $this->assertSame('Güncel Borç Tablosu', $current->viewData('viewLabel'));
        $current->assertSee('id="board-period-control" class="min-w-0 transition-opacity opacity-50"', false);

        $past = $this->get(route('reports.monthly-board', [
            'view_mode' => 'movements', 'month' => '2026-03',
        ]))->assertOk();
        $this->assertSame('2026-03', $past->viewData('month'));
        $this->assertSame('Dönemsel Borç Tablosu', $past->viewData('viewLabel'));
        $past->assertSee('id="board-period-control" class="min-w-0 transition-opacity "', false);

        foreach (['current', 'movements'] as $mode) {
            $normalized = $this->get(route('reports.monthly-board', [
                'view_mode' => $mode, 'month' => '2027-04',
            ]))->assertOk();
            $this->assertSame('2026-10', $normalized->viewData('month'));
        }

        $this->travelBack();
    }

    public function test_monthly_board_account_types_can_be_hidden_with_the_visibility_control(): void
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
                'view_mode' => 'movements',
                'hide_account_names' => 0,
                'hide_account_type' => 1,
            ]))
            ->assertOk()
            ->assertSee('Ali Malik')
            ->assertDontSee('(Kat Maliki)')
            ->assertSee('Ayşe Kiracı')
            ->assertDontSee('(Kiracı)')
            ->assertSee('Hesap türünü gizle')
            ->assertSee('name="hide_account_type"', false)
            ->assertSee('hide_account_type=1', false);

        $this->get(route('reports.monthly-board', ['month' => '2026-01', 'view_mode' => 'movements', 'hide_account_names' => 0, 'hide_account_type' => 0]))
            ->assertOk()
            ->assertSeeInOrder(['1', '(Kat Maliki)', 'Ali Malik'])
            ->assertSeeInOrder(['2', '(Kiracı)', 'Ayşe Kiracı'])
            ->assertSee('(Kat Maliki)')
            ->assertSee('(Kiracı)')
            ->assertSee('AİDAT BORÇ TABLOSU - OCAK 2026 - Akbey Apartmanı')
            ->assertDontSee('Geçmiş borçlar ve seçilen ayın ödeme durumu bugün itibarıyla gösterilir.');

        $hidden = $this->get(route('reports.monthly-board', ['month' => '2026-01', 'view_mode' => 'movements']))
            ->assertOk()->assertDontSee('>Hesap Adı</th>', false)->assertDontSee('Ali Malik')->assertDontSee('Ayşe Kiracı');
        $this->assertCount(2, $hidden->viewData('accounts'));
    }

    public function test_monthly_board_uses_residence_dates_and_keeps_deleted_accounts_only_for_relevant_debts(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Monthly report', 'unit_count' => 5]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($apartment);
        $unitNumber = 0;
        $create = function (array $attributes) use ($apartment, &$unitNumber): Account {
            $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => (string) ++$unitNumber]);
            return Account::create(array_merge([
                'apartment_id' => $apartment->id,
                'unit_id' => $unit->id,
                'type' => Account::TYPE_TENANT,
                'is_active' => true,
            ], $attributes));
        };
        $owner = $create(['name' => 'Existing owner', 'type' => Account::TYPE_OWNER]);
        $future = $create(['name' => 'June tenant', 'account_opening_date' => '2026-06-15']);
        $assignmentOnly = $create(['name' => 'Assignment tenant']);
        $assignmentOnly->tenantAssignments()->create([
            'apartment_id' => $apartment->id, 'unit_id' => $assignmentOnly->unit_id, 'move_in_date' => '2026-06-01',
        ]);
        $former = $create(['name' => 'Former tenant', 'is_active' => false,
            'account_opening_date' => '2026-01-01', 'account_end_date' => '2026-02-10']);
        $former->delete();
        $debtor = $create(['name' => 'Deleted debtor', 'is_active' => false,
            'account_opening_date' => '2026-01-01', 'account_end_date' => '2026-02-10']);
        foreach (['2026-01' => 50, '2026-02' => 100] as $period => $amount) {
            Due::create(['apartment_id' => $apartment->id, 'account_id' => $debtor->id, 'unit_id' => $debtor->unit_id,
                'due_type' => 'aidat', 'period' => $period, 'due_date' => $period.'-01',
                'amount' => $amount, 'remaining_amount' => $amount, 'status' => 'unpaid']);
        }
        $debtor->delete();

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $response = $this->get(route('reports.monthly-board', [
            'month' => '2026-02', 'due_type' => 'aidat', 'view_mode' => 'movements', 'hide_account_names' => 0,
        ]))->assertOk()->assertSee('Existing owner')->assertSee('Deleted debtor')
            ->assertDontSee('June tenant')->assertDontSee('Assignment tenant')->assertDontSee('Former tenant');
        $this->assertEqualsCanonicalizing([$owner->id, $debtor->id], $response->viewData('accounts')->pluck('id')->all());
        $this->assertEquals(['pastRemaining' => 50, 'selectedAmount' => 100, 'paid' => 0, 'remaining' => 150],
            $response->viewData('accountData')[$debtor->id]);
        $this->get(route('reports.monthly-board', [
            'month' => '2026-06', 'due_type' => 'aidat', 'view_mode' => 'movements', 'hide_account_names' => 0,
        ]))->assertOk()->assertSee('June tenant')->assertSee('Assignment tenant')->assertSee('Deleted debtor')->assertDontSee('Former tenant');
        $this->get(route('reports.monthly-board.export', [
            'type' => 'excel', 'month' => '2026-02', 'due_type' => 'aidat', 'view_mode' => 'movements', 'hide_account_names' => 0,
        ]))->assertOk()->assertDownload();
    }

}
