<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportController;
use App\Models\{Account, Apartment, Category, Due, Payment, Unit, User};
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

        $this->due($this->owner, 'aidat', '2026-06', 100, 40);
        $this->due($this->owner, 'aidat', '2026-05', 50, 10, true);
        $this->due($this->tenant, 'aidat', '2026-06', 80, 20);
        $this->due($this->secondTenant, 'aidat', '2026-06', 30);
        $this->due($this->owner, 'malik_payi', '2026-06', 200, 50);
        $this->due($this->owner, 'malik_payi', '2026-04', 300, 100);
        $this->due($this->tenant, 'malik_payi', '2026-06', 70);
        // Both report modes and exports must include every malik_payi category.
        foreach (['Ceza' => 200, 'Faiz' => 300, 'Diğer' => 70] as $name => $amount) {
            $category = Category::create(['apartment_id' => $this->apartment->id, 'name' => $name,
                'is_active' => true, 'allow_malik_payi' => true]);
            Due::where('apartment_id', $this->apartment->id)->where('due_type', 'malik_payi')
                ->where('amount', $amount)->update(['category_id' => $category->id]);
        }
        $this->due($this->owner, null, '2026-06', 9000);
        $this->due($this->owner, null, '2026-05', 8000);
        $this->due($this->owner, null, '2026-07', 7000);
        foreach (['aidat', 'malik_payi'] as $rule) {
            $this->due($this->owner, $rule, '2026-07', 10000);
            $this->due($this->owner, $rule, '2026-06', 20000)->delete();
        }
    }

    private function due(Account $account, ?string $rule, string $month, float $amount, float $paid = 0, bool $withoutPeriod = false): Due
    {
        if ($rule === null) {
            // Yeni kayıtlarda tür zorunlu; tanınmayan tarihsel veri yalnız ham fixture olarak eklenir.
            $id = \Illuminate\Support\Facades\DB::table('dues')->insertGetId([
                'apartment_id' => $account->apartment_id, 'account_id' => $account->id, 'unit_id' => $account->unit_id,
                'due_type' => 'legacy_unknown', 'created_at_manual' => $month.'-01',
                'due_date' => $month.'-10', 'amount' => $amount, 'remaining_amount' => $amount, 'status' => 'unpaid',
            ]);
            return Due::findOrFail($id);
        }
        $due = Due::create(['apartment_id' => $account->apartment_id, 'account_id' => $account->id,
            'unit_id' => $account->unit_id,
            'due_type' => $rule,
            'created_at_manual' => $month.'-01', 'due_date' => $month.'-10',
            'amount' => $amount, 'remaining_amount' => $amount, 'status' => 'unpaid']);
        if ($paid > 0) {
            $payment = Payment::create(['apartment_id' => $account->apartment_id, 'account_id' => $account->id,
                'amount' => $paid, 'unallocated_amount' => $paid, 'payment_date' => '2026-06-30']);
            $payment->allocateToDue($due, $paid);
        }
        return $due;
    }

    private function screen(array $filters = [])
    {
        return $this->get(route('reports.monthly-board', array_merge(['month' => '2026-06', 'hide_account_names' => 0, 'only_period_residents' => 0], $filters)))->assertOk();
    }

    #[TestWith(['aidat'])]
    #[TestWith(['malik_payi'])]
    public function test_current_view_does_not_count_past_debt_payments_as_selected_month_payments(string $type): void
    {
        $this->travelTo(now()->addDays(7)->setTime(12, 0));
        Due::where('apartment_id', $this->apartment->id)->delete();
        $rule = $type;
        $currentMonth = now()->format('Y-m');
        $pastMonth = now()->subMonth()->format('Y-m');
        $pastDue = $this->due($this->owner, $rule, $pastMonth, 1000);
        $this->due($this->owner, $rule, $currentMonth, 500);
        $payment = Payment::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->owner->id,
            'amount' => 1000, 'unallocated_amount' => 1000, 'payment_date' => today()]);
        $payment->allocateToDue($pastDue, 1000);
        $default = $this->get(route('reports.monthly-board', ['month' => $currentMonth, 'due_type' => $type]))->assertOk();
        $current = $default->viewData('accountData')[$this->owner->id];
        $movement = $this->screen(['due_type' => $type, 'month' => $currentMonth])->viewData('accountData')[$this->owner->id];
        $this->assertEquals($current, $movement);
        $this->assertEquals(['pastRemaining' => 0, 'selectedAmount' => 500, 'paid' => 0, 'remaining' => 500], $current);
        $this->assertEquals($current['remaining'], $movement['remaining']);
        $default->assertSee($default->viewData('trMonths')[now()->month].' '.now()->year)->assertSee('✗ Ödenmedi')->assertDontSee('view_mode=', false);
        $january = $this->get(route('reports.monthly-board', ['month' => '2026-01', 'due_type' => $type]))->assertOk();
        $this->assertSame('2026-01', $january->viewData('month'));
        $this->travelBack();
    }

    #[TestWith(['connection'])]
    #[TestWith(['server'])]
    #[TestWith(['empty'])]
    #[TestWith(['invalid'])]
    public function test_pdf_failures_show_recovery_screen_and_clean_temporary_files(string $failure): void
    {
        $temporaryPath = null;
        $builder = \Mockery::mock(\Spatie\LaravelPdf\PdfBuilder::class);
        $builder->shouldReceive('format')->andReturnSelf();
        $builder->shouldReceive('margins')->andReturnSelf();
        $builder->shouldReceive('save')->once()->andReturnUsing(function ($path) use ($failure, &$temporaryPath, $builder) {
            $temporaryPath = $path;
            file_put_contents($path, $failure === 'empty' ? '' : 'partial');
            if ($failure === 'connection') {
                throw new \Illuminate\Http\Client\ConnectionException('private-host SSL certificate error');
            }
            if ($failure === 'server') {
                throw new \RuntimeException('private-host HTTP 500');
            }
            return $builder;
        });
        Pdf::shouldReceive('view')->once()->andReturn($builder);
        $this->get(route('reports.monthly-board.export', ['type' => 'pdf', 'month' => '2026-06', 'due_type' => 'malik_payi']))
            ->assertStatus(503)->assertSee('PDF oluşturulamadı')->assertSee('Tekrar Dene')->assertSee('Önceki Sayfaya Dön')
            ->assertSee('href="'.e(route('reports.monthly-board', ['month' => '2026-06', 'due_type' => 'malik_payi'])).'"', false)
            ->assertDontSee('private-host')->assertHeaderMissing('Content-Disposition');
        $this->assertNotNull($temporaryPath);
        $this->assertFileDoesNotExist($temporaryPath);
    }

    public function test_pdf_failure_returns_a_safe_json_message(): void
    {
        Pdf::shouldReceive('view')->once()->andThrow(new \RuntimeException('private PDF service error'));
        $this->getJson(route('reports.monthly-board.export', ['type' => 'pdf', 'month' => '2026-06']))
            ->assertStatus(503)->assertJson(['message' => 'PDF şu anda oluşturulamadı. Lütfen biraz sonra tekrar deneyin.'])
            ->assertDontSee('private PDF service error');
    }

    public function test_statement_pdf_failure_returns_to_the_same_account_and_date_filters(): void
    {
        Pdf::shouldReceive('view')->once()->andThrow(new \RuntimeException('PDF unavailable'));
        $filters = ['date_from' => '2026-01-01', 'date_to' => '2026-04-30'];
        $this->get(route('accounts.statement.export', ['id' => $this->owner->id, 'type' => 'pdf', ...$filters]))
            ->assertStatus(503)
            ->assertSee('href="'.e(route('accounts.statement', ['id' => $this->owner->id, ...$filters])).'"', false);
    }

    public function test_current_and_future_months_do_not_count_future_dated_payments(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-06-15'));
        \App\Models\SubscriptionItem::query()->update(['started_at' => '2025-01-01']);
        $response = $this->screen();
        $this->assertSame('2026-06-15', $response->viewData('balanceDate'));
        $this->assertEquals(0, collect($response->viewData('accountData'))->sum('paid'));
        $this->assertEquals(260, collect($response->viewData('accountData'))->sum('remaining'));
        $future = $this->screen(['month' => '2026-07']);
        $this->assertSame('2026-06-15', $future->viewData('balanceDate'));
        $this->assertSame('2026-06', $future->viewData('month'));
        $this->assertEquals(260, collect($future->viewData('accountData'))->sum('remaining'));
        $this->travelBack();
    }

    #[TestWith(['aidat', 40, 210, 60, 190, 3])]
    #[TestWith(['malik_payi', 200, 270, 50, 420, 2])]
    public function test_scope_preserves_all_relevant_accounts_and_current_balances(string $rule, float $past, float $amount, float $paid, float $remaining, int $accountCount): void
    {
        $response = $this->screen(['due_type' => $rule]);
        $data = collect($response->viewData('accountData'));
        $this->assertCount($accountCount, $response->viewData('accounts'));
        $this->assertEquals($past, $data->sum('pastRemaining'));
        $this->assertEquals($amount, $data->sum('selectedAmount'));
        $this->assertEquals($paid, $data->sum('paid'));
        $this->assertEquals($remaining, $data->sum('remaining'));
        $this->assertSame(2, $response->viewData('unknownDuesCount'));
        $response->assertSee('borç türü belirlenmemiş 2 kayıt');
        $this->assertSame(5, substr_count($response->getContent(), '<th class="px-4 py-3'));
    }

    public function test_default_and_explicit_filters_keep_the_selected_report(): void
    {
        $response = $this->screen();
        $this->assertSame('aidat', $response->viewData('reportDueType'));
        $this->assertSame('Aidat Borç Tablosu', $response->viewData('reportLabel'));
        $response = $this->screen(['due_type' => 'malik_payi', 'type_filter' => 'owner']);
        $this->assertSame([$this->owner->id, $this->tenant->id], $response->viewData('accounts')->pluck('id')->all());
        $this->assertEquals(350, $response->viewData('accountData')[$this->owner->id]['remaining']);
        $response->assertSee('due_type=malik_payi', false);
        $this->assertSame('Kat Malikleri Demirbaş Borç Tablosu', $response->viewData('reportLabel'));
        $this->assertSame('KAT MALİKLERİ DEMİRBAŞ BORÇ TABLOSU - HAZİRAN 2026 - '.$this->apartment->name, $response->viewData('title'));
        $this->assertSame('HAZİRAN 2026 - '.$this->apartment->name, $response->viewData('subtitle'));
        $response->assertDontSee('name="type_filter"', false)->assertDontSee('name="status_filter"', false);
        $this->assertCount(3, $this->screen(['type_filter' => 'tenant'])->viewData('accounts'));
        $this->tenant->update(['is_active' => false]);
        $this->assertCount(3, $this->screen(['type_filter' => 'all', 'status_filter' => 'inactive'])->viewData('accounts'));
    }

    public function test_inactive_and_deleted_accounts_keep_relevant_debts_after_their_end_date(): void
    {
        $this->owner->update(['is_active' => false, 'account_end_date' => '2026-05-31']);
        $this->tenant->delete();
        $response = $this->screen();
        $this->assertCount(3, $response->viewData('accounts'));
        $this->assertEquals(210, collect($response->viewData('accountData'))->sum('selectedAmount'));
        $this->assertEquals(40, $response->viewData('accountData')[$this->owner->id]['pastRemaining']);
        $pastOnly = $this->screen(['month' => '2026-07']);
        $this->assertArrayHasKey($this->owner->id, $pastOnly->viewData('accountData'));
        $emptyMonth = $this->screen(['month' => '2026-02']);
        $this->assertSame([$this->secondTenant->id], $emptyMonth->viewData('accounts')->pluck('id')->all());
    }

    public function test_another_apartment_cannot_contribute_even_with_an_inconsistent_account_reference(): void
    {
        $other = Apartment::create(['user_id' => $this->apartment->user_id, 'name' => 'Other', 'unit_count' => 1]);
        Due::create(['apartment_id' => $other->id, 'account_id' => $this->owner->id, 'unit_id' => $this->owner->unit_id,
            'due_type' => 'aidat', 'period' => '2026-06', 'due_date' => '2026-06-10',
            'amount' => 50000, 'remaining_amount' => 50000, 'status' => 'unpaid']);
        $this->assertEquals(210, collect($this->screen()->viewData('accountData'))->sum('selectedAmount'));
    }

    public function test_month_selection_does_not_overflow_and_empty_units_keep_one_familiar_row(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-31 12:00:00'));
        $this->grantPaidFeatures($this->apartment);
        $aidat = $this->screen(['month' => '2026-02']);
        $this->assertSame('2026-02', $aidat->viewData('selectedMonthStr'));
        $this->assertSame([$this->tenant->id], $aidat->viewData('accounts')->pluck('id')->all());
        $aidat->assertSee('name="period_year"', false)->assertSee('name="period_month"', false);
        $malik = $this->screen(['month' => '2026-02', 'due_type' => 'malik_payi']);
        $this->assertSame([$this->owner->id], $malik->viewData('accounts')->pluck('id')->all());
        $this->travelBack();
    }

    public function test_invalid_report_rule_is_rejected(): void
    {
        $this->getJson(route('reports.monthly-board', ['due_type' => 'all']))->assertUnprocessable();
    }

    #[TestWith([2026, 1, 'previous', '2025-12'])]
    #[TestWith([2026, 12, 'next', '2027-01'])]
    #[TestWith([2026, 5, null, '2026-05'])]
    public function test_period_controls_keep_report_type_and_export_period(int $year, int $month, ?string $step, string $expected): void
    {
        $filters = ['due_type' => 'malik_payi', 'period_year' => $year, 'period_month' => $month];
        if ($step !== null) {
            $filters['period_step'] = $step;
        }
        $response = $this->screen($filters);
        $expected = min($expected, now()->format('Y-m'));
        $this->assertSame($expected, $response->viewData('month'));
        $this->assertSame('malik_payi', $response->viewData('reportDueType'));
        $response->assertSee('month='.$expected, false)->assertSee('due_type=malik_payi', false);
    }

    public function test_period_controls_return_to_current_month_and_validate_input(): void
    {
        $response = $this->screen(['period_year' => 2020, 'period_month' => 1, 'period_step' => 'current']);
        $this->assertSame(now()->format('Y-m'), $response->viewData('month'));
        $this->getJson(route('reports.monthly-board', ['period_year' => 2026, 'period_month' => 13]))->assertUnprocessable();
        $this->getJson(route('reports.monthly-board', ['period_year' => 2026]))->assertUnprocessable();
    }

    #[TestWith(['aidat'])]
    #[TestWith(['malik_payi'])]
    public function test_pdf_and_excel_match_the_single_board(string $rule): void
    {
        Payment::query()->update(['payment_date' => '2026-10-01']);
        $this->owner->update(['is_active' => false, 'account_end_date' => '2026-05-31']);
        $this->tenant->delete();
        $filters = ['month' => '2026-06', 'due_type' => $rule, 'hide_account_names' => 0, 'hide_account_type' => 0, 'only_period_residents' => 0];
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
            $this->assertSame('E', $sheet->getHighestColumn());
            $this->assertSame($screen->viewData('title'), $sheet->getCell('A1')->getValue());
            $this->assertNull($sheet->getCell('A2')->getValue());
            $this->assertStringContainsString($screen->viewData('unknownDuesCount').' adet', $sheet->getCell('A3')->getValue());
            foreach ($screen->viewData('accounts') as $index => $account) {
                $row = $index + 5;
                $this->assertSame($account->unit?->unit_no.' ('.$account->type_label.')', $sheet->getCell('A'.$row)->getValue());
                $this->assertSame($account->name, $sheet->getCell('B'.$row)->getValue());
                foreach (['C' => 'pastRemaining', 'E' => 'remaining'] as $column => $key) {
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
            $this->assertStringNotContainsString('Rapor Tarihi:', $html);
            $this->assertSame(5, substr_count($html, '<th>') + substr_count($html, '<th class='));
            $this->assertStringContainsString('belirlenmemiş '.$screen->viewData('unknownDuesCount').' borç', $html);
            return true;
        })->andReturn($builder);
        try {
            $pdf = $controller->monthlyBoardExport($currentApartment, $request, 'pdf');
            $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        } finally { if ($pdfPath && is_file($pdfPath)) unlink($pdfPath); }
    }

    public function test_hidden_account_names_remove_the_column_from_pdf_and_excel_without_merging_rows(): void
    {
        $filters = ['month' => '2026-06', 'due_type' => 'aidat', 'view_mode' => 'movements', 'hide_account_names' => 1, 'only_period_residents' => 0];
        $screen = $this->get(route('reports.monthly-board', $filters))->assertOk()
            ->assertDontSee('>Hesap Adı</th>', false);
        $this->assertTrue($screen->viewData('hideAccountNames'));
        $this->assertCount(3, $screen->viewData('accounts'));

        $pdf = view('reports.monthly-board-pdf', $screen->original->getData())->render();
        $this->assertStringNotContainsString('Hesap Adı', $pdf);
        $this->assertSame(4, substr_count($pdf, '<th>') + substr_count($pdf, '<th class='));

        $request = Request::create('/reports/monthly-board/export/excel', 'GET', $filters);
        $excel = app(ReportController::class)->monthlyBoardExport(app(CurrentApartment::class), $request, 'excel');
        ob_start();
        try { ($excel->getCallback())(); $bytes = ob_get_contents(); }
        finally { ob_end_clean(); }
        $path = tempnam(sys_get_temp_dir(), 'monthly-board-hidden-');
        try {
            file_put_contents($path, $bytes);
            $book = IOFactory::load($path);
            $sheet = $book->getActiveSheet();
            $this->assertSame('D', $sheet->getHighestColumn());
            $this->assertSame('Geçmiş Borç (₺)', $sheet->getCell('B4')->getValue());
            $this->assertNotSame('Hesap Adı', $sheet->getCell('B4')->getValue());
            $this->assertSame(8, $sheet->getHighestRow());
            $book->disconnectWorksheets();
        } finally { unlink($path); }
    }

    #[TestWith(['aidat'])]
    #[TestWith(['malik_payi'])]
    public function test_current_range_has_monthly_statuses_and_only_counts_outstanding_debt(string $type): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-10 12:00:00'));
        $this->grantPaidFeatures($this->apartment);
        Due::where('apartment_id', $this->apartment->id)->delete();
        $this->due($this->owner, $type, '2026-03', 500, 100);
        $this->due($this->owner, $type, '2026-04', 1000, 1000);
        $this->due($this->owner, $type, '2026-05', 1000);
        $this->due($this->owner, $type, '2026-06', 1000, 500, true);
        $this->due($this->owner, $type, '2026-07', 1000);
        $this->due($this->owner, $type, '2026-08', 9000);
        $this->due($this->owner, $type === 'aidat' ? 'malik_payi' : 'aidat', '2026-04', 7000);
        $this->due($this->owner, $type, '2026-05', 8000)->delete();
        $filters = ['view_mode' => 'current', 'month' => '2026-04', 'month_end' => '2026-07', 'due_type' => $type];
        $screen = $this->screen($filters)->assertSee('✓ Ödendi')->assertSee('✗ Ödenmedi')
            ->assertSee('Kısmi ödendi')->assertSee('month_end=2026-07', false)->assertSee('Geçmiş Borç');
        $this->assertSame(['2026-04', '2026-05', '2026-06', '2026-07'], $screen->viewData('reportMonths'));
        $item = $screen->viewData('accountData')[$this->owner->id];
        $this->assertEquals(['pastRemaining' => 400, 'selectedAmount' => 4000, 'paid' => 1500, 'remaining' => 2900], $item);
        $cells = $screen->viewData('monthlyData')[$this->owner->id];
        $this->assertSame('✓ Ödendi', $cells['2026-04']['status']);
        $this->assertSame('✗ Ödenmedi', $cells['2026-05']['status']);
        $this->assertSame('Kısmi ödendi', $cells['2026-06']['status']);
        $this->assertEquals(500, $cells['2026-06']['remaining']);

        $pdf = view('reports.monthly-board-pdf', $screen->original->getData())->render();
        $this->assertSame(8, substr_count($pdf, '<th>') + substr_count($pdf, '<th class='));
        $this->assertStringContainsString('✓ Ödendi', $pdf);
        $this->assertStringContainsString('2.900,00 ₺', $pdf);
        $excel = app(ReportController::class)->monthlyBoardExport(app(CurrentApartment::class),
            Request::create('/', 'GET', $filters + ['only_period_residents' => 0]), 'excel');
        ob_start();
        try { ($excel->getCallback())(); $bytes = ob_get_contents(); }
        finally { ob_end_clean(); }
        $path = tempnam(sys_get_temp_dir(), 'board-range-');
        try {
            file_put_contents($path, $bytes);
            $book = IOFactory::load($path);
            $sheet = $book->getActiveSheet();
            $this->assertSame('H', $sheet->getHighestColumn());
            $this->assertSame('Nisan 2026', $sheet->getCell('D4')->getValue());
            $this->assertSame('1.000,00 ₺ (✓ Ödendi)', $sheet->getCell('D5')->getValue());
            $this->assertEquals(2900, $sheet->getCell('H5')->getValue());
            $book->disconnectWorksheets();
        } finally { unlink($path); }
        $this->travelBack();
    }

    public function test_range_validation_cross_year_empty_months_and_legacy_links(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-10 12:00:00'));
        $this->grantPaidFeatures($this->apartment);
        $screen = $this->screen(['view_mode' => 'current', 'month' => '2025-12', 'month_end' => '2026-02']);
        $this->assertSame(['2025-12', '2026-01', '2026-02'], $screen->viewData('reportMonths'));
        $this->assertSame('—', $screen->viewData('monthlyData')[$this->tenant->id]['2026-01']['status']);
        $this->getJson(route('reports.monthly-board', ['month' => '2026-07', 'month_end' => '2026-04']))->assertUnprocessable();
        $this->getJson(route('reports.monthly-board', ['month_end' => 'invalid']))->assertUnprocessable();
        $clamped = $this->screen(['view_mode' => 'current', 'month' => '2026-09', 'month_end' => '2027-01']);
        $this->assertSame(['2026-09', '2026-10', '2026-11'], $clamped->viewData('reportMonths'));
        $movement = $this->screen(['month' => '2026-06', 'month_end' => '2026-07']);
        $this->assertSame(['2026-06', '2026-07'], $movement->viewData('reportMonths'));
        $this->travelBack();
    }

    public function test_range_residents_include_both_tenants_when_the_occupant_changes(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-10 12:00:00'));
        $this->grantPaidFeatures($this->apartment);
        $this->tenant->update(['account_end_date' => '2026-05-31']);
        $this->secondTenant->update(['account_opening_date' => '2026-06-01']);
        foreach ([[$this->tenant, '2026-04-01', '2026-05-31'], [$this->secondTenant, '2026-06-01', null]] as [$account, $start, $end]) {
            $account->tenantAssignments()->create(['apartment_id' => $this->apartment->id,
                'unit_id' => $account->unit_id, 'move_in_date' => $start, 'move_out_date' => $end]);
        }
        $this->due($this->tenant, 'aidat', '2026-04', 100);
        $screen = $this->screen(['view_mode' => 'current', 'month' => '2026-04', 'month_end' => '2026-07', 'only_period_residents' => 1]);
        $this->assertSame([$this->tenant->id, $this->secondTenant->id], $screen->viewData('accounts')->pluck('id')->all());
        $this->travelBack();
    }

    public function test_reading_both_debt_types_does_not_change_financial_records(): void
    {
        $tables = ['dues', 'payments', 'payment_allocations', 'accounts', 'account_transactions', 'cash_transactions'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table =>
            hash('sha256', json_encode(\Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get()))])->all();
        $before = $snapshot();
        $this->screen(['due_type' => 'aidat']);
        $this->screen(['due_type' => 'malik_payi']);
        $this->assertSame($before, $snapshot());
    }
}
