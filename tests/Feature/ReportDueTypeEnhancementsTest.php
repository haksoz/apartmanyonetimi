<?php

namespace Tests\Feature;

use App\Models\{Account, Apartment, Due, Expense, Payment, Unit, User};
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportDueTypeEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private Apartment $apartment;
    private Account $resident;
    private Account $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Rapor Apartmanı', 'unit_count' => 1]);
        $this->apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($this->apartment);
        $unit = Unit::create(['apartment_id' => $this->apartment->id, 'unit_no' => '1']);
        $this->resident = Account::create(['apartment_id' => $this->apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_OWNER, 'name' => 'Ali Malik', 'is_active' => true]);
        $this->supplier = Account::create(['apartment_id' => $this->apartment->id, 'type' => Account::TYPE_SUPPLIER, 'name' => 'Tedarikçi', 'is_active' => true]);
        $unit->update(['owner_account_id' => $this->resident->id, 'occupant_account_id' => $this->resident->id]);
        $this->withSession([CurrentApartment::SESSION_KEY => $this->apartment->id])->actingAs($user);
    }

    private function due(string $type, float $amount, float $remaining, string $period = '2026-01'): Due
    {
        return Due::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->resident->id,
            'unit_id' => $this->resident->unit_id, 'due_type' => $type, 'period' => $period,
            'due_date' => $period.'-10', 'amount' => $amount, 'remaining_amount' => $remaining,
            'status' => $remaining <= 0 ? 'paid' : ($remaining < $amount ? 'partial' : 'unpaid')]);
    }

    public function test_collection_report_filters_due_type_and_combines_same_month_amounts(): void
    {
        $this->due('aidat', 100, 0);
        $this->due('aidat', 100, 100);
        $this->due('malik_payi', 300, 300);

        $aidat = $this->get(route('reports.due-collection', ['year' => 2026, 'due_type' => 'aidat']))->assertOk();
        $this->assertSame('partial', $aidat->viewData('matrix')[$this->resident->id][1]);
        $this->assertSame('Aidat Tahsilat Raporu', $aidat->viewData('reportTitle'));

        $malik = $this->get(route('reports.due-collection', ['year' => 2026, 'due_type' => 'malik_payi']))->assertOk();
        $this->assertSame('overdue', $malik->viewData('matrix')[$this->resident->id][1]);
        $this->assertSame('Kat Maliki Payı Tahsilat Raporu', $malik->viewData('reportTitle'));
    }

    public function test_overdue_and_annual_reports_filter_by_due_type(): void
    {
        $this->due('aidat', 100, 100);
        $this->due('malik_payi', 300, 300);
        Due::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->resident->id,
            'unit_id' => $this->resident->unit_id, 'due_type' => 'malik_payi', 'period' => null,
            'due_date' => '2026-02-10', 'amount' => 50, 'remaining_amount' => 50, 'status' => 'unpaid']);

        $overdue = $this->get(route('reports.overdue', ['due_type' => 'aidat']))->assertOk();
        $this->assertSame(100.0, (float) $overdue->viewData('totalOverdue'));
        $this->assertCount(1, $overdue->viewData('dues'));

        $annual = $this->get(route('reports.annual-activity', ['year' => 2026, 'due_type' => 'malik_payi']))->assertOk();
        $this->assertSame(350.0, $annual->viewData('totalDues'));
        $this->assertSame('Kat Maliki Payı', $annual->viewData('dueTypeLabel'));
        $this->assertSame(300.0, $annual->viewData('monthlyData')[1]['dues']);
        $this->assertSame(50.0, $annual->viewData('monthlyData')[2]['dues']);
        $this->assertSame(0.0, $annual->viewData('monthlyData')[10]['dues']);
    }

    public function test_income_expense_includes_opening_balance_breakdown_and_expense_first(): void
    {
        Payment::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->resident->id,
            'amount' => 500, 'unallocated_amount' => 500, 'payment_date' => '2025-12-20']);
        Expense::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->supplier->id,
            'category' => 'Eski', 'description' => 'Önceki gider', 'amount' => 100, 'expense_date' => '2025-12-21']);

        $aidat = $this->due('aidat', 100, 100, '2026-01');
        $malik = $this->due('malik_payi', 200, 200, '2026-01');
        $payment = Payment::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->resident->id,
            'amount' => 350, 'unallocated_amount' => 350, 'payment_date' => '2026-01-20']);
        $payment->allocateToDue($aidat, 100);
        $payment->allocateToDue($malik, 200);
        Expense::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->supplier->id,
            'category' => 'Cari', 'description' => 'Dönem gideri', 'amount' => 70, 'expense_date' => '2026-01-22']);

        $response = $this->get(route('reports.income-expense', ['date_from' => '2026-01-01', 'date_to' => '2026-01-31']))
            ->assertOk()->assertSeeInOrder(['>Gider</th>', '>Tahsilat</th>'], false);
        $this->assertSame(400.0, $response->viewData('openingBalance'));
        $this->assertSame(680.0, $response->viewData('closingBalance'));
        $this->assertSame(100.0, $response->viewData('aidatIncome'));
        $this->assertSame(200.0, $response->viewData('ownerShareIncome'));
        $this->assertSame(50.0, $response->viewData('otherIncome'));
    }

    public function test_reports_index_contains_one_general_debt_list_card(): void
    {
        $html = $this->get(route('reports.index'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'href="'.route('reports.debt-list').'"'));
        $this->assertStringContainsString('Genel Borç Listesi', $html);
    }

    public function test_updated_report_excel_exports_accept_the_same_filters(): void
    {
        $this->due('aidat', 100, 100);
        $this->due('malik_payi', 200, 200);

        $this->get(route('reports.due-collection.export', ['type' => 'excel', 'year' => 2026, 'due_type' => 'malik_payi']))
            ->assertOk()->assertDownload();
        $this->get(route('reports.overdue.export', ['type' => 'excel', 'due_type' => 'aidat']))
            ->assertOk()->assertDownload();
        $this->get(route('reports.annual-activity.export', ['type' => 'excel', 'year' => 2026, 'due_type' => 'malik_payi']))
            ->assertOk()->assertDownload();
        $this->get(route('reports.income-expense.export', ['type' => 'excel', 'date_from' => '2026-01-01', 'date_to' => '2026-01-31']))
            ->assertOk()->assertDownload();
    }
}
