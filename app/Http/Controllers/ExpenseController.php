<?php



namespace App\Http\Controllers;



use App\Models\Account;

use App\Models\AccountTransaction;

use App\Models\CashBox;

use App\Models\CashTransaction;

use App\Models\Category;

use App\Models\Expense;

use App\Models\ExpenseDocument;

use App\Models\Payment;

use App\Support\CurrentApartment;

use App\Support\ExpensePaymentGuard;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Str;

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

            return redirect()->route('subscriber.dashboard');

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



        $filterStartDate = $request->query('start_date');
        $filterEndDate   = $request->query('end_date');
        $filterStatus   = $request->query('status');
        $filterCategory = $request->query('category');
        $filterPeriod   = $request->query('period');

        $filterSearch   = $request->query('search');

        $showImported   = $request->query('show_imported', false);



        $isOwner = $this->isOwnerOf($apartment);



        $expenses = Expense::query()

            ->with(['account', 'categoryRelation'])

            ->when($apartment, fn ($q) => $q->where('apartment_id', $apartment->id))

            ->when($filterSearch, fn ($q) => $q->where(function ($sub) use ($filterSearch) {

                $sub->whereHas('account', fn ($a) => $a->where('name', 'like', '%' . $filterSearch . '%'))

                    ->orWhere('amount', 'like', '%' . $filterSearch . '%')

                    ->orWhere('description', 'like', '%' . $filterSearch . '%');

            }))

            ->when($filterPeriod, function ($q) use ($filterPeriod) {
                $start = \Carbon\Carbon::parse($filterPeriod . '-01')->startOfMonth();
                $end   = \Carbon\Carbon::parse($filterPeriod . '-01')->endOfMonth();

                $q->whereBetween('period_month', [$start->toDateString(), $end->toDateString()]);
            })

            ->when($filterStartDate || $filterEndDate, function ($q) use ($filterStartDate, $filterEndDate) {
                $column = 'DATE(expense_date)';
                if ($filterStartDate && $filterEndDate) {
                    $q->whereRaw("{$column} BETWEEN ? AND ?", [$filterStartDate, $filterEndDate]);
                } elseif ($filterStartDate) {
                    $q->whereRaw("{$column} >= ?", [$filterStartDate]);
                } elseif ($filterEndDate) {
                    $q->whereRaw("{$column} <= ?", [$filterEndDate]);
                }
            })

            ->when($filterStatus === 'paid',    fn ($q) => $q->where('is_paid', true))

            ->when($filterStatus === 'overdue', fn ($q) => $q->where('is_paid', false)->where('due_date', '<', now()))

            ->when($filterStatus === 'pending', fn ($q) => $q->where('is_paid', false)->where(function ($q) {
                $q->whereNull('due_date')->orWhere('due_date', '>=', now());
            }))

            ->when($filterCategory, fn ($q) => $q->where('category_id', $filterCategory))

            ->when(! $showImported, fn ($q) => $q->where('is_imported', false))

            ->orderBy($sortBy, $sortDirection)

            ->paginate(25)->withQueryString();



        $categories = \App\Models\Category::query()
            ->when($apartment, fn ($q) => $q->where('apartment_id', $apartment->id))
            ->where('is_active', true)
            ->where('allow_expense', true)
            ->orderedForDisplay()
            ->pluck('name', 'id');



        $cashBoxes = $apartment
            ? CashBox::query()
                ->where('apartment_id', $apartment->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        $filters = compact('filterStartDate', 'filterEndDate', 'filterPeriod', 'filterStatus', 'filterCategory', 'filterSearch');

        // Check if there are any imported expenses for this apartment
        $hasImported = Expense::where('apartment_id', $apartment->id)
            ->where('is_imported', true)
            ->exists();

        return view('expenses.index', compact('expenses', 'apartment', 'sortBy', 'sortDirection', 'filters', 'categories', 'cashBoxes', 'isOwner', 'hasImported', 'showImported'));

    }



    /**

     * Show the form for creating a new resource.

     */

    public function create(CurrentApartment $currentApartment, Request $request)

    {

        $apartment = $currentApartment->getFor(auth()->user());



        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {

            return redirect()->route('subscriber.dashboard');

        }



        if (! $apartment) {

            return redirect()->route('apartments.create');

        }



        $accounts = Account::query()

            ->where('apartment_id', $apartment->id)

            ->where('type', Account::TYPE_SUPPLIER)

            ->where('is_hidden', false)

            ->where('is_active', true)

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

        $submissionToken = (string) Str::uuid();

        return view('expenses.create', compact('apartment', 'accounts', 'categories', 'cashBoxes', 'selectedAccountId', 'accountCategoryMap', 'submissionToken'));

    }



    /**

     * Store a newly created resource in storage.

     */

    public function store(Request $request, CurrentApartment $currentApartment)

    {

        $apartment = $currentApartment->getFor($request->user());



        if (! $apartment && $currentApartment->hasAvailableFor($request->user())) {

            return redirect()->route('subscriber.dashboard');

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

                    ->where('is_active', true)

                    ->where('allow_expense', true),

            ],

            'description' => ['nullable', 'string', 'max:255'],

            'amount' => ['required', 'numeric', 'min:0.01'],

            'expense_date' => ['required', 'date'],

            'due_date' => ['nullable', 'date'],

            'period_month' => ['required', 'date_format:Y-m'],

            'is_paid' => ['nullable', 'boolean'],

            'document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],

            'submission_token' => ['nullable', 'uuid'],

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

        if (! empty($validated['submission_token'])) {
            $submissionKey = sprintf(
                'expense-submission:%d:%d:%s',
                $request->user()->id,
                $apartment->id,
                $validated['submission_token'],
            );

            if (! Cache::add($submissionKey, true, now()->addDay())) {
                return redirect()->route('expenses.index')
                    ->with('error', 'Bu gider formu daha önce gönderildi. İkinci kayıt oluşturulmadı.');
            }
        }



        $category = Category::findOrFail($validated['category_id']);

        // Hesap seçilmediyse otomatik gizli tedarikçi hesabı oluştur
        if (empty($validated['account_id'])) {
            $accountCode = $this->generateTdrCode($apartment);
            $validated['account_id'] = Account::create([
                'apartment_id' => $apartment->id,
                'type' => Account::TYPE_SUPPLIER,
                'name' => $accountCode,
                'is_active' => true,
                'is_hidden' => true,
                'account_opening_date' => $validated['expense_date'],
            ])->id;
        }



        $expense = null;

        DB::transaction(function () use ($apartment, $validated, $category, $request, $isPaid, &$expense) {

            $expense = Expense::create([

                'apartment_id' => $apartment->id,

                'account_id' => $validated['account_id'],

                'category_id' => $category->id,

                'category' => $category->name,

                'description' => $validated['description'] ?? null,

                'amount' => $validated['amount'],

                'paid_amount' => 0,

                'remaining_amount' => $validated['amount'],

                'expense_date' => $validated['expense_date'],

                'due_date' => $validated['due_date'] ?? null,

                'period_month' => $validated['period_month'].'-01',

                'is_paid' => $isPaid,

            ]);



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



            if ($isPaid) {

                $paymentDescription = ($validated['description'] ?? 'Gider').' Ödemesi';

                $payment = Payment::create([

                    'apartment_id' => $apartment->id,

                    'account_id' => $validated['account_id'],

                    'amount' => $validated['amount'],

                    'unallocated_amount' => 0,

                    'payment_date' => $validated['payment_date'],

                    'description' => $paymentDescription,

                ]);

                CashTransaction::create([

                    'apartment_id' => $apartment->id,

                    'cash_box_id' => $validated['cash_box_id'],

                    'account_id' => $validated['account_id'],

                    'expense_id' => $expense->id,

                    'payment_id' => $payment->id,

                    'category_id' => $category->id,

                    'type' => 'expense',

                    'description' => $paymentDescription,

                    'amount' => $validated['amount'],

                    'transaction_date' => $validated['payment_date'],

                    'is_active' => true,

                ]);

                AccountTransaction::create([

                    'apartment_id' => $apartment->id,

                    'account_id' => $validated['account_id'],

                    'transactionable_type' => Payment::class,

                    'transactionable_id' => $payment->id,

                    'type' => 'debit',

                    'description' => $paymentDescription,

                    'amount' => $validated['amount'],

                    'transaction_date' => $validated['payment_date'],

                ]);

                $payment->allocations()->create([

                    'expense_id' => $expense->id,

                    'amount' => $validated['amount'],

                ]);

                $expense->update([

                    'paid_amount' => $validated['amount'],

                    'remaining_amount' => 0,

                ]);

            }

        });



        if ($request->hasFile('document') && $expense) {

            $this->storeDocumentForExpense($request, $expense);

        }



        return redirect()->route('expenses.index')->with('status', 'Gider kaydı oluşturuldu.');

    }



    /**

     * Display the specified resource.

     */

    public function show(string $id)

    {

        $expense = $this->findExpense($id);

        $expense->load([
            'paymentAllocations.payment' => fn ($q) => $q->withCount('allocations'),
            'documents',
            'expensePlan',
        ]);

        return view('expenses.show', compact('expense'));

    }



    /**

     * Show the form for editing the specified resource.

     */

    public function edit(string $id)

    {

        $expense = $this->findExpense($id);

        $accounts = $this->supplierAccounts($expense->apartment_id, $expense->account_id);

        $categories = $this->categories($expense->apartment_id, Category::TYPE_EXPENSE, $expense->category_id);



        return view('expenses.edit', compact('expense', 'accounts', 'categories'));

    }



    /**

     * Update the specified resource in storage.

     */

    public function update(Request $request, string $id)

    {

        $expense = $this->findExpense($id);

        $validated = $this->validateExpense($request, $expense->apartment_id, $expense->category_id);

        $newAccountId = $validated['account_id'] ?? null;

        DB::transaction(function () use ($expense, $validated, $request, $newAccountId) {

            $expense->update([

                'account_id' => $newAccountId,

                'category_id' => $validated['category_id'],

                'category' => Category::withTrashed()->findOrFail($validated['category_id'])->name,

                'description' => $validated['description'] ?? null,

                'amount' => $validated['amount'],

                'expense_date' => $validated['expense_date'],

                'due_date' => $validated['due_date'] ?? null,

                'period_month' => $validated['period_month'].'-01',

                'is_paid' => $request->boolean('is_paid'),

            ]);

            // Gider (credit) muhasebe kaydını yeni hesap/tutar/tarih ile güncelle
            $expense->transactions()
                ->where('transactionable_type', Expense::class)
                ->where('type', 'credit')
                ->update([
                    'account_id' => $newAccountId,
                    'amount' => $validated['amount'],
                    'description' => $validated['description'] ?? 'Gider kaydı',
                    'transaction_date' => $validated['expense_date'],
                ]);

        });

        if ($request->hasFile('document')) {

            $this->storeDocumentForExpense($request, $expense);

        }

        $message = $request->hasFile('document')
            ? 'Gider kaydı güncellendi ve doküman yüklendi.'
            : 'Gider kaydı güncellendi.';

        return redirect()->route('expenses.show', $expense)->with('status', $message);

    }



    /**

     * Remove the specified resource from storage.

     */

    public function destroy(string $id)

    {

        $expense = $this->findExpense($id);



        if ($expense->paymentAllocations()->exists()) {

            return redirect()->route('expenses.show', $expense)->with('error', 'Bu giderin ödeme kaydı var. Önce ödemeyi iptal edin.');

        }



        DB::transaction(function () use ($expense) {

            // Muhasebe kayıtlarını sil

            $expense->transactions()->delete();

            // Gideri soft delete ile sil

            $expense->delete();

        });

        // Dosyaları fiziksel olarak sil
        foreach ($expense->documents as $document) {
            Storage::disk('public')->delete($document->file_path);
        }



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

        return view('expenses.payment', compact('expense', 'cashBoxes'));

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

            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],

            'payment_date' => ['required', 'date'],

            'description' => ['nullable', 'string', 'max:255'],

        ]);



        DB::transaction(function () use ($expense, $validated) {
            ExpensePaymentGuard::lockSupplier($expense->account_id, $expense->apartment_id);
            $expense = Expense::where('apartment_id', $expense->apartment_id)
                ->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            $payable = ExpensePaymentGuard::fullAmount($expense);
            if (ExpensePaymentGuard::cents($validated['amount']) !== $payable) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Ödeme tutarı giderin güncel tam tutarıyla eşleşmiyor. Ekranı yenileyin.',
                ]);
            }


            $paymentDescription = $validated['description'] ?? ($expense->description ? $expense->description.' ödemesi' : 'Gider ödemesi');

            $payment = Payment::create([

                'apartment_id' => $expense->apartment_id,

                'account_id' => $expense->account_id,

                'amount' => $validated['amount'],

                'unallocated_amount' => 0,

                'payment_date' => $validated['payment_date'],

                'description' => $paymentDescription,

            ]);

            CashTransaction::create([

                'apartment_id' => $expense->apartment_id,

                'cash_box_id' => $validated['cash_box_id'],

                'account_id' => $expense->account_id,

                'expense_id' => $expense->id,

                'payment_id' => $payment->id,

                'category_id' => $expense->category_id,

                'type' => 'expense',

                'description' => $paymentDescription,

                'amount' => $validated['amount'],

                'transaction_date' => $validated['payment_date'],

                'is_active' => true,

            ]);

            if ($expense->account_id) {

                AccountTransaction::create([

                    'apartment_id' => $expense->apartment_id,

                    'account_id' => $expense->account_id,

                    'transactionable_type' => Payment::class,

                    'transactionable_id' => $payment->id,

                    'type' => 'debit',

                    'description' => $paymentDescription,

                    'amount' => $validated['amount'],

                    'transaction_date' => $validated['payment_date'],

                ]);

            }

            $payment->allocations()->create([

                'expense_id' => $expense->id,

                'amount' => $validated['amount'],

            ]);

            $expense->update([

                'is_paid' => true,

                'paid_amount' => $validated['amount'],

                'remaining_amount' => 0,

            ]);

        });



        return redirect()->route('expenses.index')->with('status', 'Gider ödemesi kasaya işlendi.');

    }



    public function destroyPayment(string $id)

    {

        $expense = $this->findExpense($id);



        if (! $expense->is_paid) {

            return redirect()->route('expenses.show', $expense)->with('error', 'Bu gider zaten ödenmemiş durumda.');

        }



        DB::transaction(function () use ($expense) {
            ExpensePaymentGuard::lockSupplier($expense->account_id, $expense->apartment_id);
            $expense = Expense::where('apartment_id', $expense->apartment_id)
                ->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            abort_unless($expense->is_paid, 409, 'Giderin ödeme durumu değişti. Ekranı yenileyin.');


            $paymentIds = $expense->paymentAllocations()->pluck('payment_id')->unique();

            if ($paymentIds->isNotEmpty()) {

                AccountTransaction::where('transactionable_type', Payment::class)

                    ->whereIn('transactionable_id', $paymentIds)

                    ->delete();

                CashTransaction::whereIn('payment_id', $paymentIds)->delete();

                Payment::whereIn('id', $paymentIds)->delete();

            }

            $expense->transactions()

                ->where('type', 'debit')

                ->delete();

            CashTransaction::where('apartment_id', $expense->apartment_id)

                ->where('expense_id', $expense->id)

                ->where('type', 'expense')

                ->delete();

            $expense->update([

                'is_paid' => false,

                'paid_amount' => 0,

                'remaining_amount' => $expense->amount,

            ]);

        });



        return redirect()->route('expenses.show', $expense)->with('status', 'Ödeme iptal edildi, gider tekrar açık durumuna alındı.');

    }



    private function findExpense(string $id): Expense

    {

        $apartment = app(CurrentApartment::class)->getFor(auth()->user());



        if (! $apartment) {

            abort(404);

        }



        return Expense::query()

            ->with(['account', 'categoryRelation', 'transactions', 'cashTransactions', 'paymentAllocations.payment'])

            ->where('apartment_id', $apartment->id)

            ->findOrFail($id);

    }



    private function supplierAccounts(int $apartmentId, ?int $selectedAccountId = null)

    {

        return Account::query()

            ->where('apartment_id', $apartmentId)

            ->where('type', Account::TYPE_SUPPLIER)

            ->where(fn ($q) => $q->where('is_hidden', false)->orWhere('id', $selectedAccountId))

            ->orderBy('type')

            ->orderBy('name')

            ->get();

    }



    private function validateExpense(Request $request, int $apartmentId, ?int $existingCategoryId = null): array

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

                    ->where(fn ($query) => $query
                        ->where(fn ($fresh) => $fresh->where('is_active', true)->where('allow_expense', true))
                        ->when($existingCategoryId, fn ($query) => $query->orWhere('id', $existingCategoryId))),

            ],

            'description' => ['nullable', 'string', 'max:255'],

            'amount' => ['required', 'numeric', 'min:0.01'],

            'expense_date' => ['required', 'date'],

            'due_date' => ['nullable', 'date'],

            'period_month' => ['required', 'date_format:Y-m'],

            'is_paid' => ['nullable', 'boolean'],

            'document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],

        ]);

    }



    private function categories(int $apartmentId, string $type, ?int $selectedId = null)

    {

        return Category::query()->when($selectedId, fn ($query) => $query->withTrashed())

            ->where('apartment_id', $apartmentId)

            ->where(fn ($query) => $query
                ->where(fn ($fresh) => $fresh->where('is_active', true)->where('allow_expense', true))
                ->when($selectedId, fn ($query) => $query->orWhere('id', $selectedId)))

            ->orderedForDisplay()

            ->get();

    }

    private function generateTdrCode($apartment): string
    {
        $apartmentCode = $apartment->code ?? 'XXXX';
        $year = \Carbon\Carbon::now()->format('y');

        $pattern = 'TDR-' . $apartmentCode . '-' . $year . '-%';

        $lastRef = \App\Models\Account::where('apartment_id', $apartment->id)
            ->where('name', 'like', $pattern)
            ->withTrashed()
            ->orderBy('name', 'desc')
            ->value('name');

        if ($lastRef) {
            $lastNum = (int) substr($lastRef, strrpos($lastRef, '-') + 1);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return sprintf('TDR-%s-%s-%05d', $apartmentCode, $year, $nextNum);
    }

    public function storeDocument(Request $request, string $id)
    {
        $expense = $this->findExpense($id);

        $request->validate([
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $this->storeDocumentForExpense($request, $expense);

        return redirect()->route('expenses.show', $expense)->with('status', 'Doküman eklendi.');
    }

    public function destroyDocument(string $id, ExpenseDocument $document)
    {
        $expense = $this->findExpense($id);

        abort_unless($document->expense_id === $expense->id, 404);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return redirect()->route('expenses.show', $expense)->with('status', 'Doküman silindi.');
    }

    public function downloadDocument(string $id, ExpenseDocument $document)
    {
        $expense = $this->findExpense($id);

        abort_unless($document->expense_id === $expense->id, 404);

        return Storage::disk('public')->download($document->file_path, basename($document->file_path));
    }

    private function storeDocumentForExpense(Request $request, Expense $expense): void
    {
        if (! $request->hasFile('document')) {
            return;
        }

        if (! \App\Support\FeatureGate::allows($expense->apartment, 'documents')) {
            abort(403, 'Belge yükleme ücretli plandadır.');
        }

        $file = $request->file('document');
        $mime = $file->getMimeType();
        $documentType = str_starts_with($mime, 'image/')
            ? ExpenseDocument::TYPE_INVOICE_IMAGE
            : ExpenseDocument::TYPE_INVOICE_PDF;

        foreach ($expense->documents as $document) {
            Storage::disk('public')->delete($document->file_path);
            $document->delete();
        }

        $reference = $expense->reference_number ?: $expense->generateReferenceNumber();
        if (empty($expense->reference_number)) {
            $expense->update(['reference_number' => $reference]);
        }

        $lastSequence = ExpenseDocument::where('expense_id', $expense->id)->withTrashed()->max('sequence') ?? 0;
        $sequence = $lastSequence + 1;

        $filename = sprintf(
            '%s-%02d.%s',
            $reference,
            $sequence,
            $file->guessExtension() ?? $file->getClientOriginalExtension()
        );

        $path = $file->storeAs(
            "expense_documents/{$expense->apartment_id}/{$expense->id}",
            $filename,
            'public'
        );

        ExpenseDocument::create([
            'expense_id' => $expense->id,
            'document_type' => $documentType,
            'sequence' => $sequence,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $mime,
            'size' => $file->getSize(),
        ]);
    }

}



