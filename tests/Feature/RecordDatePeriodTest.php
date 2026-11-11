<?php

namespace Tests\Feature;

use App\Models\{Account, Apartment, Category, Due, Expense, Unit, User};
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecordDatePeriodTest extends TestCase
{
    use RefreshDatabase;

    private Apartment $apartment;
    private Account $owner;
    private Account $supplier;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-11 12:00:00'));
        $user = User::factory()->create();
        $this->apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Tarihten Ay', 'unit_count' => 1]);
        $this->apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($this->apartment);
        $unit = Unit::create(['apartment_id' => $this->apartment->id, 'unit_no' => '1']);
        $this->owner = Account::create(['apartment_id' => $this->apartment->id, 'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER, 'name' => 'Malik', 'is_active' => true]);
        $unit->update(['owner_account_id' => $this->owner->id, 'occupant_account_id' => $this->owner->id]);
        $this->supplier = Account::create(['apartment_id' => $this->apartment->id, 'type' => Account::TYPE_SUPPLIER, 'name' => 'Firma']);
        $this->category = Category::create(['apartment_id' => $this->apartment->id, 'name' => 'Bakım',
            'is_active' => true, 'allow_malik_payi' => true, 'allow_expense' => true]);
        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $this->apartment->id]);
    }

    public function test_effective_date_replaces_independent_month_storage(): void
    {
        $this->assertFalse(Schema::hasColumn('expenses', 'period_month'));
        $this->assertFalse(Schema::hasColumn('dues', 'period'));
        $this->assertFalse(Schema::hasColumn('due_batches', 'period'));
        $this->assertTrue(Schema::hasColumn('due_batches', 'assessment_date'));
        $this->get(route('expenses.create'))->assertOk()->assertDontSee('name="period_month"', false);
        $this->get(route('dues.create'))->assertOk()->assertDontSee('name="period"', false);
    }

    public function test_backdated_due_ignores_posted_period_and_due_date_month(): void
    {
        $payload = ['source_type' => 'individual', 'distribution_type' => 'individual',
            'account_id' => $this->owner->id, 'category_id' => $this->category->id, 'due_type' => 'malik_payi',
            'individual_amount' => 100, 'created_at_manual' => '2026-09-15', 'due_date' => '2026-10-31', 'period' => '2020-01'];
        $this->post(route('dues.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $due = Due::sole();
        $this->assertSame('2026-09', $due->period);
        $this->assertSame('2026-11-11', $due->created_at->toDateString());
        $this->assertSame('2026-09-15', $due->transactions()->sole()->transaction_date->toDateString());
        $this->assertSame(1, Due::forMonth('2026-09')->count());
        $this->assertSame(0, Due::forMonth('2026-10')->count());
        $this->get(route('reports.monthly-board', ['month' => '2026-09', 'due_type' => 'malik_payi']))->assertOk()
            ->assertViewHas('accountData', fn ($data) => $data[$this->owner->id]['selectedAmount'] === 100.0);
        $this->patch(route('dues.update', $due), ['amount' => 100, 'account_id' => $this->owner->id,
            'category_id' => $this->category->id, 'created_at_manual' => '2026-09-15', 'due_date' => '2026-11-30',
            'period' => '2026-11'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2026-09', $due->fresh()->period);
        $this->assertSame('2026-09-15', $due->transactions()->sole()->transaction_date->toDateString());
    }

    public function test_expense_reports_use_expense_date_and_due_date_does_not_change_month(): void
    {
        $payload = ['account_id' => $this->supplier->id, 'category_id' => $this->category->id, 'amount' => 200,
            'expense_date' => '2026-09-30', 'due_date' => '2026-11-01', 'period_month' => '2020-01'];
        $this->post(route('expenses.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $expense = Expense::sole();
        $this->assertSame('2026-09', $expense->period_month->format('Y-m'));
        $this->get(route('expenses.edit', $expense))->assertOk()->assertDontSee('name="period_month"', false)->assertDontSee('syncPeriodFromDueDate');
        foreach (['expenses', 'income-expense'] as $report) {
            $response = $this->get(route('reports.'.$report, ['month' => '2026-09']))->assertOk();
            $this->assertEquals(200, $response->viewData($report === 'expenses' ? 'total' : 'totalExpense'));
        }
        $payload['due_date'] = '2026-12-01';
        $this->put(route('expenses.update', $expense), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2026-09', $expense->fresh()->period_month->format('Y-m'));
    }

    public function test_migration_refuses_conflicting_periods_and_can_round_trip_without_changing_amounts(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $expense = Expense::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->supplier->id,
            'category_id' => $this->category->id, 'amount' => 200, 'remaining_amount' => 200, 'expense_date' => '2026-09-30']);
        $migration = require database_path('migrations/2026_11_11_000002_use_effective_dates_instead_of_record_periods.php');
        $migration->down();
        \Illuminate\Support\Facades\DB::table('expenses')->where('id', $expense->id)->update(['period_month' => '2026-08-01']);
        try {
            $migration->up();
            $this->fail('A conflicting period must stop the migration.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Tarih ile eski dönem', $exception->getMessage());
            $this->assertTrue(Schema::hasColumn('expenses', 'period_month'));
            $this->assertTrue(Schema::hasColumn('dues', 'period'));
        }
        \Illuminate\Support\Facades\DB::table('expenses')->where('id', $expense->id)->update(['period_month' => '2026-09-01']);
        $migration->up();
        $migration->up(); // A partially completed MySQL DDL run can be retried safely.
        $this->assertFalse(Schema::hasColumn('expenses', 'period_month'));
        $this->assertEquals(200, $expense->fresh()->amount);
        $this->assertEquals(200, $expense->fresh()->remaining_amount);
    }
}
