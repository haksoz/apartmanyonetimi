<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportController;
use App\Models\{Account, Apartment, Due, Payment, Unit, User};
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

class MonthlyBoardAudienceTest extends TestCase
{
    use RefreshDatabase;

    private Apartment $apartment;
    private Account $owner;
    private Account $tenant;
    private Account $secondTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $user = User::factory()->create();
        $this->apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Board fixture', 'unit_count' => 1]);
        $this->apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($this->apartment);
        $unit = Unit::create(['apartment_id' => $this->apartment->id, 'unit_no' => '1']);
        foreach (['owner' => 'owner', 'tenant' => 'tenant', 'secondTenant' => 'tenant'] as $property => $type) {
            $this->{$property} = Account::create(['apartment_id' => $this->apartment->id, 'unit_id' => $unit->id,
                'type' => $type, 'name' => $property, 'is_active' => true]);
        }
        $unit->update(['owner_account_id' => $this->owner->id, 'occupant_account_id' => $this->tenant->id]);
        $this->withSession([CurrentApartment::SESSION_KEY => $this->apartment->id])->actingAs($user);

        $this->due($this->owner, 'tenant_priority', '2026-06', 100, 40);
        $this->due($this->owner, 'tenant_priority', '2026-05', 50, 10, true);
        $this->due($this->tenant, 'tenant_priority', '2026-06', 80, 20);
        $this->due($this->secondTenant, 'tenant_priority', '2026-06', 30);
        $this->due($this->owner, 'owner_only', '2026-06', 200, 50);
        $this->due($this->owner, 'owner_only', '2026-04', 300, 100);
        $this->due($this->tenant, 'owner_only', '2026-06', 70);
        $this->due($this->owner, null, '2026-06', 9000);
        $this->due($this->owner, null, '2026-05', 8000);
        $this->due($this->owner, null, '2026-07', 7000);
        foreach (['tenant_priority', 'owner_only'] as $rule) {
            $this->due($this->owner, $rule, '2026-07', 10000);
            $this->due($this->owner, $rule, '2026-06', 20000)->delete();
        }
    }

    private function due(Account $account, ?string $rule, string $month, float $amount, float $paid = 0, bool $withoutPeriod = false): Due
    {
        $due = Due::create(['apartment_id' => $account->apartment_id, 'account_id' => $account->id,
            'unit_id' => $account->unit_id, 'target_audience' => $rule,
            // Deliberately opposite: report scope must use the recorded rule, not debt type.
            'due_type' => $rule === 'owner_only' ? 'aidat' : 'malik_payi',
            'period' => $withoutPeriod ? null : $month, 'due_date' => $month.'-10',
            'amount' => $amount, 'remaining_amount' => $amount, 'status' => 'unpaid']);
        if ($paid > 0) {
            $payment = Payment::create(['apartment_id' => $account->apartment_id, 'account_id' => $account->id,
                'amount' => $paid, 'unallocated_amount' => $paid, 'payment_date' => '2026-10-01']);
            $payment->allocateToDue($due, $paid);
        }
        return $due;
    }

    private function screen(array $filters = [])
    {
        return $this->get(route('reports.monthly-board', array_merge(['month' => '2026-06'], $filters)))->assertOk();
    }

    #[TestWith(['tenant_priority', 40, 210, 60, 190, 3])]
    #[TestWith(['owner_only', 200, 270, 50, 420, 2])]
    public function test_scope_preserves_all_relevant_accounts_and_current_balances(string $rule, float $past, float $amount, float $paid, float $remaining, int $accountCount): void
    {
        $response = $this->screen(['target_audience' => $rule]);
        $data = collect($response->viewData('accountData'));
        $this->assertCount($accountCount, $response->viewData('accounts'));
        $this->assertEquals($past, $data->sum('pastRemaining'));
        $this->assertEquals($amount, $data->sum('selectedAmount'));
        $this->assertEquals($paid, $data->sum('paid'));
        $this->assertEquals($remaining, $data->sum('remaining'));
        $this->assertSame(2, $response->viewData('unknownDuesCount'));
        $response->assertSee('oluşturma kuralı belirlenmemiş 2 kayıt');
        $this->assertSame(6, substr_count($response->getContent(), '<th class="px-4 py-3'));
    }

    public function test_default_and_explicit_filters_keep_the_selected_report(): void
    {
        $response = $this->screen();
        $this->assertSame('tenant_priority', $response->viewData('targetAudience'));
        $this->assertSame('Aylık Aidat Tablosu', $response->viewData('reportLabel'));
        $response = $this->screen(['target_audience' => 'owner_only', 'type_filter' => 'owner']);
        $this->assertSame([$this->owner->id], $response->viewData('accounts')->pluck('id')->all());
        $this->assertEquals(350, $response->viewData('accountData')[$this->owner->id]['remaining']);
        $response->assertSee('target_audience=owner_only', false);
        $this->assertSame('Aylık Malik Tablosu', $response->viewData('reportLabel'));
        $this->screen(['type_filter' => 'tenant'])->assertDontSee('owner (Kat Maliki)');
        $this->assertCount(2, $this->screen(['type_filter' => 'tenant'])->viewData('accounts'));
        $this->tenant->update(['is_active' => false]);
        $this->assertSame([$this->tenant->id], $this->screen(['type_filter' => 'all', 'status_filter' => 'inactive'])->viewData('accounts')->pluck('id')->all());
    }

    public function test_another_apartment_cannot_contribute_even_with_an_inconsistent_account_reference(): void
    {
        $other = Apartment::create(['user_id' => $this->apartment->user_id, 'name' => 'Other', 'unit_count' => 1]);
        Due::create(['apartment_id' => $other->id, 'account_id' => $this->owner->id, 'unit_id' => $this->owner->unit_id,
            'target_audience' => 'tenant_priority', 'period' => '2026-06', 'due_date' => '2026-06-10',
            'amount' => 50000, 'remaining_amount' => 50000, 'status' => 'unpaid']);
        $this->assertEquals(210, collect($this->screen()->viewData('accountData'))->sum('selectedAmount'));
    }

    public function test_month_selection_does_not_overflow_and_empty_units_keep_one_familiar_row(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-31 12:00:00'));
        $aidat = $this->screen(['month' => '2026-02']);
        $this->assertSame('2026-02', $aidat->viewData('selectedMonthStr'));
        $this->assertSame([$this->tenant->id], $aidat->viewData('accounts')->pluck('id')->all());
        $this->assertTrue($aidat->viewData('monthOptions')->contains('2026-02'));
        $this->assertSame($aidat->viewData('monthOptions')->count(), $aidat->viewData('monthOptions')->unique()->count());
        $malik = $this->screen(['month' => '2026-02', 'target_audience' => 'owner_only']);
        $this->assertSame([$this->owner->id], $malik->viewData('accounts')->pluck('id')->all());
        $this->travelBack();
    }

    public function test_invalid_report_rule_is_rejected(): void
    {
        $this->getJson(route('reports.monthly-board', ['target_audience' => 'all']))->assertUnprocessable();
    }

    #[TestWith(['tenant_priority'])]
    #[TestWith(['owner_only'])]
    public function test_pdf_and_excel_match_the_screen_with_six_unchanged_columns(string $rule): void
    {
        $filters = ['month' => '2026-06', 'target_audience' => $rule];
        $screen = $this->screen($filters);
        $expected = $screen->viewData('accountData');
        $request = Request::create('/reports/monthly-board/export', 'GET', $filters);
        $controller = app(ReportController::class);
        $currentApartment = app(CurrentApartment::class);
        $excel = $controller->monthlyBoardExport($currentApartment, $request, 'excel');
        ob_start();
        try { ($excel->getCallback())(); $bytes = ob_get_contents(); }
        finally { ob_end_clean(); }
        $path = tempnam(sys_get_temp_dir(), 'monthly-board-');
        try {
            file_put_contents($path, $bytes);
            $book = IOFactory::load($path);
            $sheet = $book->getActiveSheet();
            $this->assertSame('F', $sheet->getHighestColumn());
            $this->assertSame($screen->viewData('title'), $sheet->getCell('A1')->getValue());
            $this->assertStringContainsString('2 adet', $sheet->getCell('A2')->getValue());
            foreach ($screen->viewData('accounts') as $index => $account) {
                $row = $index + 4;
                $this->assertSame($account->name.' ('.$account->type_label.')', $sheet->getCell('B'.$row)->getValue());
                foreach (['C' => 'pastRemaining', 'D' => 'selectedAmount', 'E' => 'paid', 'F' => 'remaining'] as $column => $key) {
                    $this->assertEquals($expected[$account->id][$key], $sheet->getCell($column.$row)->getValue());
                }
            }
            $this->assertEquals(24, $sheet->getColumnDimension('B')->getWidth());
            $book->disconnectWorksheets();
        } finally { unlink($path); }

        $pdfPath = null;
        $builder = \Mockery::mock(\Spatie\LaravelPdf\PdfBuilder::class);
        $builder->shouldReceive('format')->once()->with('a4')->andReturnSelf();
        $builder->shouldReceive('margins')->once()->with(10, 10, 10, 10)->andReturnSelf();
        $builder->shouldReceive('save')->once()->andReturnUsing(function ($path) use (&$pdfPath, $builder) {
            $pdfPath = $path; file_put_contents($path, '%PDF-1.4 isolated stub'); return $builder;
        });
        Pdf::shouldReceive('view')->once()->withArgs(function ($view, $data) use ($screen, $expected) {
            $this->assertSame('reports.monthly-board-pdf', $view);
            $this->assertEquals($expected, $data['accountData']);
            $this->assertSame($screen->viewData('title'), $data['title']);
            $html = view($view, $data)->render();
            $this->assertSame(6, substr_count($html, '<th>') + substr_count($html, '<th class='));
            $this->assertStringContainsString('belirlenmemiş 2 borç', $html);
            return true;
        })->andReturn($builder);
        try {
            $pdf = $controller->monthlyBoardExport($currentApartment, $request, 'pdf');
            $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        } finally { if ($pdfPath && is_file($pdfPath)) unlink($pdfPath); }
    }

    public function test_reading_both_reports_does_not_change_financial_records(): void
    {
        $tables = ['dues', 'payments', 'payment_allocations', 'accounts', 'account_transactions', 'cash_transactions'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table =>
            hash('sha256', json_encode(\Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get()))])->all();
        $before = $snapshot();
        $this->screen(['target_audience' => 'tenant_priority']);
        $this->screen(['target_audience' => 'owner_only']);
        $this->assertSame($before, $snapshot());
    }
}
