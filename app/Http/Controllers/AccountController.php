<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\CashTransaction;
use App\Models\Category;
use App\Models\Due;
use App\Models\Expense;
use App\Models\TenantAssignment;
use App\Models\Unit;
use App\Models\CashBox;
use App\Models\Payment;
use App\Models\PaymentAllocation;
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
        $sortBy       = $request->query('sort', 'unit_no');
        $sortDir      = $request->query('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['name', 'type', 'debit_total', 'credit_total', 'balance', 'unit_no'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'name';
        }

        $accounts = Account::query()
            ->with('unit')
            ->leftJoin('units', 'units.id', '=', 'accounts.unit_id')
            ->select('accounts.*')
            ->withSum(['transactions as debit_total' => function ($query) {
                $query->where('type', 'debit');
            }], 'amount')
            ->withSum(['transactions as credit_total' => function ($query) {
                $query->where('type', 'credit');
            }], 'amount')
            ->when($apartment, fn ($q) => $q->where('accounts.apartment_id', $apartment->id))
            ->when($filterSearch, fn ($q) => $q->where(function ($sub) use ($filterSearch) {
                $sub->where('accounts.name', 'like', '%' . $filterSearch . '%')
                    ->orWhere('units.unit_no', 'like', '%' . $filterSearch . '%');
            }))
            ->where('accounts.is_hidden', false)
            ->when($filterType,   fn ($q) => $q->where('accounts.type', $filterType))
            ->when($filterStatus === 'active', fn ($q) => $q->where('accounts.is_active', true))
            ->when($filterStatus === 'inactive', fn ($q) => $q->where('accounts.is_active', false))
            ->when($sortBy === 'balance', fn ($q) => $q->orderByRaw("(credit_total - debit_total) {$sortDir}"))
            ->when($sortBy === 'unit_no', fn ($q) => $q->orderByRaw("units.unit_no IS NULL {$sortDir}, units.unit_no {$sortDir}"))
            ->when(!in_array($sortBy, ['balance', 'unit_no']), fn ($q) => $q->orderBy($sortBy, $sortDir))
            ->paginate(25)->withQueryString();

        $filters = compact('filterSearch', 'filterType', 'filterStatus', 'sortBy', 'sortDir');

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

        $isOwner = $apartment && $this->isOwnerOf($apartment);

        // Check if there are any imported account transactions
        $hasImported = $apartment ? \App\Models\AccountTransaction::where('apartment_id', $apartment->id)
            ->where('is_imported', true)
            ->exists() : false;

        return view('accounts.index', compact(
            'accounts', 'apartment', 'filters',
            'orphanPaymentsCount', 'orphanPaymentsTotal', 'isOwner', 'hasImported'
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

        // Görüntüleme: yeniden eskiye, son 8 kayıt
        $transactions = $transactions->reverse()->values()->take(8);

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

        $unitIds = collect([$account->unit_id])
            ->merge($account->dues->pluck('unit_id'))
            ->filter()
            ->unique()
            ->values();

        $transferableAccounts = $unitIds->isNotEmpty()
            ? \App\Models\Account::query()
                ->where('apartment_id', $account->apartment_id)
                ->whereIn('unit_id', $unitIds)
                ->where('id', '!=', $account->id)
                ->whereIn('type', [\App\Models\Account::TYPE_OWNER, \App\Models\Account::TYPE_TENANT])
                ->orderBy('name')
                ->get(['id', 'name', 'type'])
            : collect();

        \Illuminate\Support\Facades\Log::info('Account transfer diagnostics', [
            'account_id' => $account->id,
            'account_type' => $account->type,
            'account_unit_id' => $account->unit_id,
            'account_is_active' => $account->is_active,
            'dues_count' => $account->dues->count(),
            'imported_dues_count' => $importedDues->count(),
            'unit_ids' => $unitIds->toArray(),
            'transferable_accounts_count' => $transferableAccounts->count(),
        ]);

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
                AccountTransaction::where('transactionable_type', \App\Models\Payment::class)
                    ->where('transactionable_id', $payment->id)->delete();
                \App\Models\CashTransaction::where('payment_id', $payment->id)->forceDelete();
                $payment->delete();
                $deletedCount++;
            }

            // Devir Öncesi Giderler — ilişkili CashTransaction ile birlikte sil
            $expenses = Expense::where('account_id', $account->id)->where('is_imported', true)->get();
            foreach ($expenses as $expense) {
                AccountTransaction::where('transactionable_type', Expense::class)
                    ->where('transactionable_id', $expense->id)->delete();
                \App\Models\CashTransaction::where('expense_id', $expense->id)->forceDelete();
                $expense->delete();
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
     * Delete all imported account transactions and related expenses for the apartment.
     */
    public function destroyAllImported(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment) {
            abort(403);
        }

        if (! $this->isOwnerOf($apartment)) {
            abort(403, 'Bu işlem için yönetici yetkisi gereklidir.');
        }

        $deletedDues = 0;
        $deletedExpenses = 0;
        $deletedPayments = 0;
        $deletedTransactions = 0;

        DB::transaction(function () use ($apartment, &$deletedDues, &$deletedExpenses, &$deletedPayments, &$deletedTransactions) {
            // 1. Tüm içe aktarılmış aidatları (Due) bul ve sil
            $dues = Due::where('apartment_id', $apartment->id)
                ->where('is_imported', true)
                ->get();

            foreach ($dues as $due) {
                AccountTransaction::where('transactionable_type', Due::class)
                    ->where('transactionable_id', $due->id)
                    ->delete();
                $due->delete();
                $deletedDues++;
            }

            // 2. Tüm içe aktarılmış giderleri bul ve sil
            $expenses = Expense::where('apartment_id', $apartment->id)
                ->where('is_imported', true)
                ->get();

            foreach ($expenses as $expense) {
                AccountTransaction::where('transactionable_type', Expense::class)
                    ->where('transactionable_id', $expense->id)
                    ->delete();
                CashTransaction::where('expense_id', $expense->id)->forceDelete();
                $expense->delete();
                $deletedExpenses++;
            }

            // 3. Tüm içe aktarılmış ödemeleri sil (Payment + ilişkili CashTransaction)
            $payments = Payment::where('apartment_id', $apartment->id)
                ->where('is_imported', true)
                ->get();

            foreach ($payments as $payment) {
                AccountTransaction::where('transactionable_type', Payment::class)
                    ->where('transactionable_id', $payment->id)
                    ->delete();
                CashTransaction::where('payment_id', $payment->id)->forceDelete();
                $payment->delete();
                $deletedPayments++;
            }

            // 4. Kalan import edilmiş cari hareketleri sil (bağımsız transactionlar)
            $deletedTransactions = AccountTransaction::where('apartment_id', $apartment->id)
                ->where('is_imported', true)
                ->delete();
        });

        $parts = [];
        if ($deletedDues)         $parts[] = $deletedDues . ' aidat';
        if ($deletedExpenses)     $parts[] = $deletedExpenses . ' gider';
        if ($deletedPayments)     $parts[] = $deletedPayments . ' ödeme';
        if ($deletedTransactions) $parts[] = $deletedTransactions . ' cari hareket';

        $msg = !empty($parts) ? implode(', ', $parts) . ' silindi.' : 'Silinecek kayıt bulunamadı.';

        return redirect()->back()->with('status', $msg);
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
        $duesCount = $account->dues()->count();
        $transactionsCount = $account->transactions()->count();
        $expensesCount = $account->expenses()->count();
        $paymentsCount = $account->payments()->count();
        $assignmentsCount = $account->tenantAssignments()->count();

        // Kiracı atamalarını temizle (hesap silinmeden önce - bu engel olmamalı)
        if ($assignmentsCount > 0) {
            $account->tenantAssignments()->delete();
        }

        $hasTransactions = $duesCount > 0 || $transactionsCount > 0 || $expensesCount > 0 || $paymentsCount > 0;

        if ($hasTransactions) {
            $details = [];
            if ($duesCount > 0) $details[] = "Aidat: $duesCount";
            if ($transactionsCount > 0) $details[] = "Cari Hareket: $transactionsCount";
            if ($expensesCount > 0) $details[] = "Gider: $expensesCount";
            if ($paymentsCount > 0) $details[] = "Ödeme: $paymentsCount";

            return redirect()->back()->with('error', 'Bu hesapta hareket bulunduğu için silinemez. (' . implode(', ', $details) . ')');
        }

        // Kat maliki siliniyorsa, dairenin son kat maliki mi kontrol et
        if ($account->type === 'owner' && $account->unit_id) {
            $otherOwnersCount = Account::where('apartment_id', $apartment->id)
                ->where('type', 'owner')
                ->where('unit_id', $account->unit_id)
                ->where('id', '!=', $account->id)
                ->count();

            if ($otherOwnersCount === 0) {
                return redirect()->back()->with('error', 'Daire ' . $account->unit->unit_no . ' için bu son kat maliki. Her dairede en az bir kat maliki olmalıdır. Silme işlemi iptal edildi.');
            }
        }

        $account->delete();

        return redirect()->route('accounts.index')->with('status', 'Hesap silindi.');
    }

    /**
     * Show bulk account import form
     */
    public function bulkImportForm(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment) {
            abort(403);
        }

        if (! $this->isOwnerOf($apartment)) {
            abort(403, 'Bu işlem için yönetici yetkisi gereklidir.');
        }

        return view('accounts.import', compact('apartment'));
    }

    /**
     * Download sample Excel template for bulk account import
     */
    public function bulkImportSample()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Başlık
        $sheet->setCellValue('A1', 'Toplu Hesap ve Cari Hareket Import Şablonu');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        // Talimatlar
        $sheet->setCellValue('A3', 'Talimatlar:');
        $sheet->setCellValue('A4', '1. Tarih formatı: GG.AA.YYYY (örn: 05.01.2020)');
        $sheet->setCellValue('A5', '2. Hesap Adı: Zorunlu. Aynı isimdeki kayıtlar tek hesapta birleştirilir.');
        $sheet->setCellValue('A6', '3. Daire No: Opsiyonel. Daire ilişkisi kurmak için kullanılır.');
        $sheet->setCellValue('A7', '4. Kategori: Opsiyonel. Gider kategorisi (örn: Demirbaş, Elektrik).');
        $sheet->setCellValue('A8', '5. Borç veya Alacak sütunlarından biri dolu olmalı. Her ikisi de pozitif olamaz.');
        $sheet->getStyle('A3:A8')->getFont()->setSize(10);

        // Sütun başlıkları
        $row = 9;
        $sheet->setCellValue('A' . $row, 'Tarih');
        $sheet->setCellValue('B' . $row, 'Hesap Adı');
        $sheet->setCellValue('C' . $row, 'Daire No');
        $sheet->setCellValue('D' . $row, 'Kategori');
        $sheet->setCellValue('E' . $row, 'Açıklama');
        $sheet->setCellValue('F' . $row, 'Alacak');
        $sheet->setCellValue('G' . $row, 'Borç');
        $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':G' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');

        // Örnek veri - Kat maliki
        $row = 10;
        $sheet->setCellValue('A' . $row, '05.01.2020');
        $sheet->setCellValue('B' . $row, 'Recep Kalkan');
        $sheet->setCellValue('C' . $row, '03');
        $sheet->setCellValue('D' . $row, '');
        $sheet->setCellValue('E' . $row, '2020 Bina Masraf Tahsilatı');
        $sheet->setCellValue('F' . $row, 481.26);
        $sheet->setCellValue('G' . $row, '');

        // Örnek veri - Kiracı
        $row = 11;
        $sheet->setCellValue('A' . $row, '02.12.2020');
        $sheet->setCellValue('B' . $row, '2020 Yıldırım Suyu');
        $sheet->setCellValue('C' . $row, '');
        $sheet->setCellValue('D' . $row, 'Demirbaş');
        $sheet->setCellValue('E' . $row, '2020 Yamur Suyu Gider Tamiri');
        $sheet->setCellValue('F' . $row, '');
        $sheet->setCellValue('G' . $row, 170.00);

        // Para formatı
        $sheet->getStyle('F10:G11')->getNumberFormat()->setFormatCode('#,##0.00');

        // Sütun genişlikleri
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(40);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(15);

        // Kenarlıklar
        $sheet->getStyle('A9:G11')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Dosya oluştur
        $filename = 'toplu_hesap_import_sablon.xlsx';
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
     * Preview bulk account import - parse Excel and show accounts to be created
     */
    public function bulkImportPreview(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment) {
            abort(403);
        }

        if (! $this->isOwnerOf($apartment)) {
            abort(403, 'Bu işlem için yönetici yetkisi gereklidir.');
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $file = $validated['file'];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Başlık satırını dinamik bul: A sütununda 'Tarih' yazan ilk satır
        $dataStartIndex = 1;
        foreach ($rows as $idx => $row) {
            $cell = trim((string)($row[0] ?? ''));
            if (mb_strtolower($cell) === 'tarih') {
                $dataStartIndex = $idx + 1;
                break;
            }
        }

        $transactions = [];
        $errors = [];
        $uniqueAccounts = []; // [accountName => ['min_date' => '', 'max_date' => '', 'unit_no' => '', 'types' => []]]

        for ($i = $dataStartIndex; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty($row[0])) continue;

            $date = trim($row[0] ?? '');
            $accountName = trim($row[1] ?? '');
            $unitNo = trim($row[2] ?? '');
            $categoryName = trim($row[3] ?? '');
            $description = trim($row[4] ?? '');
            $creditRaw = $row[5] ?? 0;
            $debitRaw = $row[6] ?? 0;

            if (empty($accountName) || $accountName === '-' || $accountName === '—') {
                $accountName = 'Devir Öncesi Tedarikçi';
            }

            // Tarih parse
            try {
                $parsedDate = \Carbon\Carbon::createFromFormat('d.m.Y', $date);
            } catch (\Exception $e) {
                $errors[] = 'Satır ' . ($i + 1) . ': Tarih formatı hatalı (GG.AA.YYYY bekleniyor): ' . $date;
                continue;
            }

            $credit = $this->parseTurkishNumber($creditRaw);
            $debit = $this->parseTurkishNumber($debitRaw);

            if ($debit > 0 && $credit > 0) {
                $errors[] = 'Satır ' . ($i + 1) . ': Borç ve Alacak aynı anda pozitif olamaz.';
                continue;
            }

            if ($debit == 0 && $credit == 0) {
                continue;
            }

            // Hesap anahtarı: ad + daire no kombinasyonu (aynı ad farklı daire = farklı hesap)
            $accountKey = $accountName . '|' . $unitNo;

            $transactions[] = [
                'date' => $parsedDate->format('Y-m-d'),
                'display_date' => $parsedDate->format('d.m.Y'),
                'account_key' => $accountKey,
                'account_name' => $accountName,
                'unit_no' => $unitNo,
                'category_name' => $categoryName,
                'description' => $description,
                'credit' => $credit,
                'debit' => $debit,
                'row_number' => $i + 1,
            ];

            // Hesap istatistiklerini güncelle
            if (!isset($uniqueAccounts[$accountKey])) {
                $uniqueAccounts[$accountKey] = [
                    'name' => $accountName,
                    'min_date' => $parsedDate,
                    'max_date' => $parsedDate,
                    'unit_no' => $unitNo,
                    'transaction_count' => 0,
                ];
            } else {
                if ($parsedDate->lt($uniqueAccounts[$accountKey]['min_date'])) {
                    $uniqueAccounts[$accountKey]['min_date'] = $parsedDate;
                }
                if ($parsedDate->gt($uniqueAccounts[$accountKey]['max_date'])) {
                    $uniqueAccounts[$accountKey]['max_date'] = $parsedDate;
                }
            }
            $uniqueAccounts[$accountKey]['transaction_count']++;
        }

        if (empty($transactions) && empty($errors)) {
            return back()->with('error', 'İçeri aktarılacak veri bulunamadı.');
        }

        if (!empty($errors)) {
            return back()->with('error', 'Lütfen dosyayı kontrol edin:<br><br>' . implode('<br>', $errors));
        }

        // Mevcut hesapları bul - eşleştirme için (tip bilgisiyle birlikte)
        $existingAccountsWithType = Account::where('apartment_id', $apartment->id)
            ->select('id', 'name', 'type')
            ->get()
            ->keyBy('name')
            ->toArray();

        // Daireleri bul - unit_no eşleştirmesi için
        $units = Unit::where('apartment_id', $apartment->id)
            ->pluck('id', 'unit_no')
            ->toArray();

        // Her hesap için varsayılan tip belirle
        foreach ($uniqueAccounts as $key => &$account) {
            $name = $account['name'];
            // Mevcut hesap varsa onun tipini kullan, yoksa daire no'ya göre belirle
            if (isset($existingAccountsWithType[$name])) {
                $account['suggested_type'] = $existingAccountsWithType[$name]['type'];
            } else {
                $account['suggested_type'] = empty($account['unit_no']) ? 'supplier' : 'owner';
            }
            $account['existing_account_id'] = $existingAccountsWithType[$name]['id'] ?? null;
            $account['unit_id'] = !empty($account['unit_no']) && isset($units[$account['unit_no']])
                ? $units[$account['unit_no']]
                : null;
            $account['date_range'] = $account['min_date']->format('d.m.Y') . ' - ' . $account['max_date']->format('d.m.Y');
        }

        // Verileri session'a kaydet
        session([
            'bulk_import_transactions' => $transactions,
            'bulk_import_accounts' => $uniqueAccounts,
            'bulk_import_apartment_id' => $apartment->id,
        ]);

        return redirect()->route('accounts.bulk-import-preview-page');
    }

    /**
     * Show preview page for bulk import
     */
    public function bulkImportPreviewPage(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment) {
            abort(403);
        }

        if (! $this->isOwnerOf($apartment)) {
            abort(403, 'Bu işlem için yönetici yetkisi gereklidir.');
        }

        $transactions = session('bulk_import_transactions', []);
        $accounts = session('bulk_import_accounts', []);
        $sessionApartmentId = session('bulk_import_apartment_id');

        if (empty($transactions) || $sessionApartmentId != $apartment->id) {
            return redirect()->route('accounts.bulk-import')
                ->with('error', 'Önizleme verisi bulunamadı. Lütfen dosyayı tekrar yükleyin.');
        }

        // Tüm hesapları yükle (eşleştirme için)
        $allAccounts = Account::where('apartment_id', $apartment->id)
            ->with('unit')
            ->select('id', 'name', 'type', 'unit_id')
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type,
                'unit_no' => $a->unit?->unit_no,
            ])
            ->toArray();

        // Tüm daireleri yükle (daire seçimi için)
        $allUnits = Unit::where('apartment_id', $apartment->id)
            ->pluck('unit_no', 'id')
            ->toArray();

        return view('accounts.import-preview', compact(
            'transactions', 'accounts', 'apartment', 'allAccounts', 'allUnits'
        ));
    }

    /**
     * Confirm and execute bulk account import
     */
    public function bulkImportConfirm(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment) {
            abort(403);
        }

        if (! $this->isOwnerOf($apartment)) {
            abort(403, 'Bu işlem için yönetici yetkisi gereklidir.');
        }

        $transactions = session('bulk_import_transactions', []);
        $accounts = session('bulk_import_accounts', []);
        $sessionApartmentId = session('bulk_import_apartment_id');

        if (empty($transactions) || $sessionApartmentId != $apartment->id) {
            return redirect()->route('accounts.bulk-import')
                ->with('error', 'Import verisi bulunamadı. Lütfen dosyayı tekrar yükleyin.');
        }

        // Kullanıcıdan gelen seçimler
        $accountTypes = $request->input('account_types', []);
        $accountMapping = $request->input('account_mapping', []); // Hesap eşleştirme seçimi
        $renameAccounts = $request->input('rename_accounts', []); // Hesap adı güncelleme seçimi
        $unitMapping = $request->input('unit_mapping', []); // Daire eşleştirme seçimi

        // Tüm hesaplar için tip seçimi yapılmış mı kontrol et
        $missingTypes = [];
        foreach ($accounts as $accountKey => $accountData) {
            if (empty($accountTypes[$accountKey])) {
                $missingTypes[] = $accountData['name'] . ($accountData['unit_no'] ? ' (Daire ' . $accountData['unit_no'] . ')' : '');
            }
        }
        if (!empty($missingTypes)) {
            return redirect()->back()
                ->with('error', 'Tip seçimi yapılmamış hesaplar var: ' . implode(', ', $missingTypes))
                ->withInput();
        }

        $importedCount = 0;
        $createdAccounts = [];

        // Her hesap için tarih aralığını hesapla (tenant end date için)
        $accountDateRanges = [];
        foreach ($accounts as $accountKey => $accountData) {
            $accountTransactions = collect($transactions)->where('account_key', $accountKey);
            $accountDateRanges[$accountKey] = [
                'first' => $accountTransactions->min('date'),
                'last' => $accountTransactions->max('date'),
            ];
        }

        // Devir Öncesi Kasası — yoksa otomatik oluştur
        $cashBox = CashBox::firstOrCreate(
            ['apartment_id' => $apartment->id, 'name' => 'Devir Öncesi Kasası'],
            ['is_active' => true, 'description' => 'Devir Öncesi Kasası — import işlemleri için otomatik oluşturuldu.']
        );

        // Kategorileri önceden yükle (performans)
        $categories = Category::where('apartment_id', $apartment->id)
            ->whereIn('type', [Category::TYPE_INCOME, Category::TYPE_EXPENSE, Category::TYPE_ALL])
            ->where('is_active', true)
            ->get();
        $defaultCategory = $categories->firstWhere('name', 'Aidat') ?? $categories->first();
        $defaultExpenseCategory = $categories->whereIn('type', [Category::TYPE_EXPENSE, Category::TYPE_ALL])->first();

        DB::transaction(function () use ($apartment, $accounts, $transactions, $accountTypes, $accountMapping, $renameAccounts, $unitMapping, $accountDateRanges, $cashBox, &$categories, $defaultCategory, $defaultExpenseCategory, &$importedCount, &$createdAccounts) {
            // 1. Hesapları oluştur veya eşleştir
            foreach ($accounts as $accountKey => $accountData) {
                $accountName = $accountData['name'];
                $type = $accountTypes[$accountKey] ?? 'supplier';
                // Eski kiracı/eski kat maliki veritabanında orijinal tipte kaydedilir
                $dbType = match($type) {
                    'former_tenant' => 'tenant',
                    'former_owner'  => 'owner',
                    default         => $type,
                };
                $mappedAccountId = $accountMapping[$accountKey] ?? null;
                $selectedUnitId = $unitMapping[$accountKey] ?? null; // Kullanıcının seçtiği daire
                $originalUnitId = $accountData['unit_id'] ?? null; // Excel'deki daire
                $effectiveUnitId = $selectedUnitId ?: $originalUnitId; // Sonuçta kullanılacak daire

                if ($mappedAccountId) {
                    // Kullanıcı tarafından seçilen mevcut hesabı kullan
                    $account = Account::where('apartment_id', $apartment->id)
                        ->where('id', $mappedAccountId)
                        ->first();

                    // Hesap adı güncellenmek isteniyorsa güncelle
                    if ($account && !empty($renameAccounts[$accountKey])) {
                        $account->update(['name' => $accountName]);
                    }

                    // Daire değişiyorsa ve bu bir kiracıysa, eski dairenin kiracısına end_date ver
                    if ($account && $type === 'tenant' && $selectedUnitId && $account->unit_id && $selectedUnitId != $account->unit_id) {
                        $lastDate = $accountDateRanges[$accountKey]['last'] ?? now();
                        TenantAssignment::where('account_id', $account->id)
                            ->where('unit_id', $account->unit_id)
                            ->whereNull('end_date')
                            ->update(['end_date' => $lastDate]);
                    }

                    // Daire değiştiriliyorsa hesabı güncelle
                    if ($account && $selectedUnitId && $selectedUnitId != $account->unit_id) {
                        $account->update(['unit_id' => $selectedUnitId]);
                    }
                } else {
                    // Yeni hesap oluştur
                    $firstDate = $accountDateRanges[$accountKey]['first'] ?? null;
                    $lastDate  = $accountDateRanges[$accountKey]['last'] ?? null;
                    $isFormer = in_array($type, ['former_tenant', 'former_owner']);
                    $newAccountData = [
                        'apartment_id'         => $apartment->id,
                        'type'                 => $dbType,
                        'name'                 => $accountName,
                        'is_active'            => $isFormer ? false : true,
                        'unit_id'              => $effectiveUnitId,
                        'account_opening_date' => $firstDate,
                        'account_end_date'     => ($isFormer && $lastDate) ? $lastDate : null,
                    ];

                    $account = Account::create($newAccountData);
                }

                // Kiracı/Eski Kiracı için TenantAssignment oluştur/güncelle (eğer daire varsa)
                if (in_array($type, ['tenant', 'former_tenant']) && $effectiveUnitId) {
                    $firstDate = $accountDateRanges[$accountKey]['first'] ?? now();
                    $lastDate = $accountDateRanges[$accountKey]['last'] ?? null;

                    // Bu hesap-daire kombinasyonu için mevcut assignment var mı kontrol et
                    $existingAssignment = TenantAssignment::where('account_id', $account->id)
                        ->where('unit_id', $effectiveUnitId)
                        ->first();

                    if (!$existingAssignment) {
                        $assignmentData = [
                            'account_id' => $account->id,
                            'apartment_id' => $apartment->id,
                            'unit_id' => $effectiveUnitId,
                            'move_in_date' => $firstDate,
                        ];

                        // Eski kiracı ise çıkış tarihini ekle (son hareket tarihi)
                        if (in_array($type, ['former_tenant']) && $lastDate) {
                            $assignmentData['move_out_date'] = $lastDate;
                        }

                        TenantAssignment::create($assignmentData);
                    }
                }

                $createdAccounts[$accountKey] = $account->id;
            }

            // 2. Cari hareketleri oluştur
            foreach ($transactions as $t) {
                $accountId = $createdAccounts[$t['account_key']] ?? null;
                if (!$accountId) continue;

                // Hesabı bul
                $account = Account::find($accountId);
                $isSupplier = $account->type === 'supplier';

                $catName = $t['category_name'] ?? '';

                if ($isSupplier) {
                    // TEDARİKÇİ MANTIĞI:
                    // Alacak (credit) = Tedarikçiye borçluyuz = Gider (Expense)
                    // Borç (debit)    = Biz ödeme yaptık = Ödeme (Payment + CashTransaction)

                    if ($t['credit'] > 0) {
                        // Tedarikçi alacak → Gider (Expense)
                        if ($catName) {
                            $normalCatName = mb_strtolower(trim($catName));
                            $category = $categories->first(fn($c) => mb_strtolower(trim($c->name)) === $normalCatName);
                            if (!$category) {
                                $category = Category::firstOrCreate(
                                    ['apartment_id' => $apartment->id, 'name' => trim($catName)],
                                    ['type' => Category::TYPE_EXPENSE, 'is_active' => true]
                                );
                                if (!$categories->contains('id', $category->id)) {
                                    $categories->push($category);
                                }
                            }
                        } else {
                            $category = $defaultExpenseCategory;
                        }

                        $expense = Expense::create([
                            'apartment_id'     => $apartment->id,
                            'account_id'       => $accountId,
                            'category_id'      => $category?->id,
                            'description'      => $t['description'] ?: 'Devir Öncesi',
                            'amount'           => $t['credit'],
                            'paid_amount'      => 0,
                            'remaining_amount' => $t['credit'],
                            'expense_date'     => $t['date'],
                            'period_month'     => $t['date'],
                            'is_paid'          => false,
                            'is_imported'      => true,
                        ]);

                        AccountTransaction::create([
                            'apartment_id' => $apartment->id,
                            'account_id' => $accountId,
                            'transactionable_type' => Expense::class,
                            'transactionable_id' => $expense->id,
                            'transaction_date' => $t['date'],
                            'description' => $t['description'] ?: 'Devir Öncesi',
                            'type' => 'debit',
                            'amount' => $t['credit'],
                            'is_imported' => true,
                        ]);
                    } else {
                        // Tedarikçi borç → Biz ödeme yaptık (Payment + CashTransaction)
                        $payment = Payment::create([
                            'apartment_id' => $apartment->id,
                            'account_id' => $accountId,
                            'amount' => $t['debit'],
                            'unallocated_amount' => $t['debit'],
                            'payment_date' => $t['date'],
                            'description' => $t['description'],
                            'is_imported' => true,
                        ]);

                        CashTransaction::create([
                            'cash_box_id' => $cashBox->id,
                            'apartment_id' => $apartment->id,
                            'account_id' => $accountId,
                            'payment_id' => $payment->id,
                            'type' => 'expense',
                            'amount' => $t['debit'],
                            'description' => $t['description'],
                            'transaction_date' => $t['date'],
                            'is_active' => true,
                        ]);

                        AccountTransaction::create([
                            'apartment_id' => $apartment->id,
                            'account_id' => $accountId,
                            'transactionable_type' => Payment::class,
                            'transactionable_id' => $payment->id,
                            'transaction_date' => $t['date'],
                            'description' => $t['description'],
                            'type' => 'credit',
                            'amount' => $t['debit'],
                            'is_imported' => true,
                        ]);
                    }
                } else {
                    // KAT MALİKİ / KİRACİ MANTIĞI:
                    // Borç (debit)    = Aidat (Due)
                    // Alacak (credit) = Tahsilat (Payment + CashTransaction)

                    if ($t['debit'] > 0) {
                        // Kat maliki/Kiracı borç → Aidat (Due)
                        if ($catName) {
                            $normalCatName = mb_strtolower(trim($catName));
                            $category = $categories->first(fn($c) => mb_strtolower(trim($c->name)) === $normalCatName);
                            if (!$category) {
                                $category = Category::firstOrCreate(
                                    ['apartment_id' => $apartment->id, 'name' => trim($catName)],
                                    ['type' => Category::TYPE_INCOME, 'is_active' => true]
                                );
                                if (!$categories->contains('id', $category->id)) {
                                    $categories->push($category);
                                }
                            }
                        } else {
                            $category = $defaultCategory;
                        }

                        $due = Due::create([
                            'apartment_id' => $apartment->id,
                            'account_id' => $accountId,
                            'unit_id' => $account->unit_id,
                            'category_id' => $category?->id,
                            'amount' => $t['debit'],
                            'remaining_amount' => $t['debit'],
                            'due_date' => $t['date'],
                            'created_at_manual' => $t['date'],
                            'status' => 'unpaid',
                            'description' => $t['description'],
                            'is_imported' => true,
                        ]);

                        AccountTransaction::create([
                            'apartment_id' => $apartment->id,
                            'account_id' => $accountId,
                            'transactionable_type' => Due::class,
                            'transactionable_id' => $due->id,
                            'transaction_date' => $t['date'],
                            'description' => $t['description'],
                            'type' => 'debit',
                            'amount' => $t['debit'],
                            'is_imported' => true,
                        ]);
                    } else {
                        // Kat maliki/Kiracı alacak → Tahsilat (Payment + CashTransaction)
                        $payment = Payment::create([
                            'apartment_id' => $apartment->id,
                            'account_id' => $accountId,
                            'amount' => $t['credit'],
                            'unallocated_amount' => $t['credit'],
                            'payment_date' => $t['date'],
                            'description' => $t['description'],
                            'is_imported' => true,
                        ]);

                        CashTransaction::create([
                            'cash_box_id' => $cashBox->id,
                            'apartment_id' => $apartment->id,
                            'account_id' => $accountId,
                            'payment_id' => $payment->id,
                            'type' => 'income',
                            'amount' => $t['credit'],
                            'description' => $t['description'],
                            'transaction_date' => $t['date'],
                            'is_active' => true,
                        ]);

                        AccountTransaction::create([
                            'apartment_id' => $apartment->id,
                            'account_id' => $accountId,
                            'transactionable_type' => Payment::class,
                            'transactionable_id' => $payment->id,
                            'transaction_date' => $t['date'],
                            'description' => $t['description'],
                            'type' => 'credit',
                            'amount' => $t['credit'],
                            'is_imported' => true,
                        ]);
                    }
                }

                $importedCount++;
            }
        });

        // Otomatik eşleştirme: tedarikçi hesaplarında açıklama+tutar ile gider-ödeme çiftlerini kapat
        $autoMatchedCount = 0;
        $supplierAccountIds = Account::where('apartment_id', $apartment->id)
            ->where('type', 'supplier')
            ->whereIn('id', array_values($createdAccounts))
            ->pluck('id');

        foreach ($supplierAccountIds as $suppAccountId) {
            // Bu hesaba ait import edilmiş, ödenmemiş giderler (kronolojik)
            $openExpenses = Expense::where('account_id', $suppAccountId)
                ->where('is_imported', true)
                ->where('is_paid', false)
                ->where('remaining_amount', '>', 0)
                ->orderBy('expense_date')
                ->orderBy('id')
                ->get();

            // Bu hesaba ait import edilmiş, tahsis edilmemiş ödemeler (kronolojik)
            $openPayments = Payment::where('account_id', $suppAccountId)
                ->where('is_imported', true)
                ->where('unallocated_amount', '>', 0)
                ->orderBy('payment_date')
                ->orderBy('id')
                ->get();

            if ($openExpenses->isEmpty() || $openPayments->isEmpty()) continue;

            foreach ($openPayments as $payment) {
                if ($payment->unallocated_amount <= 0) continue;

                $paymentDesc = mb_strtolower(trim($payment->description ?? ''));

                // Bu ödemeyle eşleşebilecek giderleri bul:
                // gider açıklaması ödeme açıklamasının içinde geçiyor + tutar eşit
                $matchedExpense = $openExpenses
                    ->filter(fn($e) => $e->remaining_amount > 0)
                    ->filter(function ($e) use ($paymentDesc) {
                        $expenseDesc = mb_strtolower(trim($e->description ?? ''));
                        return $expenseDesc !== '' && mb_strpos($paymentDesc, $expenseDesc) !== false;
                    })
                    ->filter(fn($e) => abs($e->remaining_amount - $payment->unallocated_amount) < 0.01)
                    ->sortBy('expense_date') // FIFO: en eskiyi seç
                    ->first();

                if (!$matchedExpense) continue;

                DB::transaction(function () use ($payment, $matchedExpense, &$autoMatchedCount) {
                    $amount = $matchedExpense->remaining_amount;

                    PaymentAllocation::create([
                        'payment_id'  => $payment->id,
                        'expense_id'  => $matchedExpense->id,
                        'due_id'      => null,
                        'amount'      => $amount,
                    ]);

                    $matchedExpense->update([
                        'paid_amount'      => $matchedExpense->amount,
                        'remaining_amount' => 0,
                        'is_paid'          => true,
                    ]);

                    $payment->update([
                        'unallocated_amount' => 0,
                    ]);

                    $autoMatchedCount++;
                });

                // Koleksiyon içindeki değeri de güncelle (bir sonraki döngü için)
                $matchedExpense->remaining_amount = 0;
            }
        }

        // Session temizle
        session()->forget(['bulk_import_transactions', 'bulk_import_accounts', 'bulk_import_apartment_id']);

        $statusMsg = count($createdAccounts) . ' hesap ve ' . $importedCount . ' cari hareket başarıyla import edildi.';
        if ($autoMatchedCount > 0) {
            $statusMsg .= ' ' . $autoMatchedCount . ' gider-ödeme çifti otomatik eşleştirildi.';
        }

        return redirect()->route('accounts.index')
            ->with('status', $statusMsg);
    }

    public function createSupplierPayment(Account $account)
    {
        if ($account->type !== Account::TYPE_SUPPLIER) {
            abort(404);
        }

        $cashBoxes = CashBox::where('apartment_id', $account->apartment_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('accounts.supplier-payment', compact('account', 'cashBoxes'));
    }

    public function storeSupplierPayment(Request $request, Account $account)
    {
        if ($account->type !== Account::TYPE_SUPPLIER) {
            abort(404);
        }

        $validated = $request->validate([
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'cash_box_id'  => ['required', 'integer', Rule::exists('cash_boxes', 'id')->where('apartment_id', $account->apartment_id)->where('is_active', true)],
            'payment_date' => ['required', 'date'],
            'description'  => ['nullable', 'string', 'max:255'],
        ]);

        $paymentDescription = $validated['description'] ?? 'Tedarikçi ödemesi';
        $payment = null;

        DB::transaction(function () use ($account, $validated, $paymentDescription, &$payment) {
            $payment = Payment::create([
                'apartment_id'       => $account->apartment_id,
                'account_id'         => $account->id,
                'amount'             => $validated['amount'],
                'unallocated_amount' => $validated['amount'],
                'payment_date'       => $validated['payment_date'],
                'description'        => $paymentDescription,
            ]);

            CashTransaction::create([
                'apartment_id'     => $account->apartment_id,
                'cash_box_id'      => $validated['cash_box_id'],
                'account_id'       => $account->id,
                'payment_id'       => $payment->id,
                'type'             => 'expense',
                'description'      => $paymentDescription,
                'amount'           => $validated['amount'],
                'transaction_date' => $validated['payment_date'],
                'is_active'        => true,
            ]);

            AccountTransaction::create([
                'apartment_id'         => $account->apartment_id,
                'account_id'           => $account->id,
                'transactionable_type' => Payment::class,
                'transactionable_id'   => $payment->id,
                'type'                 => 'debit',
                'description'          => $paymentDescription,
                'amount'               => $validated['amount'],
                'transaction_date'     => $validated['payment_date'],
            ]);
        });

        return redirect()->route('payments.show', $payment)
            ->with('status', 'Tedarikçi ödemesi kaydedildi.');
    }

    public function multiPayExpenses(Request $request, Account $account)
    {
        $expenseIds = array_filter(explode(',', $request->input('expense_ids', '')));

        $expenses = Expense::whereIn('id', $expenseIds)
            ->where('account_id', $account->id)
            ->where('is_paid', false)
            ->orderBy('expense_date')
            ->get();

        if ($expenses->isEmpty()) {
            return redirect()->route('accounts.show', $account)->with('error', 'Geçerli gider seçilmedi.');
        }

        $cashBoxes = CashBox::where('apartment_id', $account->apartment_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = Category::where('apartment_id', $account->apartment_id)
            ->where('type', Category::TYPE_EXPENSE)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $totalAmount = $expenses->sum('amount');

        return view('accounts.expenses.multi-pay', compact('account', 'expenses', 'cashBoxes', 'categories', 'totalAmount'));
    }

    public function storeMultiPayExpenses(Request $request, Account $account)
    {
        $validated = $request->validate([
            'expense_ids'   => ['required', 'string'],
            'cash_box_id'   => ['required', 'integer', Rule::exists('cash_boxes', 'id')->where('apartment_id', $account->apartment_id)->where('is_active', true)],
            'category_id'   => ['required', 'integer', Rule::exists('categories', 'id')->where('apartment_id', $account->apartment_id)],
            'payment_date'  => ['required', 'date'],
            'description'   => ['nullable', 'string', 'max:255'],
        ]);

        $expenseIds = array_filter(explode(',', $validated['expense_ids']));

        $expenses = Expense::whereIn('id', $expenseIds)
            ->where('account_id', $account->id)
            ->where('is_paid', false)
            ->get();

        if ($expenses->isEmpty()) {
            return redirect()->route('accounts.show', $account)->with('error', 'Geçerli gider bulunamadı.');
        }

        $totalAmount = $expenses->sum('amount');
        $paymentDescription = $validated['description'] ?? ($expenses->count() . ' gider toplu ödemesi');

        $payment = null;

        DB::transaction(function () use ($account, $expenses, $validated, $totalAmount, $paymentDescription, &$payment) {
            $payment = Payment::create([
                'apartment_id'      => $account->apartment_id,
                'account_id'        => $account->id,
                'amount'            => $totalAmount,
                'unallocated_amount' => 0,
                'payment_date'      => $validated['payment_date'],
                'description'       => $paymentDescription,
            ]);

            CashTransaction::create([
                'apartment_id'     => $account->apartment_id,
                'cash_box_id'      => $validated['cash_box_id'],
                'account_id'       => $account->id,
                'payment_id'       => $payment->id,
                'category_id'      => $validated['category_id'],
                'type'             => 'expense',
                'description'      => $paymentDescription,
                'amount'           => $totalAmount,
                'transaction_date' => $validated['payment_date'],
                'is_active'        => true,
            ]);

            AccountTransaction::create([
                'apartment_id'         => $account->apartment_id,
                'account_id'           => $account->id,
                'transactionable_type' => Payment::class,
                'transactionable_id'   => $payment->id,
                'type'                 => 'debit',
                'description'          => $paymentDescription,
                'amount'               => $totalAmount,
                'transaction_date'     => $validated['payment_date'],
            ]);

            foreach ($expenses as $expense) {
                $payment->allocations()->create([
                    'expense_id' => $expense->id,
                    'amount'     => $expense->amount,
                ]);

                $expense->update([
                    'is_paid'          => true,
                    'paid_amount'      => $expense->amount,
                    'remaining_amount' => 0,
                ]);
            }
        });

        return redirect()->route('payments.show', $payment)
            ->with('status', $expenses->count() . ' gider ödemesi başarıyla kaydedildi.');
    }
}
