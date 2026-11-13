<?php

namespace Tests\Feature;

use App\Enums\ExpenseRelation;
use App\Models\{Account, Apartment, Category, Due, Expense, Payment, Unit, User};
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ExpenseRelationTest extends TestCase
{
    use RefreshDatabase;

    private Apartment $apartment;
    private Account $owner;
    private Account $supplier;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->travelTo(\Carbon\Carbon::parse('2026-11-11 12:00:00'));
        $user = User::factory()->create();
        $this->apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Gider İlişkisi', 'unit_count' => 1]);
        $this->apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($this->apartment);
        $unit = Unit::create(['apartment_id' => $this->apartment->id, 'unit_no' => '1']);
        $this->owner = Account::create(['apartment_id' => $this->apartment->id, 'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER, 'name' => 'Malik']);
        $this->supplier = Account::create(['apartment_id' => $this->apartment->id, 'type' => Account::TYPE_SUPPLIER, 'name' => 'Firma']);
        $this->category = Category::create(['apartment_id' => $this->apartment->id, 'name' => 'Ortak kategori',
            'is_active' => true, 'allow_expense' => true, 'allow_aidat' => true, 'allow_malik_payi' => true]);
        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $this->apartment->id]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['account_id' => $this->supplier->id, 'category_id' => $this->category->id,
            'amount' => 600, 'description' => 'İlişkili gider', 'expense_date' => '2026-09-15', 'period_month' => '2026-09'], $overrides);
    }

    public function test_expense_status_filter_accepts_multiple_statuses_and_legacy_single_values(): void
    {
        $ids = [];
        foreach (['paid', 'overdue', 'pending'] as $status) {
            $this->post(route('expenses.store'), $this->payload())->assertSessionHasNoErrors();
            $expense = Expense::latest('id')->firstOrFail();
            $expense->update(['is_paid' => $status === 'paid',
                'due_date' => $status === 'overdue' ? now()->subDay() : null]);
            $ids[$status] = $expense->id;
        }

        $this->get(route('expenses.index', ['status' => ['paid', 'pending']]))->assertOk()
            ->assertViewHas('expenses', fn ($expenses) => $expenses->pluck('id')->sort()->values()->all()
                === [$ids['paid'], $ids['pending']])
            ->assertSee('expense-status-dialog', false);
        $this->get(route('expenses.index', ['status' => 'overdue']))->assertOk()
            ->assertViewHas('expenses', fn ($expenses) => $expenses->pluck('id')->all() === [$ids['overdue']]);
        $this->get(route('expenses.index'))->assertOk()
            ->assertViewHas('expenses', fn ($expenses) => $expenses->total() === 3);
    }

    #[TestWith([0, 180, false])]
    #[TestWith([100, 80, false])]
    #[TestWith([180, 0, true])]
    public function test_amount_edit_recalculates_balance_without_changing_payments(int $paid, int $remaining, bool $isPaid): void
    {
        $this->post(route('expenses.store'), $this->payload(['amount' => 300]))->assertSessionHasNoErrors();
        $expense = Expense::latest('id')->firstOrFail();
        $expense->update(['paid_amount' => $paid, 'remaining_amount' => 300 - $paid]);

        $this->put(route('expenses.update', $expense), $this->payload(['amount' => 180]))
            ->assertSessionHasNoErrors()->assertRedirect(route('expenses.show', $expense));

        $expense->refresh();
        $this->assertEquals(180, $expense->amount);
        $this->assertEquals($paid, $expense->paid_amount);
        $this->assertEquals($remaining, $expense->remaining_amount);
        $this->assertSame($isPaid, $expense->is_paid);
        $this->assertEquals(180, $expense->transactions()->where('type', 'credit')->sole()->amount);
        $this->get(route('expenses.show', $expense))->assertOk()
            ->assertSeeInOrder(['Tutar', '180,00 TL', 'Ödenen', number_format($paid, 2, ',', '.').' TL',
                'Kalan', number_format($remaining, 2, ',', '.').' TL']);
    }

    public function test_amount_cannot_be_reduced_below_existing_payments(): void
    {
        $this->post(route('expenses.store'), $this->payload(['amount' => 300]))->assertSessionHasNoErrors();
        $expense = Expense::latest('id')->firstOrFail();
        $expense->update(['paid_amount' => 200, 'remaining_amount' => 100]);

        $this->put(route('expenses.update', $expense), $this->payload(['amount' => 180]))
            ->assertSessionHasErrors('amount');

        $this->assertEquals(300, $expense->fresh()->amount);
        $this->assertEquals(300, $expense->transactions()->where('type', 'credit')->sole()->amount);
    }

    public function test_category_default_is_used_for_new_expenses_and_explicit_selection_wins(): void
    {
        $this->category->update(['expense_relations' => ['aidat']]);
        $this->get(route('expenses.create'))->assertOk()->assertSee('data-expense-relation="aidat"', false);
        $this->post(route('expenses.store'), $this->payload())->assertSessionHasNoErrors();
        $expense = Expense::latest('id')->firstOrFail();
        $this->assertSame(ExpenseRelation::Aidat, $expense->expense_relation);
        $this->post(route('expenses.store'), $this->payload(['expense_relation' => 'malik_payi']))->assertSessionHasNoErrors();
        $this->assertSame(ExpenseRelation::MalikPayi, Expense::latest('id')->firstOrFail()->expense_relation);
        $this->category->update(['expense_relations' => []]);
        $this->assertSame(ExpenseRelation::Aidat, $expense->fresh()->expense_relation);
        $this->get(route('expenses.edit', $expense))->assertOk()->assertSee('const preserveInitial = true;', false);
    }

    public function test_category_with_both_relations_does_not_arbitrarily_choose_for_expense(): void
    {
        $this->category->update(['expense_relations' => ['aidat', 'malik_payi']]);
        $this->post(route('expenses.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame(ExpenseRelation::Unassigned, Expense::latest('id')->firstOrFail()->expense_relation);
        foreach (['aidat', 'malik_payi'] as $value) {
            $this->post(route('expenses.store'), $this->payload(['expense_relation' => $value]))->assertSessionHasNoErrors();
            $this->assertSame($value, Expense::latest('id')->firstOrFail()->expense_relation->value);
        }
    }

    #[TestWith(['unassigned'])]
    #[TestWith(['aidat'])]
    #[TestWith(['malik_payi'])]
    public function test_creation_stores_relation_without_creating_dues(string $relation): void
    {
        $this->get(route('expenses.create'))->assertOk()
            ->assertSeeInOrder(['for="amount"', 'for="description"', 'id="expense_relation"'], false)
            ->assertSee('Ayrıntılı rapor alabilmek için gider ilişkisini oluşturun.')
            ->assertSee('İşletme Gideri')
            ->assertSee('Apartmanın günlük işleyişi, kullanımı ve düzenli giderleri.')
            ->assertSee('Kat Maliki Gideri')
            ->assertSee('Kat maliklerinin karşılaması öngörülen giderler.')
            ->assertSee('type="radio" name="expense_relation"', false)
            ->assertDontSee('<select id="expense_relation"', false);
        $this->post(route('expenses.store'), $this->payload(['expense_relation' => $relation]))->assertRedirect();
        $expense = Expense::sole();
        $this->assertSame($relation, $expense->expense_relation->value);
        $this->assertEquals(600, $expense->remaining_amount);
        $this->assertDatabaseCount('dues', 0);
        $this->get(route('expenses.show', $expense))->assertOk()->assertSee(ExpenseRelation::from($relation)->label());
    }

    public function test_unspecified_defaults_are_independent_of_category_and_invalid_values_are_rejected(): void
    {
        $this->post(route('expenses.store'), $this->payload())->assertRedirect();
        $expense = Expense::sole();
        $this->assertSame(ExpenseRelation::Unassigned, $expense->expense_relation);
        $this->postJson(route('expenses.store'), $this->payload(['expense_relation' => 'other']))->assertUnprocessable();
        $this->putJson(route('expenses.update', $expense), $this->payload(['expense_relation' => 'other']))->assertUnprocessable();
        $this->getJson(route('reports.expenses', ['expense_relation' => 'other']))->assertUnprocessable();
        $this->getJson(route('reports.income-expense', ['expense_relation' => 'other']))->assertUnprocessable();
    }

    public function test_relation_can_be_set_later_on_paid_expenses_without_changing_payments_or_balances(): void
    {
        $expense = Expense::create($this->payload(['apartment_id' => $this->apartment->id,
            'period_month' => '2026-09-01', 'paid_amount' => 600, 'remaining_amount' => 0, 'is_paid' => true]));
        $payment = Payment::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->supplier->id,
            'amount' => 600, 'unallocated_amount' => 0, 'payment_date' => '2026-09-15']);
        $payment->allocations()->create(['expense_id' => $expense->id, 'amount' => 600]);
        $snapshot = fn () => collect(['dues', 'payments', 'payment_allocations', 'cash_transactions'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $this->get(route('expenses.edit', $expense))->assertOk()->assertSee('Gider İlişkisi');
        foreach (['aidat', 'malik_payi', 'unassigned'] as $relation) {
            $this->put(route('expenses.update', $expense), $this->payload(['expense_relation' => $relation, 'is_paid' => 1]))->assertRedirect(route('expenses.show', $expense));
            $expense->refresh();
            $this->assertSame($relation, $expense->expense_relation->value);
            $this->assertEquals(600, $expense->amount);
            $this->assertEquals(600, $expense->paid_amount);
            $this->assertEquals(0, $expense->remaining_amount);
            $this->assertTrue($expense->is_paid);
        }
        $this->assertSame($before, $snapshot());
        // Older clients without this field must not reset a saved relation.
        $this->put(route('expenses.update', $expense), $this->payload(['expense_relation' => 'aidat', 'is_paid' => 1]))->assertRedirect();
        $this->put(route('expenses.update', $expense), $this->payload(['is_paid' => 1]))->assertRedirect();
        $this->assertSame(ExpenseRelation::Aidat, $expense->fresh()->expense_relation);
    }

    private function reportFixtures(): void
    {
        foreach (['2025-12-31' => [500, 100, 200], '2026-09-30' => [1000, 400, 300]] as $date => [$amount, $aidat, $malik]) {
            $payment = Payment::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->owner->id,
                'amount' => $amount, 'unallocated_amount' => $amount, 'payment_date' => $date]);
            foreach (['aidat' => $aidat, 'malik_payi' => $malik] as $type => $allocated) {
                $due = Due::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->owner->id,
                    'unit_id' => $this->owner->unit_id, 'due_type' => $type, 'category_id' => $this->category->id,
                    'period' => substr($date, 0, 7), 'due_date' => $date, 'amount' => $allocated, 'remaining_amount' => $allocated, 'status' => 'unpaid']);
                $payment->allocateToDue($due, $allocated);
            }
        }
        foreach (['aidat' => [120, 300], 'malik_payi' => [50, 200], 'unassigned' => [30, 100]] as $relation => [$past, $current]) {
            foreach (['2025-12-31' => $past, '2026-09-30' => $current] as $date => $amount) {
                Expense::create($this->payload(['apartment_id' => $this->apartment->id, 'expense_relation' => $relation,
                    'amount' => $amount, 'remaining_amount' => $amount, 'expense_date' => $date, 'period_month' => substr($date, 0, 7).'-01']));
            }
        }
    }

    #[TestWith(['all', 1000, 600, 300])]
    #[TestWith(['aidat', 400, 300, -20])]
    #[TestWith(['malik_payi', 300, 200, 150])]
    #[TestWith(['unassigned', 300, 100, 170])]
    public function test_income_expense_filters_split_payments_and_history_consistently(string $relation, int $income, int $expense, int $opening): void
    {
        $this->reportFixtures();
        $screen = $this->get(route('reports.income-expense', ['month' => '2026-09', 'expense_relation' => $relation]))->assertOk()
            ->assertSee('expense_relation='.$relation, false);
        $this->assertEquals($income, $screen->viewData('totalIncome'));
        $this->assertEquals($expense, $screen->viewData('totalExpense'));
        $this->assertEquals($opening, $screen->viewData('openingBalance'));
        $this->assertEquals($opening + $income - $expense, $screen->viewData('closingBalance'));
        $this->assertEquals($opening, $screen->viewData('historyRows')->sum('net'));
        $pdf = view('reports.income-expense-pdf', $screen->original->getData())->render();
        $this->assertStringContainsString($screen->viewData('expenseRelationLabel'), $pdf);
    }

    public function test_expense_reports_and_exports_keep_relation_and_basis(): void
    {
        $this->reportFixtures();
        foreach (['description', 'category'] as $basis) {
            foreach (['all' => 600, 'aidat' => 300, 'malik_payi' => 200, 'unassigned' => 100] as $relation => $expected) {
                $filters = ['month' => '2026-09', 'expense_relation' => $relation, 'basis' => $basis];
                $screen = $this->get(route('reports.expenses', $filters))->assertOk()->assertSee('expense_relation='.$relation, false);
                $this->assertEquals($expected, $screen->viewData('total'));
                $pdf = view('reports.expenses-pdf', $screen->original->getData())->render();
                $this->assertStringContainsString($screen->viewData('expenseRelationLabel'), $pdf);
            }
        }
        foreach (['expenses', 'income-expense'] as $report) {
            $response = $this->get(route('reports.'.$report.'.export', ['type' => 'excel', 'month' => '2026-09', 'expense_relation' => 'aidat']))->assertOk();
            ob_start();
            try { ($response->baseResponse->getCallback())(); $bytes = ob_get_contents(); }
            finally { ob_end_clean(); }
            $path = tempnam(sys_get_temp_dir(), 'relation-export-');
            try {
                file_put_contents($path, $bytes);
                $book = IOFactory::load($path);
                $sheet = $book->getActiveSheet();
                $this->assertSame('Gider İlişkisi: İşletme Gideri', $sheet->getCell('A3')->getValue());
                if ($report === 'income-expense') {
                    $this->assertEquals(300, $sheet->getCell('B5')->getValue());
                    $this->assertEquals(400, $sheet->getCell('C5')->getValue());
                } else {
                    $this->assertEquals(300, $sheet->getCell('C5')->getValue());
                }
                $book->disconnectWorksheets();
            } finally { unlink($path); }
        }
    }

    public function test_filtered_reports_do_not_include_other_apartments_or_change_financial_records(): void
    {
        $this->reportFixtures();
        $other = Apartment::create(['user_id' => $this->apartment->user_id, 'name' => 'Başka Apartman', 'unit_count' => 1]);
        Expense::create($this->payload(['apartment_id' => $other->id, 'expense_relation' => 'aidat',
            'amount' => 9000, 'remaining_amount' => 9000, 'period_month' => '2026-09-01']));
        Payment::create(['apartment_id' => $other->id, 'account_id' => $this->owner->id,
            'amount' => 9000, 'unallocated_amount' => 9000, 'payment_date' => '2026-09-15']);
        $deleted = Expense::create($this->payload(['apartment_id' => $this->apartment->id,
            'expense_relation' => 'aidat', 'amount' => 8000, 'period_month' => '2026-09-01']));
        $deleted->delete();
        $snapshot = fn () => collect(['expenses', 'dues', 'payments', 'payment_allocations', 'account_transactions', 'cash_transactions'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $closing = 0;
        foreach (['aidat', 'malik_payi', 'unassigned'] as $relation) {
            $response = $this->get(route('reports.income-expense', ['month' => '2026-09', 'expense_relation' => $relation]))->assertOk();
            $closing += $response->viewData('closingBalance');
        }
        $all = $this->get(route('reports.income-expense', ['month' => '2026-09']))->assertOk();
        $this->assertEquals(700, $all->viewData('closingBalance'));
        $this->assertEquals($closing, $all->viewData('closingBalance'));
        $report = $this->get(route('reports.expenses', ['month' => '2026-09', 'expense_relation' => 'aidat']))->assertOk();
        $this->assertEquals(300, $report->viewData('total'));
        $this->assertSame($before, $snapshot());
    }
}
