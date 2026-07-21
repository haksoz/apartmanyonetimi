<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\CashTransaction;
use App\Models\Category;
use App\Models\Due;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Unit;
use App\Support\CurrentApartment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Spatie\LaravelPdf\Facades\Pdf;

class ReportController extends Controller
{
    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    private function getApartment(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        return $apartment;
    }

    private function applyHeaderStyle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range, string $bgColor = 'FF1a5276'): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFB0BEC5']]],
        ]);
    }

    private function applyCellStyle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range, bool $center = false): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE0E0E0']]],
            'alignment' => ['horizontal' => $center ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT],
        ]);
    }

    private function applyAccountFilter($query, string $filterAccount): void
    {
        match ($filterAccount) {
            'residents' => $query->whereNotNull('unit_id')->where(function ($q) {
                // Dairede aktif kiracı varsa, borç kaydı o kiracıya ait olmalı
                $q->whereExists(function ($sub) {
                    $sub->selectRaw(1)
                        ->from('tenant_assignments')
                        ->whereColumn('tenant_assignments.unit_id', 'dues.unit_id')
                        ->whereNull('tenant_assignments.move_out_date')
                        ->whereColumn('tenant_assignments.account_id', 'dues.account_id');
                })
                // Aktif kiracı yoksa, borç kaydı kat malikine ait olmalı
                ->orWhere(function ($sub) {
                    $sub->whereNotExists(function ($sub2) {
                        $sub2->selectRaw(1)
                            ->from('tenant_assignments')
                            ->whereColumn('tenant_assignments.unit_id', 'dues.unit_id')
                            ->whereNull('tenant_assignments.move_out_date');
                    })->whereHas('account', fn ($a) => $a->where('type', Account::TYPE_OWNER));
                });
            }),
            'owners' => $query->whereHas('account', fn ($q) => $q->where('type', Account::TYPE_OWNER)),
            'inactive' => $query->whereHas('account', fn ($q) => $q->where('is_active', false)),
            default => null,
        };
    }

    private function excelResponse(Spreadsheet $spreadsheet, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);
        $filename .= '-' . now()->format('Ymd-Hi');

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.xlsx"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function pdfResponse(string $viewName, array $data, string $filename): Response
    {
        $tempPath = sys_get_temp_dir() . '/' . uniqid('pdf_', true) . '.pdf';
        $filename .= '-' . now()->format('Ymd-Hi');

        Pdf::view($viewName, array_merge($data, ['pdfMode' => true]))
            ->format('a4')
            ->margins(10, 10, 10, 10)
            ->save($tempPath);

        return response()->file($tempPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
        ])->deleteFileAfterSend();
    }

    // -------------------------------------------------------------------------
    // INDEX
    // -------------------------------------------------------------------------

    public function index(CurrentApartment $currentApartment)
    {
        $apartment = $this->getApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        return view('reports.index', compact('apartment'));
    }

    // -------------------------------------------------------------------------
    // 1. GELİR-GİDER RAPORU
    // -------------------------------------------------------------------------

    public function incomeExpense(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $dateFrom = $request->input('date_from', now()->startOfYear()->format('Y-m-d'));
        $dateTo   = $request->input('date_to', now()->format('Y-m-d'));

        $id = $apartment->id;

        // Tahsilat (Payments)
        $payments = Payment::where('apartment_id', $id)
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->whereHas('account', fn($q) => $q->where('type', '!=', Account::TYPE_SUPPLIER))
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')->orderBy('month')
            ->pluck('total', 'month');

        // Giderler
        $expenses = Expense::where('apartment_id', $id)
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')->orderBy('month')
            ->pluck('total', 'month');

        // Tüm ayları birleştir
        $months = collect(array_keys($payments->toArray() + $expenses->toArray()))->sort()->values();

        $rows = $months->map(function ($month) use ($payments, $expenses) {
            $income  = (float) ($payments[$month] ?? 0);
            $expense = (float) ($expenses[$month] ?? 0);
            return [
                'month'   => Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y'),
                'income'  => $income,
                'expense' => $expense,
                'net'     => $income - $expense,
            ];
        });

        $totalIncome  = $rows->sum('income');
        $totalExpense = $rows->sum('expense');
        $totalNet     = $totalIncome - $totalExpense;

        // Kategori bazlı giderler
        $expenseByCategory = Expense::where('expenses.apartment_id', $id)
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->leftJoin('categories', fn($j) => $j->on('categories.id', '=', 'expenses.category_id')->whereNull('categories.deleted_at'))
            ->selectRaw("COALESCE(NULLIF(categories.name,''), NULLIF(expenses.category,''), 'Diğer') as cat, SUM(expenses.amount) as total")
            ->groupBy('cat')->orderByDesc('total')
            ->pluck('total', 'cat');

        return view('reports.income-expense', compact(
            'apartment', 'rows', 'totalIncome', 'totalExpense', 'totalNet',
            'expenseByCategory', 'dateFrom', 'dateTo'
        ));
    }

    public function incomeExpenseExport(CurrentApartment $currentApartment, Request $request, string $type)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $data = $this->incomeExpenseData($apartment, $request);

        if ($type === 'pdf') {
            return $this->pdfResponse('reports.income-expense', array_merge($data, ['apartment' => $apartment]), 'gelir-gider-raporu');
        }

        // Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Gelir-Gider');
        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', 'GELİR-GİDER RAPORU — ' . $apartment->name);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->fromArray(['Dönem', 'Tahsilat (₺)', 'Gider (₺)', 'Net (₺)'], null, 'A3');
        $this->applyHeaderStyle($sheet, 'A3:D3');

        $row = 4;
        foreach ($data['rows'] as $r) {
            $sheet->fromArray([$r['month'], $r['income'], $r['expense'], $r['net']], null, 'A' . $row);
            $row++;
        }
        $sheet->fromArray(['TOPLAM', $data['totalIncome'], $data['totalExpense'], $data['totalNet']], null, 'A' . $row);
        $this->applyHeaderStyle($sheet, "A{$row}:D{$row}", 'FF2e7d32');

        foreach (['A' => 20, 'B' => 18, 'C' => 18, 'D' => 18] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        return $this->excelResponse($spreadsheet, 'gelir-gider-raporu');
    }

    private function incomeExpenseData($apartment, Request $request): array
    {
        $dateFrom = $request->input('date_from', now()->startOfYear()->format('Y-m-d'));
        $dateTo   = $request->input('date_to', now()->format('Y-m-d'));
        $id       = $apartment->id;

        $payments = Payment::where('apartment_id', $id)
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->whereHas('account', fn($q) => $q->where('type', '!=', Account::TYPE_SUPPLIER))
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')->orderBy('month')->pluck('total', 'month');

        $expenses = Expense::where('apartment_id', $id)
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')->orderBy('month')->pluck('total', 'month');

        $months = collect(array_keys($payments->toArray() + $expenses->toArray()))->sort()->values();
        $rows = $months->map(function ($month) use ($payments, $expenses) {
            $income  = (float)($payments[$month] ?? 0);
            $expense = (float)($expenses[$month] ?? 0);
            return ['month' => Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y'), 'income' => $income, 'expense' => $expense, 'net' => $income - $expense];
        });

        return [
            'rows' => $rows,
            'totalIncome'  => $rows->sum('income'),
            'totalExpense' => $rows->sum('expense'),
            'totalNet'     => $rows->sum('income') - $rows->sum('expense'),
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
        ];
    }

    // -------------------------------------------------------------------------
    // 2. BORÇ LİSTESİ
    // -------------------------------------------------------------------------

    public function debtList(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $filterUnit      = $request->input('unit_id');
        $filterStatus    = $request->input('status', 'unpaid'); // unpaid | overdue | all
        $filterStartDate = $request->input('start_date');
        $filterEndDate   = $request->input('end_date');

        $today = now()->startOfDay();

        $query = Due::with(['account', 'unit', 'category'])
            ->where('apartment_id', $apartment->id)
            ->where('remaining_amount', '>', 0)
            ->when($filterUnit, fn($q) => $q->where('unit_id', $filterUnit))
            ->when($filterStartDate || $filterEndDate, function ($q) use ($filterStartDate, $filterEndDate) {
                $column = 'DATE(due_date)';
                if ($filterStartDate && $filterEndDate) {
                    $q->whereRaw("{$column} BETWEEN ? AND ?", [$filterStartDate, $filterEndDate]);
                } elseif ($filterStartDate) {
                    $q->whereRaw("{$column} >= ?", [$filterStartDate]);
                } elseif ($filterEndDate) {
                    $q->whereRaw("{$column} <= ?", [$filterEndDate]);
                }
            })
            ->when($filterStatus === 'unpaid', fn($q) => $q->where('due_date', '>=', $today))
            ->when($filterStatus === 'overdue', fn($q) => $q->where('due_date', '<', $today))
            ->orderBy('due_date');

        $dues  = $query->get();
        $units = Unit::where('apartment_id', $apartment->id)->orderBy('unit_no')->get();
        $total = $dues->sum('remaining_amount');

        return view('reports.debt-list', compact('apartment', 'dues', 'units', 'total', 'filterUnit', 'filterStatus', 'filterStartDate', 'filterEndDate'));
    }

    public function debtListExport(CurrentApartment $currentApartment, Request $request, string $type)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $today = now()->startOfDay();

        $dues  = Due::with(['account', 'unit', 'category'])
            ->where('apartment_id', $apartment->id)
            ->where('remaining_amount', '>', 0)
            ->when($request->input('unit_id'), fn($q) => $q->where('unit_id', $request->input('unit_id')))
            ->when($request->input('start_date') || $request->input('end_date'), function ($q) use ($request) {
                $start = $request->input('start_date');
                $end   = $request->input('end_date');
                $column = 'DATE(due_date)';
                if ($start && $end) {
                    $q->whereRaw("{$column} BETWEEN ? AND ?", [$start, $end]);
                } elseif ($start) {
                    $q->whereRaw("{$column} >= ?", [$start]);
                } elseif ($end) {
                    $q->whereRaw("{$column} <= ?", [$end]);
                }
            })
            ->when($request->input('status') === 'unpaid', fn($q) => $q->where('due_date', '>=', $today))
            ->when($request->input('status') === 'overdue', fn($q) => $q->where('due_date', '<', $today))
            ->orderBy('due_date')->get();

        $total = $dues->sum('remaining_amount');

        if ($type === 'pdf') {
            return $this->pdfResponse('reports.debt-list', ['apartment' => $apartment, 'dues' => $dues, 'total' => $total, 'units' => collect(), 'filterUnit' => null, 'filterStatus' => $request->input('status', 'unpaid'), 'filterStartDate' => $request->input('start_date'), 'filterEndDate' => $request->input('end_date')], 'borclar-listesi');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Borç Listesi');
        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'BORÇ LİSTESİ — ' . $apartment->name . ' — ' . now()->format('d.m.Y'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->fromArray(['Daire', 'Hesap Adı', 'Kategori', 'Dönem', 'Toplam (₺)', 'Kalan (₺)', 'Vade Tarihi'], null, 'A3');
        $this->applyHeaderStyle($sheet, 'A3:G3');

        $row = 4;
        foreach ($dues as $due) {
            $sheet->fromArray([
                $due->unit?->unit_no ?? '-',
                $due->account?->name ?? '-',
                $due->category?->name ?? '-',
                $due->period ?? '-',
                $due->amount,
                $due->remaining_amount,
                $due->due_date?->format('d.m.Y') ?? '-',
            ], null, 'A' . $row++);
        }
        $sheet->setCellValue('E' . $row, $dues->sum('amount'));
        $sheet->setCellValue('F' . $row, $total);
        $this->applyHeaderStyle($sheet, "A{$row}:G{$row}", 'FFc62828');
        foreach (['A' => 10, 'B' => 22, 'C' => 18, 'D' => 12, 'E' => 14, 'F' => 14, 'G' => 14] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        return $this->excelResponse($spreadsheet, 'borclar-listesi');
    }

    // -------------------------------------------------------------------------
    // 3. ALACAK LİSTESİ
    // -------------------------------------------------------------------------

    public function receivableList(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $filterAccountType = $request->input('account_type', Account::TYPE_SUPPLIER);

        $accounts = Account::with(['unit'])
            ->where('apartment_id', $apartment->id)
            ->where('type', $filterAccountType)
            ->get()
            ->map(function ($account) {
                $totalDues     = (float) $account->dues()->sum('amount');
                $paidDues      = (float) $account->dues()->where('remaining_amount', 0)->sum('amount');
                $totalPayments = (float) $account->payments()->sum('amount');
                $account->total_receivable = $totalPayments - $totalDues + ($totalDues - $paidDues);
                $account->total_dues       = $totalDues;
                $account->total_payments   = $totalPayments;
                return $account;
            })
            ->filter(fn($a) => $a->total_receivable > 0);

        return view('reports.receivable-list', compact('apartment', 'accounts', 'filterAccountType'));
    }

    public function receivableListExport(CurrentApartment $currentApartment, Request $request, string $type)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $filterAccountType = $request->input('account_type', Account::TYPE_SUPPLIER);
        $accounts = Account::with(['unit'])
            ->where('apartment_id', $apartment->id)
            ->where('type', $filterAccountType)
            ->get()
            ->map(function ($account) {
                $totalDues     = (float) $account->dues()->sum('amount');
                $paidDues      = (float) $account->dues()->where('remaining_amount', 0)->sum('amount');
                $totalPayments = (float) $account->payments()->sum('amount');
                $account->total_receivable = $totalPayments - $totalDues + ($totalDues - $paidDues);
                $account->total_dues       = $totalDues;
                $account->total_payments   = $totalPayments;
                return $account;
            })
            ->filter(fn($a) => $a->total_receivable > 0);

        if ($type === 'pdf') {
            return $this->pdfResponse('reports.receivable-list', ['apartment' => $apartment, 'accounts' => $accounts, 'filterAccountType' => $filterAccountType], 'alacak-listesi');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Alacak Listesi');
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', 'ALACAK LİSTESİ — ' . $apartment->name);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->fromArray(['Hesap Adı', 'Daire', 'Tür', 'Toplam Ödeme (₺)', 'Alacak (₺)'], null, 'A3');
        $this->applyHeaderStyle($sheet, 'A3:E3');
        $row = 4;
        foreach ($accounts as $account) {
            $sheet->fromArray([$account->name, $account->unit?->unit_no ?? '-', $account->type_label, $account->total_payments, $account->total_receivable], null, 'A' . $row++);
        }
        foreach (['A' => 25, 'B' => 10, 'C' => 14, 'D' => 18, 'E' => 16] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        return $this->excelResponse($spreadsheet, 'alacak-listesi');
    }

    // -------------------------------------------------------------------------
    // 4. CARİ EKSTRELER
    // -------------------------------------------------------------------------

    public function accountStatement(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $accounts  = Account::with('unit')
            ->where('accounts.apartment_id', $apartment->id)
            ->where('accounts.is_active', true)
            ->leftJoin('units', 'units.id', '=', 'accounts.unit_id')
            ->orderByRaw("CASE WHEN accounts.type = 'supplier' THEN 1 ELSE 0 END")
            ->orderByRaw('LENGTH(units.unit_no), units.unit_no')
            ->orderBy('accounts.name')
            ->select('accounts.*')
            ->get();
        $accountId = $request->input('account_id');
        $dateFrom  = $request->input('date_from', now()->startOfYear()->format('Y-m-d'));
        $dateTo    = $request->input('date_to', now()->format('Y-m-d'));

        $transactions = collect();
        $account = null;
        $runningBalance = 0;
        $openingBalance = 0;
        $totalDebit = 0;
        $totalCredit = 0;

        if ($accountId) {
            $account = Account::find($accountId);

            $openingTxs = AccountTransaction::where('account_id', $accountId)
                ->where('transaction_date', '<', $dateFrom)
                ->orderBy('transaction_date')->orderBy('id')
                ->get();
            foreach ($openingTxs as $t) {
                $openingBalance += $t->type === 'debit' ? -(float)$t->amount : (float)$t->amount;
            }

            $runningBalance = $openingBalance;
            $transactions = AccountTransaction::with('transactionable')
                ->where('account_id', $accountId)
                ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get()
                ->map(function ($tx) use (&$runningBalance) {
                    if ($tx->type === 'debit') {
                        $runningBalance -= (float)$tx->amount;
                    } else {
                        $runningBalance += (float)$tx->amount;
                    }
                    $tx->running_balance = $runningBalance;
                    return $tx;
                });

            $totalDebit  = $transactions->where('type', 'debit')->sum('amount');
            $totalCredit = $transactions->where('type', 'credit')->sum('amount');
        }

        return view('reports.account-statement', compact('apartment', 'accounts', 'account', 'transactions', 'accountId', 'dateFrom', 'dateTo', 'totalDebit', 'totalCredit', 'runningBalance', 'openingBalance'));
    }

    public function accountStatementExport(CurrentApartment $currentApartment, Request $request, string $type)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $accountId = $request->input('account_id');
        $dateFrom  = $request->input('date_from', now()->startOfYear()->format('Y-m-d'));
        $dateTo    = $request->input('date_to', now()->format('Y-m-d'));
        $account   = Account::with('unit')->find($accountId);

        $openingBalance = 0;
        $openingTxs = AccountTransaction::where('account_id', $accountId)
            ->where('transaction_date', '<', $dateFrom)
            ->orderBy('transaction_date')->orderBy('id')
            ->get();
        foreach ($openingTxs as $t) {
            $openingBalance += $t->type === 'debit' ? -(float)$t->amount : (float)$t->amount;
        }

        $runningBalance = $openingBalance;
        $transactions = AccountTransaction::with('transactionable')
            ->where('account_id', $accountId)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->orderBy('transaction_date')->orderBy('id')
            ->get()
            ->map(function ($tx) use (&$runningBalance) {
                if ($tx->type === 'debit') $runningBalance -= (float)$tx->amount;
                else $runningBalance += (float)$tx->amount;
                $tx->running_balance = $runningBalance;
                return $tx;
            });

        $totalDebit  = $transactions->where('type', 'debit')->sum('amount');
        $totalCredit = $transactions->where('type', 'credit')->sum('amount');
        $accounts    = collect();
        $pdfMode     = true;

        $summaryText = $runningBalance < 0
            ? 'Hesabın toplam ' . number_format(abs($runningBalance), 2, ',', '.') . ' TL borcu vardır.'
            : ($runningBalance > 0
                ? 'Hesabın toplam ' . number_format($runningBalance, 2, ',', '.') . ' TL alacağı vardır.'
                : 'Hesabın borcu yoktur.');
        $summaryColor = $runningBalance < 0 ? 'bg-red-50 border-red-200 text-red-700' : ($runningBalance > 0 ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-slate-50 border-slate-200 text-slate-600');

        if ($type === 'pdf') {
            $pdfSlug = $account ? preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(str_replace(
                ['ç','ğ','ı','ö','ş','ü','Ç','Ğ','İ','Ö','Ş','Ü'],
                ['c','g','i','o','s','u','c','g','i','o','s','u'],
                $account->name
            ))) : 'hesap';
            return $this->pdfResponse('reports.account-statement-pdf', compact('apartment', 'account', 'transactions', 'dateFrom', 'dateTo', 'totalDebit', 'totalCredit', 'runningBalance', 'openingBalance', 'summaryText'), 'cari-ekstre-' . $pdfSlug);
        }

        $unitNo   = $account?->unit?->unit_no ? 'Daire ' . $account->unit->unit_no . ' — ' : '';
        $tlFormat = '#,##0.00 "TL"';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Cari Ekstre');
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'CARİ EKSTRE — ' . $unitNo . ($account?->name ?? '-') . ' — ' . $apartment->name);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', 'Dönem: ' . \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') . ' – ' . \Carbon\Carbon::parse($dateTo)->format('d.m.Y'));
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $summaryText = $runningBalance < 0
            ? 'Hesabın toplam ' . number_format(abs($runningBalance), 2, ',', '.') . ' TL borcu vardır.'
            : ($runningBalance > 0
                ? 'Hesabın toplam ' . number_format($runningBalance, 2, ',', '.') . ' TL alacağı vardır.'
                : 'Hesabın borcu yoktur.');
        $sheet->mergeCells('A3:F3');
        $sheet->setCellValue('A3', $summaryText);
        $sheet->getStyle('A3')->getFont()->setBold(true);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $summaryRgb = $runningBalance < 0 ? 'FEE2E2' : ($runningBalance > 0 ? 'D1FAE5' : 'F1F5F9');
        $sheet->getStyle('A3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($summaryRgb);
        $sheet->fromArray(['Tarih', 'Tür', 'Açıklama', 'Borç (TL)', 'Alacak (TL)', 'Bakiye (TL)'], null, 'A4');
        $this->applyHeaderStyle($sheet, 'A4:F4');
        $row = 5;
        // Açılış bakiyesi satırı
        $sheet->fromArray([
            \Carbon\Carbon::parse($dateFrom)->format('d.m.Y'),
            'Açılış',
            'Dönem Açılış Bakiyesi',
            $openingBalance > 0 ? $openingBalance : 0,
            $openingBalance < 0 ? abs($openingBalance) : 0,
            $openingBalance,
        ], null, 'A' . $row);
        $sheet->getStyle('A' . $row . ':F' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
        $sheet->getStyle('D' . $row . ':F' . $row)->getNumberFormat()->setFormatCode($tlFormat);
        $row++;
        foreach ($transactions as $tx) {
            $sheet->fromArray([
                $tx->transaction_date?->format('d.m.Y'),
                $tx->type === 'debit' ? 'Borç' : 'Alacak',
                $tx->description ?? '-',
                $tx->type === 'debit' ? $tx->amount : 0,
                $tx->type === 'credit' ? $tx->amount : 0,
                $tx->running_balance,
            ], null, 'A' . $row);
            $sheet->getStyle('D' . $row . ':F' . $row)->getNumberFormat()->setFormatCode($tlFormat);
            $row++;
        }
        // Dip toplam satırı
        $dataStartRow = 5; // açılış satırı dahil veri başlangıcı
        $dataEndRow   = $row - 1;
        $sheet->fromArray([
            '',
            '',
            'TOPLAM',
            $totalDebit,
            $totalCredit,
            $runningBalance,
        ], null, 'A' . $row);
        $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':F' . $row)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F1F5F9');
        $sheet->getStyle('D' . $row . ':F' . $row)->getNumberFormat()->setFormatCode($tlFormat);
        $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

        foreach (['A' => 13, 'B' => 10, 'C' => 32, 'D' => 14, 'E' => 14, 'F' => 14] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $accountSlug = $account ? preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(str_replace(
            ['ç','ğ','ı','ö','ş','ü','Ç','Ğ','İ','Ö','Ş','Ü'],
            ['c','g','i','o','s','u','c','g','i','o','s','u'],
            $account->name
        ))) : 'hesap';

        return $this->excelResponse($spreadsheet, 'cari-ekstre-' . $accountSlug);
    }

    // -------------------------------------------------------------------------
    // 5. AİDAT TAHSİLAT RAPORU
    // -------------------------------------------------------------------------

    public function dueCollection(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $year     = (int)$request->input('year', now()->year);
        $months   = range(1, 12);

        $accounts = Account::where('accounts.apartment_id', $apartment->id)
            ->whereIn('accounts.type', [Account::TYPE_OWNER, Account::TYPE_TENANT])
            ->with('unit')
            ->whereHas('unit')
            ->join('units', 'units.id', '=', 'accounts.unit_id')
            ->orderByRaw('LENGTH(units.unit_no), units.unit_no')
            ->orderByRaw("CASE WHEN accounts.type = 'owner' THEN 0 WHEN accounts.type = 'tenant' THEN 1 ELSE 2 END")
            ->select('accounts.*')
            ->get();

        // Her hesap × ay için borç durumunu çek (tüm due_type'lar dahil)
        $dues = Due::where('apartment_id', $apartment->id)
            ->where(function ($q) use ($year) {
                $q->whereYear('period', $year)
                  ->orWhereRaw("YEAR(due_date) = ?", [$year]);
            })
            ->with(['unit', 'account'])
            ->get();

        // [account_id][month] = status
        $matrix = [];
        foreach ($dues as $due) {
            $accountId = $due->account_id;
            $month     = optional($due->period ? Carbon::parse($due->period) : $due->due_date)->month;
            if (!$accountId || !$month) continue;

            $existing = $matrix[$accountId][$month] ?? null;
            $status   = $due->computed_status;

            // paid > partial > overdue > pending
            $priority = ['paid' => 4, 'partial' => 3, 'overdue' => 2, 'pending' => 1];
            if (!$existing || ($priority[$status] ?? 0) > ($priority[$existing] ?? 0)) {
                $matrix[$accountId][$month] = $status;
            }
        }

        $monthNames = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];

        $availableYears = range(now()->year, max(now()->year - 5, 2020), -1);

        return view('reports.due-collection', compact('apartment', 'accounts', 'months', 'matrix', 'monthNames', 'year', 'availableYears'));
    }

    public function dueCollectionExport(CurrentApartment $currentApartment, Request $request, string $type)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $year     = (int)$request->input('year', now()->year);
        $months   = range(1, 12);

        $accounts = Account::where('accounts.apartment_id', $apartment->id)
            ->whereIn('accounts.type', [Account::TYPE_OWNER, Account::TYPE_TENANT])
            ->with('unit')
            ->whereHas('unit')
            ->join('units', 'units.id', '=', 'accounts.unit_id')
            ->orderByRaw('LENGTH(units.unit_no), units.unit_no')
            ->orderByRaw("CASE WHEN accounts.type = 'owner' THEN 0 WHEN accounts.type = 'tenant' THEN 1 ELSE 2 END")
            ->select('accounts.*')
            ->get();

        $dues = Due::where('apartment_id', $apartment->id)
            ->where(function ($q) use ($year) {
                $q->whereYear('period', $year)
                  ->orWhereRaw("YEAR(due_date) = ?", [$year]);
            })
            ->with(['unit', 'account'])->get();

        $matrix = [];
        foreach ($dues as $due) {
            $accountId = $due->account_id;
            $month     = optional($due->period ? Carbon::parse($due->period) : $due->due_date)->month;
            if (!$accountId || !$month) continue;
            $status   = $due->computed_status;
            $priority = ['paid' => 4, 'partial' => 3, 'overdue' => 2, 'pending' => 1];
            $existing = $matrix[$accountId][$month] ?? null;
            if (!$existing || ($priority[$status] ?? 0) > ($priority[$existing] ?? 0)) {
                $matrix[$accountId][$month] = $status;
            }
        }

        $monthNames = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];

        if ($type === 'pdf') {
            return $this->pdfResponse('reports.due-collection', compact('apartment', 'accounts', 'months', 'matrix', 'monthNames', 'year', 'availableYears'), 'aidat-tahsilat-raporu');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Aidat Tahsilat');
        $sheet->mergeCells('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(13) . '1');
        $sheet->setCellValue('A1', 'AİDAT TAHSİLAT RAPORU — ' . $apartment->name . ' — ' . $year);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $header = ['Daire / Hesap'];
        foreach ($monthNames as $mn) $header[] = $mn;
        $sheet->fromArray($header, null, 'A3');
        $this->applyHeaderStyle($sheet, 'A3:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(13) . '3');

        $row = 4;
        foreach ($accounts as $account) {
            $rowData = [$account->unit?->unit_no . ' - ' . $account->name];
            foreach ($months as $m) {
                $status = $matrix[$account->id][$m] ?? '-';
                $labels = ['paid' => 'Ödendi', 'partial' => 'Kısmi', 'overdue' => 'Gecikmeli', 'pending' => 'Bekliyor', '-' => '-'];
                $rowData[] = $labels[$status] ?? $status;
            }
            $sheet->fromArray($rowData, null, 'A' . $row);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(30);
        for ($i = 2; $i <= 13; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setWidth(9);
        }

        return $this->excelResponse($spreadsheet, 'aidat-tahsilat-raporu');
    }

    // -------------------------------------------------------------------------
    // 6. GECİKME RAPORU
    // -------------------------------------------------------------------------

    public function overdue(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $filterUnit = $request->input('unit_id');
        $filterAccount = $request->input('account_filter', 'all');
        $units = Unit::where('apartment_id', $apartment->id)->orderBy('unit_no')->get();

        $dues = Due::with(['account', 'unit', 'category'])
            ->where('apartment_id', $apartment->id)
            ->where('remaining_amount', '>', 0)
            ->where('due_date', '<', now())
            ->when($filterUnit, fn($q) => $q->where('unit_id', $filterUnit))
            ->tap(fn ($q) => $this->applyAccountFilter($q, $filterAccount))
            ->orderBy('due_date')
            ->get()
            ->map(function ($due) {
                $due->days_overdue = (int) now()->diffInDays($due->due_date, false) * -1;
                return $due;
            })
            ->sortBy(fn($due) => $due->unit?->unit_no, SORT_NATURAL, false)
            ->values();

        $totalOverdue = $dues->sum('remaining_amount');
        $avgDays      = $dues->count() ? round($dues->avg('days_overdue')) : 0;

        return view('reports.overdue', compact('apartment', 'dues', 'units', 'filterUnit', 'filterAccount', 'totalOverdue', 'avgDays'));
    }

    public function overdue2(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $filterUnit = $request->input('unit_id');
        $filterAccount = $request->input('account_filter', 'all');
        $units = Unit::where('apartment_id', $apartment->id)->orderBy('unit_no')->get();

        $dues = Due::with(['account', 'unit', 'category'])
            ->where('apartment_id', $apartment->id)
            ->where('remaining_amount', '>', 0)
            ->where('due_date', '<', now())
            ->when($filterUnit, fn($q) => $q->where('unit_id', $filterUnit))
            ->tap(fn ($q) => $this->applyAccountFilter($q, $filterAccount))
            ->orderBy('due_date')
            ->get()
            ->map(function ($due) {
                $due->days_overdue = (int) now()->diffInDays($due->due_date, false) * -1;
                return $due;
            });

        $groups = $dues
            ->groupBy(fn($due) => $due->account_id)
            ->map(function ($accountDues) {
                $first = $accountDues->first();
                $account = $first->account;

                return (object) [
                    'account' => $account,
                    'unit' => $first->unit,
                    'dues' => $accountDues,
                    'total_remaining' => $accountDues->sum('remaining_amount'),
                    'total_amount' => $accountDues->sum('amount'),
                    'avg_days' => $accountDues->count() ? round($accountDues->avg('days_overdue')) : 0,
                ];
            })
            ->sortBy(fn($group) => $group->unit?->unit_no, SORT_NATURAL, false)
            ->values();

        $totalOverdue = $groups->sum('total_remaining');
        $avgDays = $dues->count() ? round($dues->avg('days_overdue')) : 0;

        return view('reports.overdue2', compact('apartment', 'groups', 'units', 'filterUnit', 'filterAccount', 'totalOverdue', 'avgDays'));
    }

    public function overdueExport(CurrentApartment $currentApartment, Request $request, string $type)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $filterAccount = $request->input('account_filter', 'all');

        $dues = Due::with(['account', 'unit', 'category'])
            ->where('apartment_id', $apartment->id)
            ->where('remaining_amount', '>', 0)
            ->where('due_date', '<', now())
            ->when($request->input('unit_id'), fn($q) => $q->where('unit_id', $request->input('unit_id')))
            ->tap(fn ($q) => $this->applyAccountFilter($q, $filterAccount))
            ->orderBy('due_date')->get()
            ->map(function ($due) {
                $due->days_overdue = (int) now()->diffInDays($due->due_date, false) * -1;
                return $due;
            })
            ->sortBy(fn($due) => $due->unit?->unit_no, SORT_NATURAL, false)
            ->values();

        $totalOverdue = $dues->sum('remaining_amount');
        $avgDays      = $dues->count() ? round($dues->avg('days_overdue')) : 0;

        if ($type === 'pdf') {
            return $this->pdfResponse('reports.overdue', ['apartment' => $apartment, 'dues' => $dues, 'units' => collect(), 'filterUnit' => null, 'filterAccount' => $filterAccount, 'totalOverdue' => $totalOverdue, 'avgDays' => $avgDays], 'gecikme-raporu');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Gecikme Raporu');
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'GECİKME RAPORU — ' . $apartment->name . ' — ' . now()->format('d.m.Y'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->fromArray(['Daire', 'Hesap Adı', 'Kategori', 'Açıklama', 'Vade Tarihi', 'Gecikme (Gün)', 'Toplam (₺)', 'Kalan (₺)'], null, 'A3');
        $this->applyHeaderStyle($sheet, 'A3:H3');
        $row = 4;
        foreach ($dues as $due) {
            $sheet->fromArray([
                $due->unit?->unit_no ?? '-',
                $due->account?->name ?? '-',
                $due->category?->name ?? '-',
                $due->description ?? '-',
                $due->due_date?->format('d.m.Y') ?? '-',
                $due->days_overdue,
                $due->amount,
                $due->remaining_amount,
            ], null, 'A' . $row++);
        }
        $sheet->setCellValue('H' . $row, $totalOverdue);
        $this->applyHeaderStyle($sheet, "A{$row}:H{$row}", 'FFb71c1c');
        foreach (['A' => 10, 'B' => 22, 'C' => 16, 'D' => 28, 'E' => 14, 'F' => 16, 'G' => 14, 'H' => 14] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getStyle('D4:D' . ($row - 1))->getAlignment()->setWrapText(true);

        return $this->excelResponse($spreadsheet, 'gecikme-raporu');
    }

    public function overdue2Export(CurrentApartment $currentApartment, Request $request, string $type)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $filterUnit = $request->input('unit_id');
        $filterAccount = $request->input('account_filter', 'all');

        $tableTitleSuffix = match ($filterAccount) {
            'residents' => ' - Daire Sakinleri',
            'owners' => ' - Kat Malikleri',
            'inactive' => ' - Pasif Hesaplar',
            default => '',
        };
        $tableTitle = 'Genel Borç Listesi' . $tableTitleSuffix;

        $dues = Due::with(['account', 'unit', 'category'])
            ->where('apartment_id', $apartment->id)
            ->where('remaining_amount', '>', 0)
            ->where('due_date', '<', now())
            ->when($filterUnit, fn($q) => $q->where('unit_id', $filterUnit))
            ->tap(fn ($q) => $this->applyAccountFilter($q, $filterAccount))
            ->orderBy('due_date')
            ->get()
            ->map(function ($due) {
                $due->days_overdue = (int) now()->diffInDays($due->due_date, false) * -1;
                return $due;
            });

        $groups = $dues
            ->groupBy(fn($due) => $due->account_id)
            ->map(function ($accountDues) {
                $first = $accountDues->first();

                return (object) [
                    'account' => $first->account,
                    'unit' => $first->unit,
                    'dues' => $accountDues,
                    'total_remaining' => $accountDues->sum('remaining_amount'),
                ];
            })
            ->sortBy(fn($group) => $group->unit?->unit_no, SORT_NATURAL, false)
            ->values();

        $totalOverdue = $groups->sum('total_remaining');
        $avgDays = $dues->count() ? round($dues->avg('days_overdue')) : 0;

        if ($type === 'pdf') {
            return $this->pdfResponse('reports.debt-list-pdf', ['apartment' => $apartment, 'groups' => $groups, 'tableTitle' => $tableTitle, 'totalOverdue' => $totalOverdue], 'borclar-listesi');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Borç Listesi');
        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', $tableTitle . ' — ' . $apartment->name . ' — ' . now()->format('d.m.Y'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->fromArray(['Daire', 'Hesap Adı', 'Detaylar', 'Toplam Borç (₺)'], null, 'A3');
        $this->applyHeaderStyle($sheet, 'A3:D3');

        $row = 4;
        foreach ($groups as $group) {
            $details = [];
            foreach ($group->dues as $due) {
                $details[] = sprintf(
                    '%s | %s ₺ | %s',
                    $due->created_at_manual?->format('d.m.Y') ?? $due->created_at?->format('d.m.Y') ?? '-',
                    number_format($due->amount, 2, ',', '.'),
                    $due->description ?? '-'
                );
            }

            $sheet->fromArray([
                $group->unit?->unit_no ?? '-',
                $group->account?->name ?? '-',
                implode("\n", $details),
                $group->total_remaining,
            ], null, 'A' . $row);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('B' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('C' . $row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle('D' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $row++;
        }

        $sheet->setCellValue('D' . $row, $totalOverdue);
        $this->applyHeaderStyle($sheet, "A{$row}:D{$row}", 'FFb71c1c');
        $sheet->getStyle("D4:D{$row}")->getNumberFormat()->setFormatCode('#,##0.00 "₺"');

        foreach (['A' => 10, 'B' => 22, 'C' => 70, 'D' => 18] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        return $this->excelResponse($spreadsheet, 'borclar-listesi');
    }

    // -------------------------------------------------------------------------
    // 7. YILLIK FAALİYET RAPORU
    // -------------------------------------------------------------------------

    public function annualActivity(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $year = (int)$request->input('year', now()->year);
        $id   = $apartment->id;
        $availableYears = range(now()->year, max(now()->year - 5, 2020), -1);

        $totalDues    = (float) Due::where('apartment_id', $id)->whereYear('created_at', $year)->sum('amount');
        $collectedDues= (float) Due::where('apartment_id', $id)->whereYear('created_at', $year)->sum(DB::raw('amount - remaining_amount'));
        $pendingDues  = $totalDues - $collectedDues;

        $totalExpenses = (float) Expense::where('apartment_id', $id)->whereYear('expense_date', $year)->sum('amount');
        $paidExpenses  = (float) Expense::where('apartment_id', $id)->whereYear('expense_date', $year)->where('is_paid', true)->sum('amount');
        $unpaidExpenses= $totalExpenses - $paidExpenses;

        $cashIn  = (float) CashTransaction::where('apartment_id', $id)->whereYear('transaction_date', $year)->where('type', 'income')->sum('amount');
        $cashOut = (float) CashTransaction::where('apartment_id', $id)->whereYear('transaction_date', $year)->where('type', 'expense')->sum('amount');

        // Aylık karşılaştırma
        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'dues'     => (float) Due::where('apartment_id', $id)->whereYear('created_at', $year)->whereMonth('created_at', $m)->sum('amount'),
                'payments' => (float) Payment::where('apartment_id', $id)->whereYear('payment_date', $year)->whereMonth('payment_date', $m)->sum('amount'),
                'expenses' => (float) Expense::where('apartment_id', $id)->whereYear('expense_date', $year)->whereMonth('expense_date', $m)->sum('amount'),
            ];
        }

        $expenseByCategory = Expense::where('expenses.apartment_id', $id)
            ->whereYear('expense_date', $year)
            ->leftJoin('categories', fn($j) => $j->on('categories.id', '=', 'expenses.category_id')->whereNull('categories.deleted_at'))
            ->selectRaw("COALESCE(NULLIF(categories.name,''), NULLIF(expenses.category,''), 'Diğer') as cat, SUM(expenses.amount) as total")
            ->groupBy('cat')->orderByDesc('total')->pluck('total', 'cat');

        $monthNames = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

        return view('reports.annual-activity', compact(
            'apartment', 'year', 'availableYears',
            'totalDues', 'collectedDues', 'pendingDues',
            'totalExpenses', 'paidExpenses', 'unpaidExpenses',
            'cashIn', 'cashOut',
            'monthlyData', 'monthNames', 'expenseByCategory'
        ));
    }

    public function annualActivityExport(CurrentApartment $currentApartment, Request $request, string $type)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $year = (int)$request->input('year', now()->year);
        $id   = $apartment->id;
        $availableYears = [];

        $totalDues    = (float) Due::where('apartment_id', $id)->whereYear('created_at', $year)->sum('amount');
        $collectedDues= (float) Due::where('apartment_id', $id)->whereYear('created_at', $year)->sum(DB::raw('amount - remaining_amount'));
        $pendingDues  = $totalDues - $collectedDues;
        $totalExpenses = (float) Expense::where('apartment_id', $id)->whereYear('expense_date', $year)->sum('amount');
        $paidExpenses  = (float) Expense::where('apartment_id', $id)->whereYear('expense_date', $year)->where('is_paid', true)->sum('amount');
        $unpaidExpenses= $totalExpenses - $paidExpenses;
        $cashIn  = (float) CashTransaction::where('apartment_id', $id)->whereYear('transaction_date', $year)->where('type', 'income')->sum('amount');
        $cashOut = (float) CashTransaction::where('apartment_id', $id)->whereYear('transaction_date', $year)->where('type', 'expense')->sum('amount');
        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'dues'     => (float) Due::where('apartment_id', $id)->whereYear('created_at', $year)->whereMonth('created_at', $m)->sum('amount'),
                'payments' => (float) Payment::where('apartment_id', $id)->whereYear('payment_date', $year)->whereMonth('payment_date', $m)->sum('amount'),
                'expenses' => (float) Expense::where('apartment_id', $id)->whereYear('expense_date', $year)->whereMonth('expense_date', $m)->sum('amount'),
            ];
        }
        $expenseByCategory = Expense::where('expenses.apartment_id', $id)
            ->whereYear('expense_date', $year)
            ->leftJoin('categories', fn($j) => $j->on('categories.id', '=', 'expenses.category_id')->whereNull('categories.deleted_at'))
            ->selectRaw("COALESCE(NULLIF(categories.name,''), NULLIF(expenses.category,''), 'Diğer') as cat, SUM(expenses.amount) as total")
            ->groupBy('cat')->orderByDesc('total')->pluck('total', 'cat');
        $monthNames = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

        if ($type === 'pdf') {
            return $this->pdfResponse('reports.annual-activity', compact('apartment', 'year', 'availableYears', 'totalDues', 'collectedDues', 'pendingDues', 'totalExpenses', 'paidExpenses', 'unpaidExpenses', 'cashIn', 'cashOut', 'monthlyData', 'monthNames', 'expenseByCategory'), 'yillik-faaliyet-raporu');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Yıllık Faaliyet');
        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', 'YILLIK FAALİYET RAPORU — ' . $apartment->name . ' — ' . $year);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->fromArray(['Kalem', 'Tutar (₺)'], null, 'A3');
        $this->applyHeaderStyle($sheet, 'A3:B3');
        $summaryRows = [
            ['Toplam Tahakkuk Eden Aidat', $totalDues],
            ['Tahsil Edilen Aidat', $collectedDues],
            ['Tahsil Edilemeyen Aidat', $pendingDues],
            ['Toplam Gider', $totalExpenses],
            ['Ödenen Gider', $paidExpenses],
            ['Ödenmemiş Gider', $unpaidExpenses],
            ['Kasa Geliri', $cashIn],
            ['Kasa Gideri', $cashOut],
            ['Kasa Bakiyesi', $cashIn - $cashOut],
        ];
        $row = 4;
        foreach ($summaryRows as $sr) {
            $sheet->fromArray($sr, null, 'A' . $row++);
        }

        $sheet->mergeCells('A' . ($row + 1) . ':D' . ($row + 1));
        $sheet->setCellValue('A' . ($row + 1), 'AYLIK DETAY');
        $this->applyHeaderStyle($sheet, 'A' . ($row + 1) . ':D' . ($row + 1));
        $row += 2;
        $sheet->fromArray(['Ay', 'Tahakkuk (₺)', 'Tahsilat (₺)', 'Gider (₺)'], null, 'A' . $row);
        $this->applyHeaderStyle($sheet, "A{$row}:D{$row}");
        $row++;
        foreach ($monthlyData as $m => $mData) {
            $sheet->fromArray([$monthNames[$m], $mData['dues'], $mData['payments'], $mData['expenses']], null, 'A' . $row++);
        }
        foreach (['A' => 30, 'B' => 18, 'C' => 18, 'D' => 18] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        return $this->excelResponse($spreadsheet, 'yillik-faaliyet-raporu');
    }

    // -------------------------------------------------------------------------
    // 8. BÜTÇE RAPORU
    // -------------------------------------------------------------------------

    public function budget(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $year = (int)$request->input('year', now()->year);
        $id   = $apartment->id;
        $availableYears = range(now()->year, max(now()->year - 5, 2020), -1);

        $categories = Category::where('apartment_id', $id)
            ->where('is_active', true)
            ->whereIn('type', [Category::TYPE_EXPENSE, Category::TYPE_ALL])
            ->get();

        $rows = $categories->map(function ($cat) use ($id, $year) {
            $actual = (float) Expense::where('apartment_id', $id)
                ->where('category_id', $cat->id)
                ->whereYear('expense_date', $year)
                ->sum('amount');
            return [
                'category'   => $cat->name,
                'budget'     => 0, // Bütçe sistemi eklenebilir; şimdilik 0
                'actual'     => $actual,
                'variance'   => 0 - $actual,
                'pct'        => 0,
            ];
        })->filter(fn($r) => $r['actual'] > 0);

        // Kategorisiz giderler
        $uncategorized = (float) Expense::where('apartment_id', $id)
            ->whereNull('category_id')
            ->whereYear('expense_date', $year)
            ->sum('amount');
        if ($uncategorized > 0) {
            $rows->push(['category' => 'Diğer / Kategorisiz', 'budget' => 0, 'actual' => $uncategorized, 'variance' => -$uncategorized, 'pct' => 0]);
        }

        $totalActual = $rows->sum('actual');

        return view('reports.budget', compact('apartment', 'year', 'availableYears', 'rows', 'totalActual'));
    }

    public function budgetExport(CurrentApartment $currentApartment, Request $request, string $type)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $year = (int)$request->input('year', now()->year);
        $id   = $apartment->id;
        $availableYears = [];

        $categories = Category::where('apartment_id', $id)->where('is_active', true)->whereIn('type', [Category::TYPE_EXPENSE, Category::TYPE_ALL])->get();
        $rows = $categories->map(function ($cat) use ($id, $year) {
            $actual = (float) Expense::where('apartment_id', $id)->where('category_id', $cat->id)->whereYear('expense_date', $year)->sum('amount');
            return ['category' => $cat->name, 'budget' => 0, 'actual' => $actual, 'variance' => -$actual, 'pct' => 0];
        })->filter(fn($r) => $r['actual'] > 0);
        $uncategorized = (float) Expense::where('apartment_id', $id)->whereNull('category_id')->whereYear('expense_date', $year)->sum('amount');
        if ($uncategorized > 0) $rows->push(['category' => 'Diğer / Kategorisiz', 'budget' => 0, 'actual' => $uncategorized, 'variance' => -$uncategorized, 'pct' => 0]);
        $totalActual = $rows->sum('actual');

        if ($type === 'pdf') {
            return $this->pdfResponse('reports.budget', compact('apartment', 'year', 'availableYears', 'rows', 'totalActual'), 'butce-raporu');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Bütçe Raporu');
        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', 'BÜTÇE RAPORU — ' . $apartment->name . ' — ' . $year);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->fromArray(['Kategori', 'Bütçe (₺)', 'Gerçekleşen (₺)', 'Fark (₺)'], null, 'A3');
        $this->applyHeaderStyle($sheet, 'A3:D3');
        $row = 4;
        foreach ($rows as $r) {
            $sheet->fromArray([$r['category'], $r['budget'], $r['actual'], $r['variance']], null, 'A' . $row++);
        }
        $sheet->fromArray(['TOPLAM', 0, $totalActual, -$totalActual], null, 'A' . $row);
        $this->applyHeaderStyle($sheet, "A{$row}:D{$row}", 'FF37474f');
        foreach (['A' => 28, 'B' => 16, 'C' => 18, 'D' => 14] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        return $this->excelResponse($spreadsheet, 'butce-raporu');
    }

    // -------------------------------------------------------------------------
    // 9. AYLIK AİDAT PANO TABLOSU
    // -------------------------------------------------------------------------

    public function monthlyBoard(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $month = $request->input('month', now()->format('Y-m'));
        $id    = $apartment->id;
        $typeFilter   = $request->input('type_filter', 'resident');
        $statusFilter = $request->input('status_filter', 'active');
        $showAccountType = $request->boolean('show_account_type', false);

        $accountsQuery = Account::where('accounts.apartment_id', $id)
            ->with('unit')
            ->whereHas('unit')
            ->join('units', 'units.id', '=', 'accounts.unit_id')
            ->orderByRaw('LENGTH(units.unit_no), units.unit_no')
            ->orderByRaw("CASE WHEN accounts.type = 'owner' THEN 0 WHEN accounts.type = 'tenant' THEN 1 ELSE 2 END")
            ->select('accounts.*');

        if ($typeFilter === 'owner') {
            $accountsQuery->where('accounts.type', Account::TYPE_OWNER);
        } elseif ($typeFilter === 'tenant') {
            $accountsQuery->where('accounts.type', Account::TYPE_TENANT);
        } else {
            $accountsQuery->whereIn('accounts.type', [Account::TYPE_OWNER, Account::TYPE_TENANT]);
        }

        if ($statusFilter === 'active') {
            $accountsQuery->where('accounts.is_active', true)->whereNull('accounts.deleted_at');
        } elseif ($statusFilter === 'inactive') {
            $accountsQuery->where(function ($q) {
                $q->where('accounts.is_active', false)->orWhereNotNull('accounts.deleted_at');
            });
        } else {
            $accountsQuery->withTrashed();
        }

        $accounts = $accountsQuery->get();

        if ($typeFilter === 'resident') {
            $accounts = $accounts->groupBy('unit_id')->map(function ($group) {
                return $group->firstWhere('type', Account::TYPE_TENANT)
                    ?? $group->firstWhere('type', Account::TYPE_OWNER);
            })->values();
        }

        // Parse month
        try {
            $parsedMonth = Carbon::createFromFormat('Y-m', $month);
        } catch (\Exception $e) {
            $parsedMonth = now();
        }
        $selectedMonthStr = $parsedMonth->format('Y-m');

        // Seçili ay due'ları + tüm açık geçmiş due'ları
        $allDues = Due::with(['account', 'unit', 'allocations'])
            ->where('apartment_id', $id)
            ->where(function ($q) use ($selectedMonthStr, $parsedMonth) {
                $q->where('period', 'like', $selectedMonthStr . '%')
                  ->orWhere(function ($q2) use ($parsedMonth) {
                      $q2->whereNull('period')
                         ->whereYear('due_date', $parsedMonth->year)
                         ->whereMonth('due_date', $parsedMonth->month);
                  });
            })
            ->orWhere(function ($q) {
                $q->where('remaining_amount', '>', 0);
            })
            ->get();

        $getDueMonth = fn($due) => $due->period
            ? Carbon::parse($due->period)->format('Y-m')
            : $due->due_date?->format('Y-m');

        $selectedDues = $allDues->filter(fn($due) => $getDueMonth($due) === $selectedMonthStr);
        $pastDues     = $allDues->filter(fn($due) => $getDueMonth($due) < $selectedMonthStr);

        $selectedByAccount = $selectedDues->groupBy('account_id');
        $pastByAccount     = $pastDues->groupBy('account_id');

        $accountData = [];
        foreach ($accounts as $account) {
            $sel = $selectedByAccount[$account->id] ?? collect();
            $past = $pastByAccount[$account->id] ?? collect();

            $pastRemaining    = (float) $past->sum('remaining_amount');
            $selectedAmount   = (float) $sel->sum('amount');
            $selectedRemaining = (float) $sel->sum('remaining_amount');
            $paid             = (float) $sel->sum('allocated_amount');

            $accountData[$account->id] = [
                'pastRemaining'    => $pastRemaining,
                'selectedAmount'   => $selectedAmount,
                'paid'             => $paid,
                'remaining'        => $pastRemaining + $selectedRemaining,
            ];
        }

        $categoryList = Category::where('apartment_id', $id)->where('is_active', true)->whereIn('type', [Category::TYPE_INCOME, Category::TYPE_ALL])->get();

        // Tüm aylar için selector
        $monthOptions = collect();
        for ($i = 11; $i >= -2; $i--) {
            $monthOptions->push(now()->subMonths($i)->format('Y-m'));
        }

        return view('reports.monthly-board', compact(
            'apartment', 'accounts', 'month', 'parsedMonth', 'selectedMonthStr',
            'accountData', 'categoryList', 'monthOptions', 'typeFilter', 'statusFilter', 'showAccountType'
        ));
    }

    public function monthlyBoardExport(CurrentApartment $currentApartment, Request $request, string $type)
    {
        $apartment = $this->getApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $month = $request->input('month', now()->format('Y-m'));
        $id    = $apartment->id;
        $typeFilter   = $request->input('type_filter', 'resident');
        $statusFilter = $request->input('status_filter', 'active');
        $showAccountType = $request->boolean('show_account_type', false);
        try { $parsedMonth = Carbon::createFromFormat('Y-m', $month); } catch (\Exception $e) { $parsedMonth = now(); }
        $selectedMonthStr = $parsedMonth->format('Y-m');

        $accountsQuery = Account::where('accounts.apartment_id', $id)
            ->with('unit')
            ->whereHas('unit')
            ->join('units', 'units.id', '=', 'accounts.unit_id')
            ->orderByRaw('LENGTH(units.unit_no), units.unit_no')
            ->orderByRaw("CASE WHEN accounts.type = 'owner' THEN 0 WHEN accounts.type = 'tenant' THEN 1 ELSE 2 END")
            ->select('accounts.*');

        if ($typeFilter === 'owner') {
            $accountsQuery->where('accounts.type', Account::TYPE_OWNER);
        } elseif ($typeFilter === 'tenant') {
            $accountsQuery->where('accounts.type', Account::TYPE_TENANT);
        } else {
            $accountsQuery->whereIn('accounts.type', [Account::TYPE_OWNER, Account::TYPE_TENANT]);
        }

        if ($statusFilter === 'active') {
            $accountsQuery->where('accounts.is_active', true)->whereNull('accounts.deleted_at');
        } elseif ($statusFilter === 'inactive') {
            $accountsQuery->where(function ($q) {
                $q->where('accounts.is_active', false)->orWhereNotNull('accounts.deleted_at');
            });
        } else {
            $accountsQuery->withTrashed();
        }

        $accounts = $accountsQuery->get();

        if ($typeFilter === 'resident') {
            $accounts = $accounts->groupBy('unit_id')->map(function ($group) {
                return $group->firstWhere('type', Account::TYPE_TENANT)
                    ?? $group->firstWhere('type', Account::TYPE_OWNER);
            })->values();
        }

        $allDues = Due::with(['account', 'unit', 'allocations'])
            ->where('apartment_id', $id)
            ->where(function ($q) use ($selectedMonthStr, $parsedMonth) {
                $q->where('period', 'like', $selectedMonthStr . '%')
                  ->orWhere(function ($q2) use ($parsedMonth) {
                      $q2->whereNull('period')
                         ->whereYear('due_date', $parsedMonth->year)
                         ->whereMonth('due_date', $parsedMonth->month);
                  });
            })
            ->orWhere(function ($q) {
                $q->where('remaining_amount', '>', 0);
            })
            ->get();

        $getDueMonth = fn($due) => $due->period
            ? Carbon::parse($due->period)->format('Y-m')
            : $due->due_date?->format('Y-m');

        $selectedDues = $allDues->filter(fn($due) => $getDueMonth($due) === $selectedMonthStr);
        $pastDues     = $allDues->filter(fn($due) => $getDueMonth($due) < $selectedMonthStr);

        $selectedByAccount = $selectedDues->groupBy('account_id');
        $pastByAccount     = $pastDues->groupBy('account_id');

        $accountData = [];
        foreach ($accounts as $account) {
            $sel = $selectedByAccount[$account->id] ?? collect();
            $past = $pastByAccount[$account->id] ?? collect();

            $pastRemaining    = (float) $past->sum('remaining_amount');
            $selectedAmount   = (float) $sel->sum('amount');
            $selectedRemaining = (float) $sel->sum('remaining_amount');
            $paid             = (float) $sel->sum('allocated_amount');

            $accountData[$account->id] = [
                'pastRemaining'    => $pastRemaining,
                'selectedAmount'   => $selectedAmount,
                'paid'             => $paid,
                'remaining'        => $pastRemaining + $selectedRemaining,
            ];
        }

        $categoryList = Category::where('apartment_id', $id)->where('is_active', true)->whereIn('type', [Category::TYPE_INCOME, Category::TYPE_ALL])->get();
        $monthOptions = collect();
        $trMonths = [1=>'Ocak',2=>'Şubat',3=>'Mart',4=>'Nisan',5=>'Mayıs',6=>'Haziran',7=>'Temmuz',8=>'Ağustos',9=>'Eylül',10=>'Ekim',11=>'Kasım',12=>'Aralık'];

        $title = $typeFilter === 'resident'
            ? 'AYLIK AİDAT TABLOSU — ' . $apartment->name . ' Daire Sakinleri — ' . $trMonths[$parsedMonth->month] . ' ' . $parsedMonth->year
            : 'AYLIK AİDAT PANO TABLOSU — ' . $apartment->name . ' — ' . $trMonths[$parsedMonth->month] . ' ' . $parsedMonth->year;

        if ($type === 'pdf') {
            return $this->pdfResponse('reports.monthly-board-pdf', compact(
                'apartment', 'accounts', 'month', 'parsedMonth', 'selectedMonthStr',
                'accountData', 'categoryList', 'monthOptions', 'typeFilter', 'statusFilter', 'title', 'trMonths', 'showAccountType'
            ), 'aylik-aidat-pano-tablosu');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Aidat Pano');
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $header = ['Daire No', 'Hesap Adı', 'Geçmiş Borç (₺)', $trMonths[$parsedMonth->month] . ' Borç (₺)', 'Ödenen (₺)', 'Kalan (₺)'];
        $sheet->fromArray($header, null, 'A3');
        $this->applyHeaderStyle($sheet, 'A3:F3');
        $row = 4;
        foreach ($accounts as $account) {
            $data = $accountData[$account->id];
            $rowData = [
                $account->unit?->unit_no,
                $account->name . ($showAccountType && $account->type === 'owner' ? ' (Kat Maliki)' : ($showAccountType && $account->type === 'tenant' ? ' (Kiracı)' : '')),
                $data['pastRemaining'],
                $data['selectedAmount'],
                $data['paid'],
                $data['remaining'],
            ];
            $sheet->fromArray($rowData, null, 'A' . $row++);
        }
        if ($row > 4) {
            $sheet->getStyle('C4:F' . ($row - 1))
                ->getNumberFormat()
                ->setFormatCode('#,##0.00 "₺"');
            $sheet->getStyle('A4:A' . ($row - 1))
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        foreach (['A' => 10, 'B' => 24, 'C' => 16, 'D' => 16, 'E' => 16, 'F' => 16] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        return $this->excelResponse($spreadsheet, 'aylik-aidat-pano-tablosu');
    }
}
