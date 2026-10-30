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
                'due_type' => 'legacy_unknown', 'period' => $month,
                'due_date' => $month.'-10', 'amount' => $amount, 'remaining_amount' => $amount, 'status' => 'unpaid',
            ]);
            return Due::findOrFail($id);
        }
        $due = Due::create(['apartment_id' => $account->apartment_id, 'account_id' => $account->id,
            'unit_id' => $account->unit_id,
            'due_type' => $rule,
            'period' => $withoutPeriod ? null : $month, 'due_date' => $month.'-10',
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
        return $this->get(route('reports.monthly-board', array_merge(['month' => '2026-06', 'view_mode' => 'movements', 'hide_account_names' => 0], $filters)))->assertOk();
    }

    #[TestWith(['aidat'])]
    #[TestWith(['malik_payi'])]
    public function test_current_view_does_not_count_past_debt_payments_as_selected_month_payments(string $type): void
    {
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
        $this->assertSame('current', $default->viewData('viewMode'));
        $current = $default->viewData('accountData')[$this->owner->id];
        $movement = $this->screen(['due_type' => $type, 'month' => $currentMonth])->viewData('accountData')[$this->owner->id];
        $this->assertEquals(1000, $movement['pastRemaining'] - $current['pastRemaining']);
        $this->assertEquals(['pastRemaining' => 0, 'selectedAmount' => 500, 'paid' => 0, 'remaining' => 500], $current);
        $this->assertEquals(['pastRemaining' => 1000, 'selectedAmount' => 500, 'paid' => 1000, 'remaining' => 500], $movement);
        $this->assertEquals($current['remaining'], $movement['remaining']);
        $default->assertSee($default->viewData('trMonths')[now()->month].' Borcuna')->assertSee('Ödenen (₺)')->assertSee('view_mode=current', false);
        $january = $this->get(route('reports.monthly-board', ['month' => '2026-01', 'due_type' => $type]))->assertOk();
        $this->assertSame($currentMonth, $january->viewData('month'));
        $this->getJson(route('reports.monthly-board', ['view_mode' => 'invalid']))->assertUnprocessable();
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

    #[TestWith(['aidat'])]
    #[TestWith(['malik_payi'])]
    public function test_month_end_reconstructs_debts_paid_in_later_months(string $rule): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-04-10'));
        \App\Models\SubscriptionItem::query()->update(['started_at' => '2025-01-01']);
        $past = $this->due($this->owner, $rule, '2025-12', 80, 0, true);
        $january = $this->due($this->owner, $rule, '2026-01', 100);
        $pay = function ($due, $date, $amount) {
            $payment = Payment::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->owner->id,
                'payment_date' => $date, 'amount' => $amount, 'unallocated_amount' => $amount]);
            $payment->allocateToDue($due, $amount);
        };
        $pay($past, '2026-01-31', 30);
        $pay($past, '2026-02-01', 50);
        $pay($january, '2026-01-31', 25);
        $pay($january, '2026-03-01', 75);
        $this->owner->update(['is_active' => false, 'account_end_date' => '2025-12-31']);
        $this->owner->delete();
        $filters = ['due_type' => $rule];
        $jan = $this->screen([...$filters, 'month' => '2026-01']);
        $this->assertSame('2026-01-31', $jan->viewData('balanceDate'));
        $this->assertEquals(['pastRemaining' => 80, 'selectedAmount' => 100, 'paid' => 55, 'remaining' => 125], $jan->viewData('accountData')[$this->owner->id]);
        $feb = $this->screen([...$filters, 'month' => '2026-02']);
        $this->assertEquals(['pastRemaining' => 125, 'selectedAmount' => 0, 'paid' => 50, 'remaining' => 75], $feb->viewData('accountData')[$this->owner->id]);
        $march = $this->screen([...$filters, 'month' => '2026-03']);
        $this->assertEquals(['pastRemaining' => 75, 'selectedAmount' => 0, 'paid' => 75, 'remaining' => 0], $march->viewData('accountData')[$this->owner->id]);
        $april = $this->screen([...$filters, 'month' => '2026-04']);
        $this->assertEquals(0, $april->viewData('accountData')[$this->owner->id]['pastRemaining'] ?? 0);
        $this->travelBack();
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

    #[TestWith(['aidat', 50, 210, 70, 190, 3])]
    #[TestWith(['malik_payi', 300, 270, 150, 420, 2])]
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
        $this->assertSame(6, substr_count($response->getContent(), '<th class="px-4 py-3'));
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
        $this->assertEquals(50, $response->viewData('accountData')[$this->owner->id]['pastRemaining']);
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
    #[TestWith(['aidat', 'current'])]
    #[TestWith(['malik_payi', 'current'])]
    public function test_pdf_and_excel_match_the_screen_with_six_unchanged_columns(string $rule, string $mode = 'movements'): void
    {
        Payment::query()->update(['payment_date' => '2026-10-01']);
        $this->owner->update(['is_active' => false, 'account_end_date' => '2026-05-31']);
        $this->tenant->delete();
        $filters = ['month' => '2026-06', 'due_type' => $rule, 'view_mode' => $mode, 'hide_account_names' => 0, 'hide_account_type' => 0];
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
            $this->assertNull($sheet->getCell('A2')->getValue());
            $this->assertStringContainsString($screen->viewData('unknownDuesCount').' adet', $sheet->getCell('A3')->getValue());
            foreach ($screen->viewData('accounts') as $index => $account) {
                $row = $index + 5;
                $this->assertSame($account->unit?->unit_no.' ('.$account->type_label.')', $sheet->getCell('A'.$row)->getValue());
                $this->assertSame($account->name, $sheet->getCell('B'.$row)->getValue());
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
            $this->assertStringNotContainsString('Rapor Tarihi:', $html);
            $this->assertSame(6, substr_count($html, '<th>') + substr_count($html, '<th class='));
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
        $filters = ['month' => '2026-06', 'due_type' => 'aidat', 'view_mode' => 'movements'];
        $screen = $this->get(route('reports.monthly-board', $filters))->assertOk()
            ->assertDontSee('>Hesap Adı</th>', false);
        $this->assertTrue($screen->viewData('hideAccountNames'));
        $this->assertCount(3, $screen->viewData('accounts'));

        $pdf = view('reports.monthly-board-pdf', [
            'title' => $screen->viewData('title'),
            'accounts' => $screen->viewData('accounts'),
            'accountData' => $screen->viewData('accountData'),
            'parsedMonth' => $screen->viewData('parsedMonth'),
            'paidLabel' => $screen->viewData('paidLabel'),
            'unknownDuesCount' => $screen->viewData('unknownDuesCount'),
            'hideAccountType' => $screen->viewData('hideAccountType'),
            'hideAccountNames' => true,
        ])->render();
        $this->assertStringNotContainsString('Hesap Adı', $pdf);
        $this->assertSame(5, substr_count($pdf, '<th>') + substr_count($pdf, '<th class='));

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
            $this->assertSame('E', $sheet->getHighestColumn());
            $this->assertSame('Geçmiş Borç (₺)', $sheet->getCell('B4')->getValue());
            $this->assertNotSame('Hesap Adı', $sheet->getCell('B4')->getValue());
            $this->assertSame(7, $sheet->getHighestRow());
            $book->disconnectWorksheets();
        } finally { unlink($path); }
    }

    public function test_reading_both_reports_does_not_change_financial_records(): void
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
