<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $sortBy = $request->query('sort_by', 'expense_date');
        $sortDirection = $request->query('sort_direction', 'desc');
        $validSortColumns = ['expense_date', 'period_month', 'category', 'account_id', 'amount', 'is_paid'];

        if (! in_array($sortBy, $validSortColumns)) {
            $sortBy = 'expense_date';
        }
        if (! in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $filterPeriod   = $request->query('period');
        $filterStatus   = $request->query('status');
        $filterCategory = $request->query('category');
        $filterSearch   = $request->query('search');
        $showImported   = $request->boolean('show_imported', false);

        $isOwner = $this->isOwnerOf($apartment);

        $expenses = Expense::query()
            ->with(['account', 'categoryRelation'])
            ->when($apartment, fn ($q) => $q->where('apartment_id', $apartment->id))
            ->when(! $showImported, fn ($q) => $q->where('is_imported', false))
            ->when($filterSearch, fn ($q) => $q->where(function ($sub) use ($filterSearch) {
                $sub->whereHas('account', fn ($a) => $a->where('name', 'like', '%' . $filterSearch . '%'))
                    ->orWhere('amount', 'like', '%' . $filterSearch . '%')
                    ->orWhere('description', 'like', '%' . $filterSearch . '%');
            }))
            ->when($filterPeriod,   fn ($q) => $q->whereYear('period_month', substr($filterPeriod, 0, 4))->whereMonth('period_month', substr($filterPeriod, 5, 2)))
            ->when($filterStatus === 'paid',    fn ($q) => $q->where('is_paid', true))
            ->when($filterStatus === 'unpaid',  fn ($q) => $q->where('is_paid', false))
            ->when($filterCategory, fn ($q) => $q->where('category', $filterCategory))
            ->orderBy($sortBy, $sortDirection)
            ->paginate(25)->withQueryString();

        $categories = Expense::query()
            ->when($apartment, fn ($q) => $q->where('apartment_id', $apartment->id))
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $filters = compact('filterPeriod', 'filterStatus', 'filterCategory', 'filterSearch', 'showImported');

        return view('expenses.index', compact('expenses', 'apartment', 'sortBy', 'sortDirection', 'filters', 'categories', 'isOwner', 'showImported'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        $accounts = Account::query()
            ->where('apartment_id', $apartment->id)
            ->where('type', Account::TYPE_SUPPLIER)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $categories = $this->categories($apartment->id, Category::TYPE_EXPENSE);

        $cashBoxes = CashBox::query()
            ->where('apartment_id', $apartment->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedAccountId = $request->query('account_id');

        $accountCategoryMap = $accounts->mapWithKeys(fn ($a) => [
            $a->id => $a->default_category_id,
        ])->filter()->toJson();

        return view('expenses.create', compact('apartment', 'accounts', 'categories', 'cashBoxes', 'selectedAccountId', 'accountCategoryMap'));
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

        $isPaid = $request->boolean('is_paid');

        $rules = [
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('apartment_id', $apartment->id)
                    ->where('type', Account::TYPE_SUPPLIER),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('apartment_id', $apartment->id)
                    ->where('is_active', true),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'period_month' => ['required', 'date_format:Y-m'],
            'is_paid' => ['nullable', 'boolean'],
        ];

        // If paid, require payment fields
        if ($isPaid) {
            $rules['payment_date'] = ['required', 'date'];
            $rules['cash_box_id'] = [
                'required',
                'integer',
                Rule::exists('cash_boxes', 'id')
                    ->where('apartment_id', $apartment->id)
                    ->where('is_active', true),
            ];
        }

        $validated = $request->validate($rules);

        $category = Category::findOrFail($validated['category_id']);

        DB::transaction(function () use ($apartment, $validated, $category, $request, $isPaid) {
            $expense = Expense::create([
                'apartment_id' => $apartment->id,
                'account_id' => $validated['account_id'] ?? null,
                'category_id' => $category->id,
                'category' => $category->name,
                'description' => $validated['description'] ?? null,
                'amount' => $validated['amount'],
                'expense_date' => $validated['expense_date'],
                'due_date' => $validated['due_date'] ?? null,
                'period_month' => $validated['period_month'].'-01',
                'is_paid' => $isPaid,
            ]);

            if ($validated['account_id']) {
                // Gider kaydı: tedarikçi alacaklı oldu (credit)
                AccountTransaction::create([
                    'apartment_id' => $apartment->id,
                    'account_id' => $validated['account_id'],
                    'transactionable_type' => Expense::class,
                    'transactionable_id' => $expense->id,
                    'type' => 'credit',
                    'description' => $validated['description'] ?? 'Gider kaydı',
                    'amount' => $validated['amount'],
                    'transaction_date' => $validated['expense_date'],
                ]);
            }

            if ($isPaid) {
                $paymentDescription = ($validated['description'] ?? 'Gider').' Ödemesi';

                // Gider ödendiyse: Kasadan çıkış kaydet
                CashTransaction::create([
                    'apartment_id' => $apartment->id,
                    'cash_box_id' => $validated['cash_box_id'],
                    'account_id' => $validated['account_id'] ?? null,
                    'category_id' => $category->id,
                    'type' => 'expense',
                    'description' => $paymentDescription,
                    'amount' => $validated['amount'],
                    'transaction_date' => $validated['payment_date'],
                    'is_active' => true,
                ]);

                // Tedarikçi varsa ödeme kaydı: alacak kapandı (debit)
                if ($validated['account_id']) {
                    AccountTransaction::create([
                        'apartment_id' => $apartment->id,
                        'account_id' => $validated['account_id'],
                        'transactionable_type' => Expense::class,
                        'transactionable_id' => $expense->id,
                        'type' => 'debit',
                        'description' => $paymentDescription,
                        'amount' => $validated['amount'],
                        'transaction_date' => $validated['payment_date'],
                    ]);
                }
            }
        });

        return redirect()->route('expenses.index')->with('status', 'Gider kaydı oluşturuldu.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $expense = $this->findExpense($id);

        return view('expenses.show', compact('expense'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $expense = $this->findExpense($id);
        $accounts = $this->supplierAccounts($expense->apartment_id);
        $categories = $this->categories($expense->apartment_id, Category::TYPE_EXPENSE, $expense->category_id);

        return view('expenses.edit', compact('expense', 'accounts', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $expense = $this->findExpense($id);
        $validated = $this->validateExpense($request, $expense->apartment_id);

        $oldAccountId = $expense->account_id;
        $newAccountId = $validated['account_id'] ?? null;

        $expense->update([
            'account_id' => $newAccountId,
            'category_id' => $validated['category_id'],
            'category' => Category::findOrFail($validated['category_id'])->name,
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'expense_date' => $validated['expense_date'],
            'due_date' => $validated['due_date'] ?? null,
            'period_month' => $validated['period_month'].'-01',
            'is_paid' => $request->boolean('is_paid'),
        ]);

        // Tedarikçi değişikliği varsa muhasebe kayıtlarını güncelle
        if ($newAccountId !== $oldAccountId) {
            // Eski tedarikçiye ait kayıtları sil (varsa)
            if ($oldAccountId) {
                $expense->transactions()->where('account_id', $oldAccountId)->delete();
            }

            // Yeni tedarikçiye kayıtları oluştur
            if ($newAccountId) {
                // 1. Alacak kaydı (gider tutarı kadar)
                AccountTransaction::create([
                    'apartment_id' => $expense->apartment_id,
                    'account_id' => $newAccountId,
                    'transactionable_type' => Expense::class,
                    'transactionable_id' => $expense->id,
                    'type' => 'credit',
                    'description' => $validated['description'] ?? 'Gider kaydı',
                    'amount' => $expense->amount,
                    'transaction_date' => $expense->expense_date,
                ]);

                // 2. Ödeme kaydı (ödenen tutar kadar) - varsa
                $paidAmount = $expense->is_imported
                    ? ($expense->paid_amount ?? 0)
                    : ($expense->is_paid ? $expense->amount : 0);

                if ($paidAmount > 0) {
                    AccountTransaction::create([
                        'apartment_id' => $expense->apartment_id,
                        'account_id' => $newAccountId,
                        'transactionable_type' => Expense::class,
                        'transactionable_id' => $expense->id,
                        'type' => 'debit',
                        'description' => ($validated['description'] ?? 'Gider') . ' ödemesi',
                        'amount' => $paidAmount,
                        'transaction_date' => $expense->expense_date,
                    ]);
                }
            }
        }

        return redirect()->route('expenses.index')->with('status', 'Gider kaydı güncellendi.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $expense = $this->findExpense($id);

        if ($expense->is_paid) {
            return redirect()->route('expenses.show', $expense)->with('error', 'Ödenmiş gider silinemez. Önce ödemeyi iptal edin.');
        }

        // Tahsis edilmiş ödeme varsa silmeye izin verme
        $hasAllocations = $expense->paymentAllocations()->exists();
        if ($hasAllocations) {
            return redirect()->route('expenses.show', $expense)->with('error', 'Bu gider tahsis edilmiş ödemelerle bağlantılı. Önce tahsisleri iptal edin.');
        }

        DB::transaction(function () use ($expense) {
            // Muhasebe kayıtlarını sil
            $expense->transactions()->delete();
            // Gideri soft delete ile sil
            $expense->delete();
        });

        return redirect()->route('expenses.index')->with('status', 'Gider kaydı silindi.');
    }

    public function createPayment(string $id)
    {
        $expense = $this->findExpense($id);
        $cashBoxes = CashBox::query()
            ->where('apartment_id', $expense->apartment_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $categories = $this->categories($expense->apartment_id, Category::TYPE_EXPENSE, $expense->category_id);

        return view('expenses.payment', compact('expense', 'cashBoxes', 'categories'));
    }

    public function storePayment(Request $request, string $id)
    {
        $expense = $this->findExpense($id);

        $validated = $request->validate([
            'cash_box_id' => [
                'required',
                'integer',
                Rule::exists('cash_boxes', 'id')
                    ->where('apartment_id', $expense->apartment_id)
                    ->where('is_active', true),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('apartment_id', $expense->apartment_id)
                    ->where('is_active', true),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$expense->amount],
            'payment_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($expense, $validated) {
            // 1. Payment kaydı oluştur (tedarikçi hesabına)
            $payment = Payment::create([
                'apartment_id' => $expense->apartment_id,
                'account_id' => $expense->account_id,
                'amount' => $validated['amount'],
                'unallocated_amount' => 0, // Otomatik tahsis edilecek
                'payment_date' => $validated['payment_date'],
                'method' => null,
                'description' => $validated['description'] ?? ($expense->description ? $expense->description.' ödemesi' : 'Gider ödemesi'),
            ]);

            // 2. Kasa hareketi oluştur (payment_id ile ilişkilendirilmiş)
            CashTransaction::create([
                'apartment_id' => $expense->apartment_id,
                'cash_box_id' => $validated['cash_box_id'],
                'account_id' => $expense->account_id,
                'payment_id' => $payment->id,
                'category_id' => $validated['category_id'],
                'type' => 'expense',
                'description' => $validated['description'] ?? ($expense->description ? $expense->description.' ödemesi' : 'Gider ödemesi'),
                'amount' => $validated['amount'],
                'transaction_date' => $validated['payment_date'],
                'is_active' => true,
            ]);

            // 3. Tedarikçi hesabına ödeme kaydı (debit - alacak kapanıyor)
            if ($expense->account_id) {
                AccountTransaction::create([
                    'apartment_id' => $expense->apartment_id,
                    'account_id' => $expense->account_id,
                    'transactionable_type' => Payment::class,
                    'transactionable_id' => $payment->id,
                    'type' => 'debit',
                    'description' => $validated['description'] ?? ($expense->description ? $expense->description.' ödemesi' : 'Gider ödemesi'),
                    'amount' => $validated['amount'],
                    'transaction_date' => $validated['payment_date'],
                ]);
            }

            // 4. Gider'e otomatik tahsis oluştur
            $payment->allocations()->create([
                'expense_id' => $expense->id,
                'amount' => $validated['amount'],
            ]);

            // 5. Gider'i kapat
            $expense->update(['is_paid' => true]);
        });

        return redirect()->route('expenses.index')->with('status', 'Gider ödemesi kaydedildi ve tahsis edildi.');
    }

    public function destroyPayment(string $id)
    {
        $expense = $this->findExpense($id);

        if (! $expense->is_paid) {
            return redirect()->route('expenses.show', $expense)->with('error', 'Bu gider zaten ödenmemiş durumda.');
        }

        DB::transaction(function () use ($expense) {
            // 1. Gider'e ait tahsisi bul ve iptal et (yeni Payment/Allocation mantığı)
            $allocation = PaymentAllocation::where('expense_id', $expense->id)->first();
            if ($allocation) {
                $payment = $allocation->payment;
                $amount = $allocation->amount;

                // Tahsisi sil
                $allocation->delete();

                // Ödemenin tahsis edilmemiş tutarını artır
                if ($payment) {
                    $payment->increment('unallocated_amount', $amount);
                }

                // Payment'e ait AccountTransaction kayıtlarını sil
                if ($payment) {
                    AccountTransaction::where('apartment_id', $expense->apartment_id)
                        ->where('account_id', $expense->account_id)
                        ->where('transactionable_type', Payment::class)
                        ->where('transactionable_id', $payment->id)
                        ->delete();
                }

                // Payment'e ait CashTransaction kayıtlarını sil
                if ($payment) {
                    CashTransaction::where('apartment_id', $expense->apartment_id)
                        ->where('payment_id', $payment->id)
                        ->delete();
                }
            } else {
                // Eski mantık: Devir ile gelen veya direkt ödenmiş giderler için
                // Expense'e ait AccountTransaction kayıtlarını sil (debit tipi - ödeme kaydı)
                $expense->transactions()
                    ->where('type', 'debit')
                    ->delete();

                // Eski CashTransaction kayıtlarını sil (expense_id ile ilişkili)
                CashTransaction::where('apartment_id', $expense->apartment_id)
                    ->where('expense_id', $expense->id)
                    ->delete();
            }

            // 2. Gider'i aç
            $expense->update(['is_paid' => false]);
        });

        return redirect()->route('expenses.show', $expense)->with('status', 'Ödeme iptal edildi ve gider tekrar açık durumuna alındı.');
    }

    private function findExpense(string $id): Expense
    {
        $apartment = app(CurrentApartment::class)->getFor(auth()->user());

        if (! $apartment) {
            abort(404);
        }

        return Expense::query()
            ->with(['account', 'categoryRelation', 'transactions', 'cashTransactions'])
            ->where('apartment_id', $apartment->id)
            ->findOrFail($id);
    }

    private function supplierAccounts(int $apartmentId)
    {
        return Account::query()
            ->where('apartment_id', $apartmentId)
            ->where('type', Account::TYPE_SUPPLIER)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    private function validateExpense(Request $request, int $apartmentId): array
    {
        return $request->validate([
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('apartment_id', $apartmentId)
                    ->where('type', Account::TYPE_SUPPLIER),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('apartment_id', $apartmentId)
                    ->where('is_active', true),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'period_month' => ['required', 'date_format:Y-m'],
            'is_paid' => ['nullable', 'boolean'],
        ]);
    }

    private function categories(int $apartmentId, string $type, ?int $selectedId = null)
    {
        return Category::query()
            ->where('apartment_id', $apartmentId)
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $selectedId))
            ->where(fn ($query) => $query->where('type', Category::TYPE_ALL)->orWhere('type', $type))
            ->orderBy('name')
            ->get();
    }

    /**
     * Show expense import form
     */
    public function importForm(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        if (! $this->isOwnerOf($apartment)) {
            abort(403, 'Bu işlem için yönetici yetkisi gereklidir.');
        }

        return view('expenses.import', compact('apartment'));
    }

    /**
     * Download sample Excel template for expense import
     */
    public function importSample(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment) {
            abort(403);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $sheet->setCellValue('A1', 'Tarih');
        $sheet->setCellValue('B1', 'Hesap Adı');
        $sheet->setCellValue('C1', 'Açıklama');
        $sheet->setCellValue('D1', 'Son Ödeme Tarihi');
        $sheet->setCellValue('E1', 'Kategori');
        $sheet->setCellValue('F1', 'Alacak');
        $sheet->setCellValue('G1', 'Borç');

        // Sample data - fully paid
        $sheet->setCellValue('A2', '15.01.2024');
        $sheet->setCellValue('B2', 'ABC Temizlik Ltd');
        $sheet->setCellValue('C2', 'Ocak ayı temizlik ücreti');
        $sheet->setCellValue('D2', '30.01.2024');
        $sheet->setCellValue('E2', 'Temizlik');
        $sheet->setCellValue('F2', '5000');
        $sheet->setCellValue('G2', '5000');

        // Sample data - partially paid
        $sheet->setCellValue('A3', '10.02.2024');
        $sheet->setCellValue('B3', 'XYZ Elektrik');
        $sheet->setCellValue('C3', 'Şubat elektrik faturası');
        $sheet->setCellValue('D3', '28.02.2024');
        $sheet->setCellValue('E3', 'Elektrik');
        $sheet->setCellValue('F3', '3000');
        $sheet->setCellValue('G3', '1500');

        // Sample data - unpaid
        $sheet->setCellValue('A4', '05.03.2024');
        $sheet->setCellValue('B4', 'Doğalgaz AŞ');
        $sheet->setCellValue('C4', 'Mart doğalgaz faturası');
        $sheet->setCellValue('D4', '31.03.2024');
        $sheet->setCellValue('E4', 'Doğalgaz');
        $sheet->setCellValue('F4', '4500');
        $sheet->setCellValue('G4', '0');

        // Style headers
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        // Auto width
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $filename = 'gider_import_sablonu.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), 'expense_import_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * Process Excel and prepare preview data
     */
    public function importPreview(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        if (! $this->isOwnerOf($apartment)) {
            abort(403, 'Bu işlem için yönetici yetkisi gereklidir.');
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $file = $validated['file'];

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Excel dosyası okunamadı: ' . $e->getMessage());
        }

        // Remove header row
        $headers = array_shift($rows);

        // Load categories for matching
        $categories = Category::where('apartment_id', $apartment->id)
            ->where('is_active', true)
            ->get()
            ->keyBy(fn ($cat) => mb_strtolower($cat->name, 'UTF-8'));

        $defaultCategory = Category::where('apartment_id', $apartment->id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'Diğer')->orWhere('name', 'diğer'))
            ->first()
            ?? $categories->first();

        $transactions = [];
        $errors = [];
        $rowNum = 2;

        foreach ($rows as $row) {
            // Skip empty rows
            if (empty($row[0]) && empty($row[2])) {
                $rowNum++;
                continue;
            }

            $date = $this->parseDate($row[0] ?? null);
            $accountName = trim($row[1] ?? '');
            $description = trim($row[2] ?? '');
            $dueDate = $this->parseDate($row[3] ?? null);
            $categoryName = trim($row[4] ?? '');
            $alacak = $this->parseTurkishNumber($row[5] ?? 0);
            $borc = $this->parseTurkishNumber($row[6] ?? 0);

            $rowErrors = [];

            if (! $date) {
                $rowErrors[] = 'Geçersiz tarih';
            }
            if (empty($description)) {
                $rowErrors[] = 'Açıklama boş';
            }
            if ($alacak < 0) {
                $rowErrors[] = 'Alacak negatif olamaz';
            }
            if ($borc < 0) {
                $rowErrors[] = 'Borç negatif olamaz';
            }
            if ($borc > $alacak) {
                $rowErrors[] = 'Borç, Alacak\'tan büyük olamaz';
            }

            // Match category
            $matchedCategory = null;
            $categoryMatched = false;
            if (! empty($categoryName)) {
                $matchedCategory = $categories->get(mb_strtolower($categoryName, 'UTF-8'));
                if ($matchedCategory) {
                    $categoryMatched = true;
                }
            }

            if (! $matchedCategory) {
                $matchedCategory = $defaultCategory;
            }

            $transactions[] = [
                'row' => $rowNum,
                'date' => $date?->format('Y-m-d'),
                'account_name' => $accountName,
                'description' => $description,
                'due_date' => $dueDate?->format('Y-m-d'),
                'category_name' => $categoryName,
                'category_id' => $matchedCategory?->id,
                'category_matched' => $categoryMatched,
                'alacak' => $alacak,
                'borc' => $borc,
                'remaining' => $alacak - $borc,
                'errors' => $rowErrors,
                'is_valid' => empty($rowErrors),
            ];

            if (! empty($rowErrors)) {
                $errors[] = "Satır {$rowNum}: " . implode(', ', $rowErrors);
            }

            $rowNum++;
        }

        if (empty($transactions)) {
            return redirect()->back()->with('error', 'Excel dosyasında işlenecek veri bulunamadı.');
        }

        session([
            'import_expenses' => $transactions,
            'import_apartment_id' => $apartment->id,
            'import_errors' => $errors,
        ]);

        return redirect()->route('expenses.import-preview-page');
    }

    /**
     * Show import preview page
     */
    public function importPreviewPage(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment) {
            abort(403);
        }

        $transactions = session('import_expenses', []);
        $validationErrors = session('import_errors', []);

        if (empty($transactions)) {
            return redirect()->route('expenses.import')->with('error', 'Önizleme verisi bulunamadı.');
        }

        // Calculate totals
        $totalAlacak = collect($transactions)->sum('alacak');
        $totalBorc = collect($transactions)->sum('borc');
        $totalRemaining = collect($transactions)->sum('remaining');
        $validCount = collect($transactions)->where('is_valid', true)->count();
        $invalidCount = count($transactions) - $validCount;

        return view('expenses.import-preview', compact(
            'apartment',
            'transactions',
            'validationErrors',
            'totalAlacak',
            'totalBorc',
            'totalRemaining',
            'validCount',
            'invalidCount'
        ));
    }

    /**
     * Confirm and import expenses
     */
    public function importConfirm(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment) {
            abort(403);
        }

        if (! $this->isOwnerOf($apartment)) {
            abort(403, 'Bu işlem için yönetici yetkisi gereklidir.');
        }

        $transactions = session('import_expenses', []);

        if (empty($transactions)) {
            return redirect()->route('expenses.import')->with('error', 'İçeri aktarılacak veri bulunamadı.');
        }

        // Filter only valid transactions
        $validTransactions = collect($transactions)->where('is_valid', true)->all();

        if (empty($validTransactions)) {
            return redirect()->route('expenses.import')->with('error', 'Geçerli kayıt bulunamadı.');
        }

        // Devir Öncesi Kasası — yoksa otomatik oluştur
        $cashBox = CashBox::firstOrCreate(
            ['apartment_id' => $apartment->id, 'name' => 'Devir Öncesi Kasası'],
            ['is_active' => true, 'description' => 'Devir Öncesi Kasası — gider import işlemleri için otomatik oluşturuldu.']
        );

        $importedCount = 0;
        DB::transaction(function () use ($apartment, $validTransactions, $cashBox, &$importedCount) {
            foreach ($validTransactions as $t) {
                $expense = Expense::create([
                    'apartment_id' => $apartment->id,
                    'account_id' => null, // Tedarikçi sonradan bağlanacak
                    'category_id' => $t['category_id'],
                    'category' => Category::find($t['category_id'])?->name ?? 'Diğer',
                    'description' => $t['description'] . ($t['account_name'] ? ' (Hesap: ' . $t['account_name'] . ')' : ''),
                    'amount' => $t['alacak'],
                    'paid_amount' => $t['borc'],
                    'remaining_amount' => $t['remaining'],
                    'expense_date' => $t['date'],
                    'due_date' => $t['due_date'] ?? $t['date'],
                    'period_month' => $t['date'],
                    'is_paid' => $t['remaining'] == 0,
                    'is_imported' => true,
                ]);

                // If paid_amount > 0, create cash transaction
                if ($t['borc'] > 0) {
                    CashTransaction::create([
                        'apartment_id' => $apartment->id,
                        'cash_box_id' => $cashBox->id,
                        'expense_id' => $expense->id,
                        'category_id' => $t['category_id'],
                        'type' => 'expense',
                        'description' => 'Devir Öncesi Gider Ödemesi: ' . $t['description'],
                        'amount' => $t['borc'],
                        'transaction_date' => $t['date'],
                        'is_active' => true,
                    ]);
                }

                $importedCount++;
            }
        });

        session()->forget(['import_expenses', 'import_apartment_id', 'import_errors']);

        return redirect()->route('expenses.index', ['show_imported' => 1])
            ->with('status', $importedCount . ' adet gider başarıyla Devir Öncesi olarak içeri aktarıldı.');
    }

    private function parseDate($value): ?\Carbon\Carbon
    {
        if (empty($value)) {
            return null;
        }

        // Excel date serial number
        if (is_numeric($value)) {
            try {
                return \Carbon\Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value));
            } catch (\Exception $e) {
                return null;
            }
        }

        // String date - try d.m.Y format first (01.01.2024)
        if (is_string($value)) {
            $value = trim($value);

            // Try DD.MM.YYYY format (01.01.2024)
            if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value)) {
                try {
                    return \Carbon\Carbon::createFromFormat('d.m.Y', $value);
                } catch (\Exception $e) {
                    // Fall through to generic parse
                }
            }

            // Try YYYY-MM-DD format (2024-01-01)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                try {
                    return \Carbon\Carbon::createFromFormat('Y-m-d', $value);
                } catch (\Exception $e) {
                    // Fall through to generic parse
                }
            }
        }

        // Fallback to generic parse
        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseTurkishNumber($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (empty($value)) {
            return 0.0;
        }

        // Remove spaces, convert Turkish decimal/thousands separators
        $value = str_replace(' ', '', $value);
        // Handle Turkish format: 1.234,56 → 1234.56
        $value = str_replace('.', '', $value); // Remove thousand separator
        $value = str_replace(',', '.', $value); // Convert decimal separator

        return (float) $value;
    }
}



