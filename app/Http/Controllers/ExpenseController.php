<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Category;
use App\Models\Expense;
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

        $expenses = Expense::query()
            ->with(['account', 'categoryRelation'])
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->orderBy($sortBy, $sortDirection)
            ->get();

        return view('expenses.index', compact('expenses', 'apartment', 'sortBy', 'sortDirection'));
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
                // Önce gider kaydını oluştur (credit - borçlanma)
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

                // Tedarikçi varsa ödeme kaydı oluştur (debit - borç ödeniyor)
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

        $expense->update([
            'account_id' => $validated['account_id'] ?? null,
            'category_id' => $validated['category_id'],
            'category' => Category::findOrFail($validated['category_id'])->name,
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'expense_date' => $validated['expense_date'],
            'due_date' => $validated['due_date'] ?? null,
            'period_month' => $validated['period_month'].'-01',
            'is_paid' => $request->boolean('is_paid'),
        ]);

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
            CashTransaction::create([
                'apartment_id' => $expense->apartment_id,
                'cash_box_id' => $validated['cash_box_id'],
                'account_id' => $expense->account_id,
                'category_id' => $validated['category_id'],
                'type' => 'expense',
                'description' => $validated['description'] ?? $expense->category.' gider ödemesi',
                'amount' => $validated['amount'],
                'transaction_date' => $validated['payment_date'],
                'is_active' => true,
            ]);

            // Gider tedarikçi hesapla bağlıysa, ödeme muhasebe hareketi oluştur (debit - borç ödeniyor)
            if ($expense->account_id) {
                AccountTransaction::create([
                    'apartment_id' => $expense->apartment_id,
                    'account_id' => $expense->account_id,
                    'transactionable_type' => Expense::class,
                    'transactionable_id' => $expense->id,
                    'type' => 'debit',
                    'description' => ($expense->description ? $expense->description.' ödemesi' : 'Gider ödemesi'),
                    'amount' => $validated['amount'],
                    'transaction_date' => $validated['payment_date'],
                ]);
            }

            $expense->update(['is_paid' => true]);
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
            $expense->transactions()
                ->where('type', 'debit')
                ->delete();

            CashTransaction::where('apartment_id', $expense->apartment_id)
                ->where('type', 'expense')
                ->where('amount', $expense->amount)
                ->when($expense->account_id, fn ($q) => $q->where('account_id', $expense->account_id))
                ->latest()
                ->limit(1)
                ->delete();

            $expense->update(['is_paid' => false]);
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
            ->with(['account', 'categoryRelation', 'transactions'])
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
}



