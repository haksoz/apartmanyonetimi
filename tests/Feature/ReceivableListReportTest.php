<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportController;
use App\Models\{Account, AccountTransaction, Due, Payment};
use App\Support\CurrentApartment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Blade, Schema};
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\LaravelPdf\Facades\Pdf;

/** Uses the existing migration-free, :memory:-only fixture and payment regression tests. */
class ReceivableListReportTest extends ExpenseFullPaymentTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('dues', function (Blueprint $table) {
            $table->id();
            foreach (['apartment_id', 'account_id', 'unit_id', 'category_id'] as $column) $table->unsignedBigInteger($column)->nullable();
            foreach (['amount', 'remaining_amount'] as $column) $table->decimal($column, 15, 2);
            foreach (['due_type', 'reference_number', 'period', 'status', 'description'] as $column) $table->string($column)->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function account(string $type, string $name = 'Report account', int $apartmentId = 0): Account
    {
        return Account::create(['apartment_id' => $apartmentId ?: $this->apartment->id, 'type' => $type, 'name' => $name]);
    }

    private function entry(Account $account, string $type, float $amount, string $description = 'Direct ledger entry'): void
    {
        $account->transactions()->create(['apartment_id' => $account->apartment_id, 'type' => $type,
            'amount' => $amount, 'transaction_date' => '2026-10-01', 'description' => $description]);
    }

    private function screen(string $type = 'supplier')
    {
        $request = Request::create('/reports/receivable-list', 'GET', ['account_type' => $type]);
        $this->app->instance('request', $request);
        return app(ReportController::class)->receivableList(app(CurrentApartment::class), $request);
    }

    #[TestWith(['owner', 0, 1000])]
    #[TestWith(['owner', 400, 600])]
    #[TestWith(['owner', 1000, 0])]
    #[TestWith(['owner', 1200, 0])]
    #[TestWith(['tenant', 0, 1000])]
    #[TestWith(['tenant', 400, 600])]
    #[TestWith(['tenant', 1000, 0])]
    #[TestWith(['tenant', 1200, 0])]
    public function test_resident_report_matches_existing_ledger_with_real_allocations(string $type, int $received, int $expected): void
    {
        $account = $this->account($type);
        $due = Due::create(['apartment_id' => $this->apartment->id, 'account_id' => $account->id,
            'due_type' => 'aidat', 'amount' => 1000, 'remaining_amount' => 1000, 'due_date' => '2026-10-01']);
        $due->transactions()->create(['apartment_id' => $this->apartment->id, 'account_id' => $account->id,
            'type' => 'debit', 'amount' => 1000, 'transaction_date' => '2026-10-01']);
        if ($received) {
            $payment = Payment::create(['apartment_id' => $this->apartment->id, 'account_id' => $account->id,
                'amount' => $received, 'unallocated_amount' => $received, 'payment_date' => '2026-10-01']);
            $payment->transactions()->create(['apartment_id' => $this->apartment->id, 'account_id' => $account->id,
                'type' => 'credit', 'amount' => $received, 'transaction_date' => '2026-10-01']);
            $payment->allocateToDue($due, min(1000, $received));
            $this->assertEquals(max(0, $received - 1000), $payment->fresh()->unallocated_amount);
        }
        $ledger = $account->ledger_balance;
        $this->assertEquals($received - 1000, $ledger);
        $row = $this->screen($type)->getData()['accounts']->firstWhere('id', $account->id);
        if ($expected) {
            $this->assertNotNull($row);
            $this->assertEquals(max(0, -$ledger), $row->total_receivable);
            $this->assertEquals($expected, $row->total_receivable);
            $this->assertEquals($received, $row->total_payments);
        } else {
            $this->assertNull($row);
        }
    }

    #[TestWith([false, 1000])]
    #[TestWith([true, 0])]
    public function test_supplier_report_matches_existing_expense_and_payment_ledger(bool $paid, int $expected): void
    {
        $this->post(route('expenses.store'), $this->payload(['account_id' => $this->supplier->id,
            'category_id' => $this->category->id, 'expense_date' => '2026-10-01',
            'period_month' => '2026-10', 'is_paid' => $paid]))->assertSessionHasNoErrors();
        $ledger = $this->supplier->ledger_balance;
        $this->assertEquals($expected, $ledger);
        $row = $this->screen()->getData()['accounts']->firstWhere('id', $this->supplier->id);
        if ($expected) {
            $this->assertEquals($ledger, $row->total_receivable);
        } else {
            $this->assertNull($row);
        }
    }

    public function test_opening_and_direct_entries_cents_scope_and_existing_filter_behavior(): void
    {
        foreach (['owner', 'tenant', 'supplier'] as $type) {
            $account = $this->account($type, $type.' ledger');
            $direction = $type === 'supplier' ? 'credit' : 'debit';
            $opposite = $direction === 'credit' ? 'debit' : 'credit';
            $this->entry($account, $direction, 1000.01, 'Açılış bakiyesi');
            $this->entry($account, $opposite, 400, 'Direct adjustment');
            $foreign = $this->account($type, 'Foreign apartment', 999);
            $this->entry($foreign, $direction, 9000);
            $zero = $this->account($type, 'Zero balance');
            $oppositeAccount = $this->account($type, 'Opposite balance');
            $this->entry($oppositeAccount, $opposite, 25);
            $deleted = $this->account($type, 'Deleted');
            $this->entry($deleted, $direction, 50);
            $deleted->delete();
            $account->update(['is_active' => false]); // Existing report includes inactive, nondeleted accounts.
            $rows = $this->screen($type)->getData()['accounts'];
            $this->assertSame([$account->id], $rows->pluck('id')->values()->all());
            $this->assertSame(600.01, $rows->first()->total_receivable);
            $this->assertEquals(abs($account->ledger_balance), $rows->first()->total_receivable);
            $this->assertSame(0.0, $rows->first()->total_payments);
        }
    }

    public function test_unallocated_payment_does_not_change_report_when_allocation_is_added(): void
    {
        $account = $this->account('owner');
        $this->entry($account, 'debit', 1000);
        $this->entry($account, 'credit', 400);
        $payment = Payment::create(['apartment_id' => $this->apartment->id, 'account_id' => $account->id,
            'amount' => 400, 'unallocated_amount' => 400, 'payment_date' => '2026-10-01']);
        $due = Due::create(['apartment_id' => $this->apartment->id, 'account_id' => $account->id,
            'due_type' => 'aidat', 'amount' => 1000, 'remaining_amount' => 1000]);
        $before = $this->screen('owner')->getData()['accounts']->first()->total_receivable;
        $payment->allocateToDue($due, 400);
        $this->assertEquals(600, $before);
        $this->assertEquals($before, $this->screen('owner')->getData()['accounts']->first()->total_receivable);
        $this->assertEquals(-600, $account->ledger_balance);
    }

    #[TestWith(['owner'])]
    #[TestWith(['tenant'])]
    #[TestWith(['supplier'])]
    public function test_screen_pdf_and_excel_use_identical_accounts_and_values(string $type): void
    {
        $positive = $this->account($type, 'Positive '. $type);
        $direction = $type === 'supplier' ? 'credit' : 'debit';
        $this->entry($positive, $direction, 1634.56);
        $payment = Payment::create(['apartment_id' => $this->apartment->id, 'account_id' => $positive->id,
            'amount' => 400, 'unallocated_amount' => 400, 'payment_date' => '2026-10-01']);
        $payment->transactions()->create(['apartment_id' => $this->apartment->id, 'account_id' => $positive->id,
            'type' => $type === 'supplier' ? 'debit' : 'credit', 'amount' => 400, 'transaction_date' => '2026-10-01']);
        $this->account($type, 'Omitted zero account');
        $screen = $this->screen($type);
        $this->assertSame('reports.receivable-list', $screen->name());
        $data = $screen->getData();
        $expected = $data['accounts']->mapWithKeys(fn ($a) => [$a->name => [$a->total_payments, $a->total_receivable]])->all();
        // Render the actual screen body without the unrelated application navigation/membership queries.
        $body = file_get_contents(resource_path('views/reports/receivable-list.blade.php'));
        $body = str_replace(["@extends('layouts.app')", "@section('content')", '@endsection'], '', $body);
        \Illuminate\View\Component::flushCache();
        $html = Blade::render($body, $data, deleteCachedView: true);
        $this->assertStringContainsString('1.234,56', $html);
        $this->assertStringNotContainsString('Omitted zero account', $html);
        $request = Request::create('/reports/receivable-list/export/excel', 'GET', ['account_type' => $type]);
        $response = app(ReportController::class)->receivableListExport(app(CurrentApartment::class), $request, 'excel');
        ob_start();
        try {
            ($response->getCallback())();
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $path = tempnam(sys_get_temp_dir(), 'receivable-xlsx-');
        try {
            file_put_contents($path, $bytes);
            $book = IOFactory::load($path);
            $sheet = $book->getActiveSheet();
            $actual = [];
            for ($row = 4; $row <= $sheet->getHighestRow(); $row++) {
                $actual[$sheet->getCell('A'.$row)->getValue()] = [
                    $sheet->getCell('D'.$row)->getValue(), $sheet->getCell('E'.$row)->getValue(),
                ];
            }
            $this->assertEquals($expected, $actual);
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $pdfPath = null;
        $builder = \Mockery::mock(\Spatie\LaravelPdf\PdfBuilder::class);
        $builder->shouldReceive('format')->once()->with('a4')->andReturnSelf();
        $builder->shouldReceive('margins')->once()->with(10, 10, 10, 10)->andReturnSelf();
        $builder->shouldReceive('save')->once()->andReturnUsing(function ($path) use (&$pdfPath, $builder) {
            $pdfPath = $path;
            file_put_contents($path, '%PDF-1.4 isolated renderer stub');
            return $builder;
        });
        Pdf::shouldReceive('view')->once()->withArgs(function ($view, $pdfData) use ($expected) {
            $this->assertSame('reports.receivable-list-pdf', $view);
            $this->assertEquals($expected, $pdfData['accounts']->mapWithKeys(fn ($a) => [$a->name => [$a->total_payments, $a->total_receivable]])->all());
            $html = view($view, $pdfData)->render();
            $this->assertStringContainsString('1.234,56', $html);
            $this->assertStringNotContainsString('Omitted zero account', $html);
            return true;
        })->andReturn($builder);
        try {
            $response = app(ReportController::class)->receivableListExport(app(CurrentApartment::class), $request, 'pdf');
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        } finally {
            if ($pdfPath && is_file($pdfPath)) unlink($pdfPath);
        }
    }
}
