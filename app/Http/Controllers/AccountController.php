<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Category;
use App\Models\Expense;
use App\Models\TenantAssignment;
use App\Models\Unit;
use App\Models\CashBox;
use App\Models\Payment;
use App\Support\CurrentApartment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * Parse Turkish number format (1.135,00 -> 1135.00)
     */
    private function parseTurkishNumber($value)
    {
        if (is_numeric($value)) {
            return round(floatval($value), 2);
        }

        if (is_string($value)) {
            // Türkçe format: binlik ayırıcı nokta, ondalık ayırıcı virgül
            // Önce binlik ayırıcıları kaldır, sonra virgülü noktaya çevir
            $value = str_replace('.', '', $value); // Binlik ayırıcıları kaldır
            $value = str_replace(',', '.', $value); // Ondalık ayırıcıyı noktaya çevir
            return round(floatval($value), 2);
        }

        return 0.0;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $filterSearch = $request->query('search');
        $filterType   = $request->query('type');
        $filterStatus = $request->query('status', 'active'); // Default: sadece aktifler

        $accounts = Account::query()
            ->with('unit')
            ->withSum(['transactions as debit_total' => function ($query) {
                $query->where('type', 'debit');
            }], 'amount')
            ->withSum(['transactions as credit_total' => function ($query) {
                $query->where('type', 'credit');
            }], 'amount')
            ->when($apartment, fn ($q) => $q->where('apartment_id', $apartment->id))
            ->when($filterSearch, fn ($q) => $q->where(function ($sub) use ($filterSearch) {
                $sub->where('accounts.name', 'like', '%' . $filterSearch . '%')
                    ->orWhereHas('unit', fn ($u) => $u->where('unit_no', 'like', '%' . $filterSearch . '%'));
            }))
            ->when($filterType,   fn ($q) => $q->where('type', $filterType))
            ->when($filterStatus === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filterStatus === 'inactive', fn ($q) => $q->where('is_active', false))
            // 'all' seçeneğinde filtre uygulanmaz
            ->orderByRaw('unit_id IS NULL, unit_id')
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        $filters = compact('filterSearch', 'filterType', 'filterStatus');

        // Hesapsız (account_id = null) ödemeleri hesapla
        $orphanPaymentsCount = 0;
        $orphanPaymentsTotal = 0;
        if ($apartment) {
            $orphanPayments = \App\Models\Payment::where('apartment_id', $apartment->id)
                ->whereNull('account_id')
                ->where('unallocated_amount', '>', 0)
                ->selectRaw('COUNT(*) as count, SUM(unallocated_amount) as total')
                ->first();
            $orphanPaymentsCount = $orphanPayments->count ?? 0;
            $orphanPaymentsTotal = $orphanPayments->total ?? 0;
        }

        return view('accounts.index', compact(
            'accounts', 'apartment', 'filters',
            'orphanPaymentsCount', 'orphanPaymentsTotal'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        $units = Unit::query()
            ->where('apartment_id', $apartment->id)
            ->orderBy('unit_no')
            ->get();

        $categories = Category::query()
            ->where('apartment_id', $apartment->id)
            ->orderBy('name')
            ->get();

        return view('accounts.create', compact('apartment', 'units', 'categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor($request->user());

        if (! $apartment && $currentApartment->hasAvailableFor($request->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        $validator = \Validator::make($request->all(), [
            'type' => ['required', Rule::in([Account::TYPE_OWNER, Account::TYPE_TENANT, Account::TYPE_SUPPLIER])],
            'unit_id' => [
                'required_if:type,'.Account::TYPE_OWNER.','.Account::TYPE_TENANT,
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where('apartment_id', $apartment->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'balance' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'move_in_date' => ['nullable', 'date'],
            'account_opening_date' => ['nullable', 'date'],
            'default_category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('apartment_id', $apartment->id)],
        ], [
            'unit_id.required_if' => 'Kat maliki ve kiracı hesapları için daire seçimi zorunludur.',
            'name.required'        => 'Ad Soyad / Ünvan zorunludur.',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasyon hatası',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        if ($validated['type'] === Account::TYPE_TENANT && empty($validated['unit_id'])) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kiracı hesabı için daire bağlantısı zorunludur.'
                ], 422);
            }
            return back()->withErrors(['unit_id' => 'Kiracı hesabı için daire bağlantısı zorunludur.'])->withInput();
        }

        if ($validated['type'] === Account::TYPE_TENANT && TenantAssignment::where('unit_id', $validated['unit_id'])->whereNull('move_out_date')->exists()) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu dairede aktif kiracı var. Önce mevcut kiracıya çıkış tarihi girin.'
                ], 422);
            }
            return back()->withErrors(['unit_id' => 'Bu dairede aktif kiracı var. Önce mevcut kiracıya çıkış tarihi girin.'])->withInput();
        }

        if ($validated['type'] === Account::TYPE_OWNER && empty($validated['unit_id'])) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kat maliki hesabı için daire bağlantısı zorunludur.'
                ], 422);
            }
            return back()->withErrors(['unit_id' => 'Kat maliki hesabı için daire bağlantısı zorunludur.'])->withInput();
        }

        if ($validated['type'] === Account::TYPE_OWNER && Account::where('unit_id', $validated['unit_id'])->where('type', Account::TYPE_OWNER)->where('is_active', true)->exists()) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu dairede aktif kat maliki var. Önce mevcut kat malikini pasife alın.'
                ], 422);
            }
            return back()->withErrors(['unit_id' => 'Bu dairede aktif kat maliki var. Önce mevcut kat malikini pasife alın.'])->withInput();
        }

        $account = DB::transaction(function () use ($apartment, $request, $validated) {
            $account = Account::create([
                'apartment_id' => $apartment->id,
                'unit_id' => in_array($validated['type'], [Account::TYPE_OWNER, Account::TYPE_TENANT], true) ? ($validated['unit_id'] ?? null) : null,
                'type' => $validated['type'],
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'balance' => $validated['balance'] ?? 0,
                'account_opening_date' => $validated['account_opening_date'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'default_category_id' => $validated['default_category_id'] ?? null,
            ]);

            if ($account->type === Account::TYPE_TENANT && $account->unit_id) {
                TenantAssignment::create([
                    'apartment_id' => $apartment->id,
                    'unit_id' => $account->unit_id,
                    'account_id' => $account->id,
                    'move_in_date' => $validated['account_opening_date'],
                ]);

                Unit::whereKey($account->unit_id)->update(['occupant_account_id' => $account->id]);
            }

            if ($account->type === Account::TYPE_OWNER && $account->unit_id) {
                Unit::whereKey($account->unit_id)->update(['owner_account_id' => $account->id]);
            }

            return $account;
        });

        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'account' => $account,
                'message' => 'Tedarikçi oluşturuldu.'
            ]);
        }

        return redirect()->route('accounts.index')->with('status', 'Hesap oluşturuldu.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $account = Account::query()
            ->with([
                'unit',
                'user',
                'activeTenantAssignment',
                'transactions' => fn ($query) => $query->orderBy('transaction_date')->orderBy('id'),
                'dues' => fn ($query) => $query->where('remaining_amount', '>', 0)->where('is_imported', false)->orderByDesc('due_date'),
                'payments' => fn ($query) => $query->where('unallocated_amount', '>', 0)->where('is_imported', false)->orderByDesc('payment_date'),
                'expenses' => fn ($query) => $query->where('is_paid', false)->orderBy('expense_date'),
            ])
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        // Hazır ordered transactions ile satır satır çalışan bakiye hesapla
        $transactions = $account->transactions->values();
        $running = 0;
        foreach ($transactions as $t) {
            $debit = $t->type === 'debit' ? $t->amount : 0;
            $credit = $t->type === 'credit' ? $t->amount : 0;
            $running += $debit - $credit;
            // runtime attribute for view
            $t->running_balance = $running;
        }

        // Görüntüleme: yeniden eskiye, son 5 kayıt
        $transactions = $transactions->reverse()->values()->take(5);

        // Ödemelere ait tahsisleri yükle ve transactionlara ekle
        $paymentIds = $transactions
            ->filter(fn($t) => ($t->transactionable_type ?? '') === Payment::class)
            ->pluck('transactionable_id')
            ->unique()
            ->values();

        // Ödemelere ait tahsisleri ve kasa hareketlerini yükle
        $cashUrlMap = [];
        if ($paymentIds->isNotEmpty()) {
            $payments = Payment::with(['allocations.due', 'allocations.expense', 'cashTransactions'])->whereIn('id', $paymentIds)->get()->keyBy('id');

            foreach ($transactions as $t) {
                if (($t->transactionable_type ?? '') === Payment::class && isset($payments[$t->transactionable_id])) {
                    $t->allocations = $payments[$t->transactionable_id]->allocations;
                    $cashTx = $payments[$t->transactionable_id]->cashTransactions->first();
                    if ($cashTx) {
                        $cashUrlMap[$t->id] = route('cash.show', $cashTx);
                    }
                } else {
                    $t->allocations = collect();
                }
            }
        } else {
            foreach ($transactions as $t) {
                $t->allocations = collect();
            }
        }

        // Giderlere ait kasa hareketlerini yükle
        $expenseIds = $transactions
            ->filter(fn($t) => ($t->transactionable_type ?? '') === Expense::class)
            ->pluck('transactionable_id')
            ->unique()
            ->values();

        if ($expenseIds->isNotEmpty()) {
            $expenses = Expense::with('cashTransactions')->whereIn('id', $expenseIds)->get()->keyBy('id');

            foreach ($transactions as $t) {
                if (($t->transactionable_type ?? '') === Expense::class && isset($expenses[$t->transactionable_id])) {
                    $cashTx = $expenses[$t->transactionable_id]->cashTransactions->first();
                    if ($cashTx) {
                        $cashUrlMap[$t->id] = route('cash.show', $cashTx);
                    }
                }
            }
        }

        $cashBoxes = CashBox::query()
            ->where('apartment_id', $account->apartment_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $importedDues = $account->dues()
            ->where('remaining_amount', '>', 0)
            ->where('is_imported', true)
            ->orderByDesc('due_date')
            ->get();

        $importedPayments = $account->payments()
            ->where('unallocated_amount', '>', 0)
            ->where('is_imported', true)
            ->orderByDesc('payment_date')
            ->get();

        $transferableAccounts = $account->unit_id
            ? \App\Models\Account::query()
                ->where('unit_id', $account->unit_id)
                ->where('id', '!=', $account->id)
                ->where('apartment_id', $account->apartment_id)
                ->orderBy('name')
                ->get(['id', 'name', 'type'])
            : collect();

        return view('accounts.show', compact('account', 'transactions', 'cashBoxes', 'importedDues', 'importedPayments', 'transferableAccounts', 'cashUrlMap'));
    }

    /**
     * Hesap ekstresi — tarih aralığı filtreli tüm hareketler.
     */
    public function statement(string $id, Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        $account = Account::query()
            ->with('unit')
            ->when($apartment, fn ($q) => $q->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        // Varsayılan tarih aralığı: bir önceki ayın 1. günü - bugün
        $dateFrom = $request->query('date_from') ?? now()->subMonth()->startOfMonth()->format('Y-m-d');
        $dateTo   = $request->query('date_to') ?? now()->format('Y-m-d');

        // Filtre öncesi bakiye (açılış bakiyesi)
        $openingBalance = 0;
        if ($dateFrom) {
            $opening = $account->transactions()
                ->where('transaction_date', '<', $dateFrom)
                ->orderBy('transaction_date')->orderBy('id')
                ->get();
            foreach ($opening as $t) {
                $openingBalance += $t->type === 'debit' ? $t->amount : -$t->amount;
            }
        }

        // Filtreli hareketler
        $query = $account->transactions()
            ->with(['transactionable'])
            ->when($dateFrom, fn ($q) => $q->where('transaction_date', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('transaction_date', '<=', $dateTo))
            ->orderBy('transaction_date')->orderBy('id');

        $transactions = $query->get();

        // Running balance
        $running = $openingBalance;
        foreach ($transactions as $t) {
            $running += $t->type === 'debit' ? $t->amount : -$t->amount;
            $t->running_balance = $running;
        }

        // Tahsisleri ve kasa hareketlerini yükle
        $paymentIds = $transactions
            ->filter(fn ($t) => ($t->transactionable_type ?? '') === Payment::class)
            ->pluck('transactionable_id')->unique()->values();

        $cashUrlMap = [];
        if ($paymentIds->isNotEmpty()) {
            $payments = Payment::with(['allocations.due', 'allocations.expense', 'cashTransactions'])->whereIn('id', $paymentIds)->get()->keyBy('id');
            foreach ($transactions as $t) {
                $t->allocations = (($t->transactionable_type ?? '') === Payment::class && isset($payments[$t->transactionable_id]))
                    ? $payments[$t->transactionable_id]->allocations
                    : collect();
                if (($t->transactionable_type ?? '') === Payment::class && isset($payments[$t->transactionable_id])) {
                    $cashTx = $payments[$t->transactionable_id]->cashTransactions->first();
                    if ($cashTx) {
                        $cashUrlMap[$t->id] = route('cash.show', $cashTx);
                    }
                }
            }
        } else {
            foreach ($transactions as $t) {
                $t->allocations = collect();
            }
        }

        // Giderlere ait kasa hareketlerini yükle
        $expenseIds = $transactions
            ->filter(fn ($t) => ($t->transactionable_type ?? '') === Expense::class)
            ->pluck('transactionable_id')->unique()->values();

        if ($expenseIds->isNotEmpty()) {
            $expenses = Expense::with('cashTransactions')->whereIn('id', $expenseIds)->get()->keyBy('id');
            foreach ($transactions as $t) {
                if (($t->transactionable_type ?? '') === Expense::class && isset($expenses[$t->transactionable_id])) {
                    $cashTx = $expenses[$t->transactionable_id]->cashTransactions->first();
                    if ($cashTx) {
                        $cashUrlMap[$t->id] = route('cash.show', $cashTx);
                    }
                }
            }
        }

        $closingBalance = $transactions->last()?->running_balance ?? $openingBalance;

        // İçe aktarılmış transaction sayısı
        $importedCount = $account->transactions()->where('is_imported', true)->count();

        return view('accounts.statement', compact(
            'account', 'transactions', 'openingBalance', 'closingBalance', 'dateFrom', 'dateTo', 'importedCount', 'cashUrlMap'
        ));
    }

    /**
     * Excel export for account statement (XLSX)
     */
    public function statementExport(string $id, Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        $account = Account::query()
            ->with('unit')
            ->when($apartment, fn ($q) => $q->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        $dateFrom = $request->query('date_from') ?? now()->subMonth()->startOfMonth()->format('Y-m-d');
        $dateTo   = $request->query('date_to') ?? now()->format('Y-m-d');

        $openingBalance = 0;
        $opening = $account->transactions()
            ->where('transaction_date', '<', $dateFrom)
            ->orderBy('transaction_date')->orderBy('id')
            ->get();
        foreach ($opening as $t) {
            $openingBalance += $t->type === 'debit' ? $t->amount : -$t->amount;
        }

        $transactions = $account->transactions()
            ->with(['transactionable'])
            ->where('transaction_date', '>=', $dateFrom)
            ->where('transaction_date', '<=', $dateTo)
            ->orderBy('transaction_date')->orderBy('id')
            ->get();

        $running = $openingBalance;
        foreach ($transactions as $t) {
            $running += $t->type === 'debit' ? $t->amount : -$t->amount;
            $t->running_balance = $running;
        }

        // PhpSpreadsheet ile XLSX oluştur
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Başlık
        $sheet->setCellValue('A1', 'Hesap Ekstresi');
        $sheet->setCellValue('A2', 'Hesap: ' . $account->name);
        $sheet->setCellValue('A3', 'Daire: ' . ($account->unit?->unit_no ?? '-'));
        $sheet->setCellValue('A4', 'Tarih Aralığı: ' . \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') . ' - ' . \Carbon\Carbon::parse($dateTo)->format('d.m.Y'));

        // Özet mesajı
        $closingBalance = $transactions->last()?->running_balance ?? $openingBalance;
        $summary = $closingBalance > 0
            ? 'Hesabın Toplam ' . number_format($closingBalance, 2, ',', '.') . ' TL borcu vardır'
            : ($closingBalance < 0
                ? 'Hesabın Toplam ' . number_format(abs($closingBalance), 2, ',', '.') . ' TL alacağı vardır'
                : 'Hesabın bakiyesi sıfırdır');
        $sheet->setCellValue('A5', $summary);
        $sheet->getStyle('A5')->getFont()->setBold(true);
        $sheet->getStyle('A5')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($closingBalance > 0 ? 'DC2626' : ($closingBalance < 0 ? '059669' : '64748B')));

        // Stil
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A4')->getFont()->setBold(true);

        // Sütun başlıkları
        $row = 6;
        $sheet->setCellValue('A' . $row, 'Tarih');
        $sheet->setCellValue('B' . $row, 'Referans');
        $sheet->setCellValue('C' . $row, 'Açıklama');
        $sheet->setCellValue('D' . $row, 'Borç');
        $sheet->setCellValue('E' . $row, 'Alacak');
        $sheet->setCellValue('F' . $row, 'Bakiye');
        $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':F' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');

        // Açılış bakiyesi
        $row = 7;
        $sheet->setCellValue('A' . $row, \Carbon\Carbon::parse($dateFrom)->format('d.m.Y'));
        $sheet->setCellValue('B' . $row, '—');
        $sheet->setCellValue('C' . $row, 'Dönem Açılış Bakiyesi');
        $sheet->setCellValue('D' . $row, $openingBalance > 0 ? $openingBalance : 0);
        $sheet->setCellValue('E' . $row, $openingBalance < 0 ? abs($openingBalance) : 0);
        $sheet->setCellValue('F' . $row, $openingBalance);
        $sheet->getStyle('A' . $row . ':F' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');

        // Hareketler
        $row = 8;
        foreach ($transactions as $t) {
            $desc = $t->description ?? '';
            if ($t->transactionable_type === Payment::class) {
                $desc = 'Ödeme' . ($t->description ? ' - ' . $t->description : '');
            } elseif ($t->transactionable_type === Due::class) {
                $desc = 'Aidat' . ($t->description ? ' - ' . $t->description : '');
            } elseif ($t->transactionable_type === Expense::class) {
                $desc = 'Gider' . ($t->description ? ' - ' . $t->description : '');
            }

            $sheet->setCellValue('A' . $row, $t->transaction_date->format('d.m.Y'));
            $sheet->setCellValue('B' . $row, $t->transactionable?->reference_number ?? '—');
            $sheet->setCellValue('C' . $row, $desc);
            $sheet->setCellValue('D' . $row, $t->type === 'debit' ? $t->amount : 0);
            $sheet->setCellValue('E' . $row, $t->type === 'credit' ? $t->amount : 0);
            $sheet->setCellValue('F' . $row, $t->running_balance);

            // Para formatı
            $sheet->getStyle('D' . $row . ':F' . $row)->getNumberFormat()->setFormatCode('#,##0.00 "TL"');

            $row++;
        }

        // Sütun genişlikleri
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(35);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);

        // Kenarlıklar
        $lastRow = $row - 1;
        $sheet->getStyle('A6:F' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Dosya oluştur
        $filename = 'ekstre_' . $account->id . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Download sample Excel template for import
     */
    public function statementImportSample()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Başlık
        $sheet->setCellValue('A1', 'Hesap Hareketleri İçeri Aktarma Şablonu');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        // Talimatlar
        $sheet->setCellValue('A3', 'Talimatlar:');
        $sheet->setCellValue('A4', '1. Tarih formatı: GG.AA.YYYY (örn: 01.01.2024)');
        $sheet->setCellValue('A5', '2. Borç veya Alacak sütunlarından biri dolu olmalı. İkisi birden pozitif olamaz. Boş hücreye 0 yazılabilir.');
        $sheet->setCellValue('A6', '3. Kategori sütunu opsiyoneldir. Borç satırları için kullanın (örn: Aidat, Demirbaş). Boş bırakılırsa "Aidat" atanır.');
        $sheet->getStyle('A3:A6')->getFont()->setSize(10);

        // Sütun başlıkları
        $row = 7;
        $sheet->setCellValue('A' . $row, 'Tarih');
        $sheet->setCellValue('B' . $row, 'Açıklama');
        $sheet->setCellValue('C' . $row, 'Borç');
        $sheet->setCellValue('D' . $row, 'Alacak');
        $sheet->setCellValue('E' . $row, 'Kategori');
        $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':E' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');

        // Örnek veri
        $row = 8;
        $sheet->setCellValue('A' . $row, '01.01.2024');
        $sheet->setCellValue('B' . $row, 'Ocak 2024 Aidatı');
        $sheet->setCellValue('C' . $row, 1000);
        $sheet->setCellValue('D' . $row, '');
        $sheet->setCellValue('E' . $row, 'Aidat');

        $row = 9;
        $sheet->setCellValue('A' . $row, '05.01.2024');
        $sheet->setCellValue('B' . $row, 'Ocak 2024 Demirbaş');
        $sheet->setCellValue('C' . $row, 200);
        $sheet->setCellValue('D' . $row, '');
        $sheet->setCellValue('E' . $row, 'Demirbaş');

        $row = 10;
        $sheet->setCellValue('A' . $row, '15.01.2024');
        $sheet->setCellValue('B' . $row, 'Devir öncesi tahsilat');
        $sheet->setCellValue('C' . $row, '');
        $sheet->setCellValue('D' . $row, 500);
        $sheet->setCellValue('E' . $row, '');

        // Para formatı
        $sheet->getStyle('C8:D10')->getNumberFormat()->setFormatCode('#,##0.00');

        // Sütun genişlikleri
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(20);

        // Kenarlıklar
        $sheet->getStyle('A7:E10')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Dosya oluştur
        $filename = 'hesap_hareketleri_sablon.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Import account transactions from Excel - preview before confirm
     */
    public function statementImport(Request $request, string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());
        $account = Account::query()
            ->when($apartment, fn ($q) => $q->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $file = $validated['file'];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Başlık satırını dinamik bul: A sütununda 'Tarih' yazan ilk satır
        $dataStartIndex = 1; // fallback: 2. satırdan başla
        foreach ($rows as $idx => $row) {
            $cell = trim((string)($row[0] ?? ''));
            if (mb_strtolower($cell) === 'tarih') {
                $dataStartIndex = $idx + 1;
                break;
            }
        }

        $transactions = [];
        $errors = [];
        for ($i = $dataStartIndex; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty($row[0])) continue; // Tarih boşsa atla

            $date = $row[0];
            $description = $row[1] ?? '';
            $debitRaw = $row[2] ?? 0;
            $creditRaw = $row[3] ?? 0;
            $categoryName = trim($row[4] ?? '');

            // Türkçe sayı formatını düzelt (1.135,00 -> 1135.00)
            $debit = $this->parseTurkishNumber($debitRaw);
            $credit = $this->parseTurkishNumber($creditRaw);

            // Tarih formatı kontrolü
            if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
                $errors[] = 'Satır ' . ($i + 1) . ': Tarih formatı hatalı. GG.AA.YYYY formatında olmalı.';
                continue;
            }

            // Borç/Alacak kontrolü - ikisi birden pozitif olamaz (0 değerleri kabul edilir)
            if ($debit > 0 && $credit > 0) {
                $errors[] = 'Satır ' . ($i + 1) . ': Borç ve Alacak sütunları aynı anda pozitif olamaz.';
                continue;
            }

            // Her ikisi de sıfırsa satırı sessizce atla (dış sistemlerden gelen 0/0,00/0.00 değerleri)
            if ($debit == 0 && $credit == 0) {
                continue;
            }

            if (empty($description)) {
                $errors[] = 'Satır ' . ($i + 1) . ': Açıklama zorunludur.';
                continue;
            }

            $transactions[] = [
                'date' => \Carbon\Carbon::createFromFormat('d.m.Y', $date)->format('Y-m-d'),
                'description' => $description,
                'debit' => floatval($debit),
                'credit' => floatval($credit),
                'category_name' => $categoryName,
            ];
        }

        if (empty($transactions) && empty($errors)) {
            return back()->with('error', 'İçeri aktarılacak veri bulunamadı.');
        }

        if (!empty($errors)) {
            return back()->with('error', 'Lütfen dosyayı kontrol edin:<br><br>' . implode('<br>', $errors));
        }

        // Verileri session'a kaydet
        session(['import_transactions' => $transactions, 'import_account_id' => $account->id]);

        return redirect()->route('accounts.statement.import-preview', $account->id);
    }

    /**
     * Show import preview page
     */
    public function statementImportPreview(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());
        $account = Account::query()
            ->when($apartment, fn ($q) => $q->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        $transactions = session('import_transactions', []);

        if (empty($transactions)) {
            return redirect()->route('accounts.statement', $account->id)->with('error', 'Önizleme verisi bulunamadı.');
        }

        return view('accounts.statement-import-preview', compact('account', 'transactions'));
    }

    /**
     * Confirm and import transactions
     */
    public function statementImportConfirm(Request $request, string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());
        $account = Account::query()
            ->when($apartment, fn ($q) => $q->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        $transactions = session('import_transactions', []);

        if (empty($transactions)) {
            return redirect()->route('accounts.statement', $account->id)->with('error', 'İçeri aktarılacak veri bulunamadı.');
        }

        // Devir Öncesi Kasası — yoksa otomatik oluştur
        $cashBox = CashBox::firstOrCreate(
            ['apartment_id' => $account->apartment_id, 'name' => 'Devir Öncesi Kasası'],
            ['is_active' => true, 'description' => 'Devir Öncesi Kasası — import işlemleri için otomatik oluşturuldu.']
        );

        // Kategorileri önceden yükle (performans)
        $categories = \App\Models\Category::where('apartment_id', $account->apartment_id)
            ->whereIn('type', [\App\Models\Category::TYPE_INCOME, \App\Models\Category::TYPE_ALL])
            ->where('is_active', true)
            ->get();
        $defaultCategory = $categories->firstWhere('name', 'Aidat') ?? $categories->first();

        $importedCount = 0;
        DB::transaction(function () use ($account, $transactions, $cashBox, $categories, $defaultCategory, &$importedCount) {
            foreach ($transactions as $t) {
                if ($t['debit'] > 0) {
                    // Borç → Devir Öncesi Aidat (Due)
                    $catName = $t['category_name'] ?? '';
                    $category = $catName
                        ? ($categories->first(fn($c) => mb_strtolower($c->name) === mb_strtolower($catName)) ?? $defaultCategory)
                        : $defaultCategory;

                    $due = \App\Models\Due::create([
                        'apartment_id'    => $account->apartment_id,
                        'account_id'      => $account->id,
                        'unit_id'         => $account->unit_id,
                        'category_id'     => $category?->id,
                        'amount'          => $t['debit'],
                        'remaining_amount' => $t['debit'],
                        'due_date'        => $t['date'],
                        'created_at_manual' => $t['date'],
                        'status'          => 'unpaid',
                        'description'     => $t['description'],
                        'is_imported'     => true,
                    ]);

                    AccountTransaction::create([
                        'apartment_id'         => $account->apartment_id,
                        'account_id'           => $account->id,
                        'transactionable_type' => \App\Models\Due::class,
                        'transactionable_id'   => $due->id,
                        'transaction_date'     => $t['date'],
                        'description'          => $t['description'],
                        'type'                 => 'debit',
                        'amount'               => $t['debit'],
                        'is_imported'          => true,
                    ]);
                } else {
                    // Alacak → Devir Öncesi Ödeme (Payment + CashTransaction)
                    $payment = \App\Models\Payment::create([
                        'apartment_id'      => $account->apartment_id,
                        'account_id'        => $account->id,
                        'amount'            => $t['credit'],
                        'unallocated_amount' => $t['credit'],
                        'payment_date'      => $t['date'],
                        'description'       => $t['description'],
                        'is_imported'       => true,
                    ]);

                    \App\Models\CashTransaction::create([
                        'cash_box_id'      => $cashBox->id,
                        'apartment_id'     => $account->apartment_id,
                        'account_id'       => $account->id,
                        'payment_id'       => $payment->id,
                        'type'             => 'income',
                        'amount'           => $t['credit'],
                        'description'      => $t['description'],
                        'transaction_date' => $t['date'],
                        'is_active'        => true,
                    ]);

                    AccountTransaction::create([
                        'apartment_id'         => $account->apartment_id,
                        'account_id'           => $account->id,
                        'transactionable_type' => \App\Models\Payment::class,
                        'transactionable_id'   => $payment->id,
                        'transaction_date'     => $t['date'],
                        'description'          => $t['description'],
                        'type'                 => 'credit',
                        'amount'               => $t['credit'],
                        'is_imported'          => true,
                    ]);
                }
                $importedCount++;
            }
        });

        // Session'ı temizle
        session()->forget(['import_transactions', 'import_account_id']);

        return redirect()->route('accounts.statement', $account->id)->with('status', $importedCount . ' adet hareket başarıyla Devir Öncesi olarak içeri aktarıldı.');
    }

    /**
     * Delete imported transactions
     */
    public function deleteLastImport(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());
        $account = Account::query()
            ->when($apartment, fn ($q) => $q->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        $deletedCount = 0;

        $skippedDues     = 0;
        $skippedPayments = 0;

        DB::transaction(function () use ($account, &$deletedCount, &$skippedDues, &$skippedPayments) {
            // Devir Öncesi Aidatlar — tahsis yapılmışsa atla
            $dues = \App\Models\Due::where('account_id', $account->id)->where('is_imported', true)->get();
            foreach ($dues as $due) {
                if ($due->allocations()->exists()) {
                    $skippedDues++;
                    continue;
                }
                AccountTransaction::where('transactionable_type', \App\Models\Due::class)
                    ->where('transactionable_id', $due->id)->delete();
                $due->delete();
                $deletedCount++;
            }

            // Devir Öncesi Ödemeler — tahsis yapılmışsa atla
            $payments = \App\Models\Payment::where('account_id', $account->id)->where('is_imported', true)->get();
            foreach ($payments as $payment) {
                if ($payment->allocations()->exists()) {
                    $skippedPayments++;
                    continue;
                }
                // CashTransaction'ı payment_id değil account_id+tarih+tutar ile bul
                // ama önce AccountTransaction üzerinden transactionable_id ile doğru kaydı sil
                AccountTransaction::where('transactionable_type', \App\Models\Payment::class)
                    ->where('transactionable_id', $payment->id)->delete();
                \App\Models\CashTransaction::where('cash_box_id', function ($q) use ($account) {
                    $q->select('id')->from('cash_boxes')
                      ->where('apartment_id', $account->apartment_id)
                      ->where('name', 'Devir Öncesi Kasası');
                })
                ->where('account_id', $account->id)
                ->whereDate('transaction_date', $payment->payment_date)
                ->where('amount', $payment->amount)
                ->forceDelete();
                $payment->delete();
                $deletedCount++;
            }

            // Geriye kalan eski ham is_imported transaction'lar (eski format)
            $oldCount = AccountTransaction::where('account_id', $account->id)->where('is_imported', true)->delete();
            $deletedCount += $oldCount;
        });

        if ($deletedCount === 0 && ($skippedDues + $skippedPayments) === 0) {
            return redirect()->route('accounts.statement', $account->id)->with('error', 'Silinecek içeri aktarılmış kayıt bulunamadı.');
        }

        $msg = $deletedCount . ' adet Devir Öncesi kayıt silindi.';
        if ($skippedDues + $skippedPayments > 0) {
            $msg .= ' ' . ($skippedDues + $skippedPayments) . ' adet kayıt tahsis/ödeme ilişkisi olduğu için atlandı.';
        }

        return redirect()->route('accounts.statement', $account->id)->with('status', $msg);
    }

    public function destroyTransaction(string $id, AccountTransaction $transaction, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());
        $account = Account::query()
            ->when($apartment, fn ($q) => $q->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        abort_unless($transaction->account_id === $account->id && $transaction->is_imported, 403);

        $transaction->delete();

        return redirect()->route('accounts.statement', $account->id)->with('status', 'Kayıt silindi.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $account = Account::query()
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        $units = Unit::query()
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->orderBy('unit_no')
            ->get();

        $categories = Category::query()
            ->where('apartment_id', $account->apartment_id)
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $account->default_category_id))
            ->where(fn ($q) => $q->where('type', Category::TYPE_ALL)->orWhere('type', Category::TYPE_EXPENSE))
            ->orderBy('name')
            ->get();

        return view('accounts.edit', compact('account', 'apartment', 'units', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor($request->user());

        if (! $apartment && $currentApartment->hasAvailableFor($request->user())) {
            return redirect()->route('current-apartment.select');
        }

        $account = Account::query()
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        // Type değiştirilemez - mevcut type'ı koru
        if ($request->has('type') && $request->input('type') !== $account->type) {
            return back()->withErrors(['type' => 'Hesap türü değiştirilemez.'])->withInput();
        }

        // Owner ve tenant için unit_id değiştirilemez
        if (in_array($account->type, [Account::TYPE_OWNER, Account::TYPE_TENANT])) {
            if ($request->has('unit_id') && (int) $request->input('unit_id') !== (int) $account->unit_id) {
                return back()->withErrors(['unit_id' => 'Kat maliki ve kiracı hesaplarında daire bağlantısı değiştirilemez.'])->withInput();
            }
        }

        $validated = $request->validate([
            'unit_id' => [
                'required_if:type,'.Account::TYPE_OWNER.','.Account::TYPE_TENANT,
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where('apartment_id', $account->apartment_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'balance' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'move_in_date' => ['nullable', 'date'],
            'account_opening_date' => ['nullable', 'date'],
            'default_category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('apartment_id', $account->apartment_id)],
        ], [
            'unit_id.required_if' => 'Kat maliki ve kiracı hesapları için daire seçimi zorunludur.',
            'name.required'        => 'Ad Soyad / Ünvan zorunludur.',
        ]);

        // Mevcut type'ı validated'a ekle
        $validated['type'] = $account->type;

        if ($validated['type'] === Account::TYPE_TENANT && empty($validated['unit_id'])) {
            return back()->withErrors(['unit_id' => 'Kiracı hesabı için daire bağlantısı zorunludur.'])->withInput();
        }

        if ($validated['type'] === Account::TYPE_TENANT && ! empty($validated['unit_id']) && $request->boolean('is_active')) {
            $hasOtherActiveTenant = TenantAssignment::where('unit_id', $validated['unit_id'])
                ->whereNull('move_out_date')
                ->where('account_id', '!=', $account->id)
                ->exists();

            if ($hasOtherActiveTenant) {
                return back()->withErrors(['unit_id' => 'Bu dairede aktif kiracı var. Önce mevcut kiracıya çıkış tarihi girin.'])->withInput();
            }
        }

        if ($validated['type'] === Account::TYPE_OWNER && empty($validated['unit_id'])) {
            return back()->withErrors(['unit_id' => 'Kat maliki hesabı için daire bağlantısı zorunludur.'])->withInput();
        }

        if ($validated['type'] === Account::TYPE_OWNER && ! empty($validated['unit_id'])) {
            $hasOtherOwner = Account::where('unit_id', $validated['unit_id'])
                ->where('type', Account::TYPE_OWNER)
                ->where('is_active', true)
                ->whereKeyNot($account->id)
                ->exists();

            if ($hasOtherOwner) {
                return back()->withErrors(['unit_id' => 'Bu dairede aktif kat maliki var. Önce mevcut kat malikini pasife alın veya düzenleyin.'])->withInput();
            }
        }

        DB::transaction(function () use ($account, $request, $validated) {
            $updateData = [
                'unit_id' => in_array($validated['type'], [Account::TYPE_OWNER, Account::TYPE_TENANT], true) ? ($validated['unit_id'] ?? null) : null,
                'type' => $validated['type'],
                'balance' => $validated['balance'] ?? 0,
                'account_opening_date' => $validated['account_opening_date'],
                'is_active' => $request->boolean('is_active'),
                'default_category_id' => $validated['default_category_id'] ?? null,
            ];

            $updateData['name']  = $validated['name'];
            $updateData['phone'] = $validated['phone'] ?? null;
            $updateData['email'] = $validated['email'] ?? null;

            $account->update($updateData);

            if ($account->type === Account::TYPE_TENANT && $account->unit_id) {
                $isActive = $request->boolean('is_active');

                if ($isActive) {
                    // Aktif — açık assignment güncelle veya oluştur
                    $assignment = TenantAssignment::firstOrNew([
                        'account_id' => $account->id,
                        'move_out_date' => null,
                    ]);

                    $assignment->fill([
                        'apartment_id' => $account->apartment_id,
                        'unit_id' => $account->unit_id,
                        'move_in_date' => $validated['account_opening_date'],
                    ])->save();

                    Unit::whereKey($account->unit_id)->update(['occupant_account_id' => $account->id]);
                } else {
                    // Pasif — açık assignment'ı kapat
                    TenantAssignment::where('account_id', $account->id)
                        ->whereNull('move_out_date')
                        ->update(['move_out_date' => now()->toDateString()]);

                    // Dairenin kiracısını temizle (sadece bu kiracıysa)
                    Unit::whereKey($account->unit_id)
                        ->where('occupant_account_id', $account->id)
                        ->update(['occupant_account_id' => null]);
                }
            }

            if ($account->type === Account::TYPE_OWNER && $account->unit_id) {
                Unit::whereKey($account->unit_id)->update(['owner_account_id' => $account->id]);
            }
        });

        return redirect()->route('accounts.show', $account)->with('status', 'Hesap güncellendi.');
    }

    /**
     * Kiracı kiralamasını sonlandır.
     */
    public function terminateTenancy(Request $request, Account $account, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor($request->user());
        abort_unless($apartment && $account->apartment_id === $apartment->id, 403);
        abort_unless($account->type === Account::TYPE_TENANT, 400);

        $validated = $request->validate([
            'termination_date' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($account, $validated, $apartment) {
            // Tenant assignment'ı güncelle
            $assignment = $account->activeTenantAssignment;
            if ($assignment) {
                $assignment->update(['move_out_date' => $validated['termination_date']]);
            }

            // Unit occupant'ı eski malike geri döndür (veya null yap)
            $unit = $account->unit;
            if ($unit) {
                $unit->update(['occupant_account_id' => $unit->owner_account_id]);
            }

            // Hesabı pasifleştir ve user bağını kopar
            $account->update([
                'is_active' => false,
                'user_id' => null,
                'account_end_date' => $validated['termination_date'],
            ]);

            // User'ı apartmandan çıkar
            if ($account->user) {
                $apartment->members()->detach($account->user_id);
            }
        });

        return redirect()->route('accounts.show', $account)->with('status', 'Kiralama sonlandırıldı.');
    }

    /**
     * Kat maliki malikliğini sonlandır ve yeni malik ata.
     */
    public function terminateOwnership(Request $request, string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor($request->user());
        $account = Account::where('apartment_id', $apartment->id)->findOrFail($id);
        abort_unless($account->type === Account::TYPE_OWNER, 400);

        $validated = $request->validate([
            'termination_date' => ['required', 'date'],
        ]);

        $newOwner = DB::transaction(function () use ($account, $validated, $apartment) {
            $unit = $account->unit;

            // Eski maliki pasifleştir
            $account->update([
                'is_active' => false,
                'user_id' => null,
                'account_end_date' => $validated['termination_date'],
            ]);

            // User bağını kopar
            if ($account->user) {
                $apartment->members()->detach($account->user_id);
            }

            // Yeni boş malik hesabı aç
            $newOwner = Account::create([
                'apartment_id' => $apartment->id,
                'unit_id' => $unit?->id,
                'type' => Account::TYPE_OWNER,
                'name' => $unit ? str_pad($unit->unit_no, 2, '0', STR_PAD_LEFT).'. Daire Kat Maliki' : 'Kat Maliki',
                'is_active' => true,
                'account_opening_date' => $validated['termination_date'],
            ]);

            if ($unit) {
                $unit->update(['owner_account_id' => $newOwner->id]);
            }

            // Dairenin occupant'ını da güncelle (eğer eski malik occupant ise)
            if ($unit && $unit->occupant_account_id === $account->id) {
                $unit->update(['occupant_account_id' => $newOwner->id]);
            }

            return $newOwner;
        });

        return redirect()->route('accounts.edit', $newOwner)->with('status', 'Maliklik sonlandırıldı ve yeni malik hesabı oluşturuldu.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $account = Account::query()
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        // Hesapta hareket var mı kontrol et
        $hasTransactions = $account->dues()->exists() ||
                          $account->transactions()->exists() ||
                          $account->expenses()->exists() ||
                          $account->payments()->exists() ||
                          $account->tenantAssignments()->exists();

        if ($hasTransactions) {
            return redirect()->back()->with('error', 'Bu hesapta hareket bulunduğu için silinemez. Önce ilişkili kayıtları silin.');
        }

        $account->delete();

        return redirect()->route('accounts.index')->with('status', 'Hesap silindi.');
    }
}
