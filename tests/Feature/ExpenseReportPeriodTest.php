<?php

namespace Tests\Feature;

use App\Models\{Apartment, Expense, User};
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExpenseReportPeriodTest extends TestCase
{
    use RefreshDatabase;

    private Apartment $apartment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->travelTo(\Carbon\Carbon::parse('2026-11-10 12:00:00'));
        $user = User::factory()->create();
        $this->apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Gider Dönemi', 'unit_count' => 1]);
        $this->apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($this->apartment);
        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $this->apartment->id]);
        foreach (['2026-08' => 800, '2026-09' => 900, '2026-10' => 1000, '2026-11' => 1100] as $period => $amount) {
            Expense::create(['apartment_id' => $this->apartment->id, 'description' => $period.' gideri',
                'category' => 'Bakım', 'amount' => $amount, 'remaining_amount' => $amount,
                'period_month' => $period.'-01', 'expense_date' => '2026-11-05']);
        }
    }

    public function test_default_single_month_and_range_use_the_shared_picker_and_expense_period(): void
    {
        $default = $this->get(route('reports.expenses'))->assertOk()->assertSee('id="board-period-dialog"', false)
            ->assertSee('data-[selection=complete]:bg-emerald-50', false)->assertDontSee('Özel tarih aralığı')->assertDontSee('type="date"', false);
        $this->assertEquals(1100, $default->viewData('total'));
        $this->assertEquals(900, $this->get(route('reports.expenses', ['month' => '2026-09']))->assertOk()->viewData('total'));
        $range = $this->get(route('reports.expenses', ['period_year' => 2026, 'period_month' => 9, 'month_end' => '2026-10']))->assertOk();
        $this->assertEquals(1900, $range->viewData('total'));
        $this->assertSame('2026-09-01', $range->viewData('dateFrom'));
        $this->assertSame('2026-10-31', $range->viewData('dateTo'));
        $range->assertSee('Eylül 2026 – Ekim 2026')->assertSee('month_end=2026-10', false);
        $this->assertEquals(1900, $this->get(route('reports.expenses', ['month' => '2026-09', 'month_end' => '2026-10', 'basis' => 'category']))->assertOk()->viewData('total'));
    }

    public function test_removed_date_parameters_cannot_override_the_selected_period(): void
    {
        $screen = $this->get(route('reports.expenses', ['range_mode' => 'date', 'month' => '2026-09',
            'date_from' => '2026-11-05', 'date_to' => '2026-11-05']))->assertOk();
        $this->assertSame('2026-09', $screen->viewData('month'));
        $this->assertEquals(900, $screen->viewData('total'));
        $screen->assertDontSee('range_mode=date', false)->assertDontSee('date_from=', false);
    }

    public function test_range_boundaries_validation_and_other_apartment_isolation(): void
    {
        $this->getJson(route('reports.expenses', ['month' => '2026-10', 'month_end' => '2026-09']))->assertUnprocessable();
        $this->getJson(route('reports.expenses', ['month_end' => '2026-13']))->assertUnprocessable();
        $this->getJson(route('reports.expenses', ['period_year' => 2026]))->assertUnprocessable();
        $future = $this->get(route('reports.expenses', ['month' => '2026-09', 'month_end' => '2027-01']))->assertOk();
        $this->assertSame('2026-11', $future->viewData('monthEnd'));
        $crossYear = $this->get(route('reports.expenses', ['month' => '2025-12', 'month_end' => '2026-02']))->assertOk();
        $this->assertSame('2026-02-28', $crossYear->viewData('dateTo'));
        $other = Apartment::create(['user_id' => $this->apartment->user_id, 'name' => 'Other', 'unit_count' => 1]);
        Expense::create(['apartment_id' => $other->id, 'description' => 'Yabancı gider', 'amount' => 9999,
            'expense_date' => '2026-09-01', 'period_month' => '2026-09-01']);
        $this->get(route('reports.expenses', ['month' => '2026-09']))->assertOk()->assertDontSee('Yabancı gider');
    }

    public function test_pdf_template_and_excel_keep_the_selected_month_range(): void
    {
        $filters = ['month' => '2026-09', 'month_end' => '2026-10', 'basis' => 'description'];
        $screen = $this->get(route('reports.expenses', $filters))->assertOk();
        $pdf = view('reports.expenses-pdf', $screen->original->getData())->render();
        $this->assertStringContainsString('01.09.2026', $pdf);
        $this->assertStringContainsString('31.10.2026', $pdf);
        $this->assertStringContainsString('1.900,00', $pdf);
        $excel = $this->get(route('reports.expenses.export', ['type' => 'excel'] + $filters))->assertOk();
        ob_start();
        try { ($excel->baseResponse->getCallback())(); $bytes = ob_get_contents(); }
        finally { ob_end_clean(); }
        $path = tempnam(sys_get_temp_dir(), 'expense-period-');
        try {
            file_put_contents($path, $bytes);
            $book = IOFactory::load($path);
            $sheet = $book->getActiveSheet();
            $this->assertSame($screen->viewData('rangeLabel'), $sheet->getCell('A2')->getValue());
            $this->assertEquals(1900, $sheet->getCell('C'.$sheet->getHighestRow())->getValue());
            $book->disconnectWorksheets();
        } finally { unlink($path); }
    }

    public function test_income_expense_uses_the_same_picker_without_category_breakdown(): void
    {
        $account = \App\Models\Account::create(['apartment_id' => $this->apartment->id,
            'type' => \App\Models\Account::TYPE_OWNER, 'name' => 'Malik']);
        foreach (['2026-08-01' => 50, '2026-09-30' => 100, '2026-10-31' => 200] as $date => $amount) {
            \App\Models\Payment::create(['apartment_id' => $this->apartment->id, 'account_id' => $account->id,
                'amount' => $amount, 'unallocated_amount' => $amount, 'payment_date' => $date]);
        }
        $filters = ['period_year' => 2026, 'period_month' => 9, 'month_end' => '2026-10', 'show_categories' => 1];
        $screen = $this->get(route('reports.income-expense', $filters))->assertOk()
            ->assertSee('id="board-period-dialog"', false)->assertSee('Eylül 2026 – Ekim 2026')
            ->assertSee('Görüntüle')->assertSee('month_end=2026-10', false)
            ->assertDontSee('type="date"', false)->assertDontSee('show_categories', false)->assertDontSee('Gider Kategorileri');
        $this->assertEquals(300, $screen->viewData('totalIncome'));
        $this->assertEquals(0, $screen->viewData('totalExpense'));
        $this->assertEquals(50, $screen->viewData('openingBalance'));
        $this->assertEquals(350, $screen->viewData('closingBalance'));
        $this->assertSame(['Eylül 2026', 'Ekim 2026'], $screen->viewData('rows')->pluck('month')->all());
        $pdf = view('reports.income-expense-pdf', $screen->original->getData())->render();
        $this->assertStringContainsString('31.10.2026', $pdf);
        $this->assertStringNotContainsString('Gider Kategorileri', $pdf);
        $default = $this->get(route('reports.income-expense'))->assertOk();
        $this->assertSame('2026-01', $default->viewData('month'));
        $this->assertSame('2026-11', $default->viewData('monthEnd'));
        $this->getJson(route('reports.income-expense', ['month' => '2026-10', 'month_end' => '2026-09']))->assertUnprocessable();
    }
}
