<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Category;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\TenantAssignment;
use App\Models\Unit;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DueController extends Controller
{
    public function index(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $sortBy = $request->query('sort_by', 'created_at');
        $sortDirection = $request->query('sort_direction', 'desc');
        $validSortColumns = ['created_at', 'unit_id', 'due_date', 'amount', 'status'];

        if (! in_array($sortBy, $validSortColumns)) {
            $sortBy = 'created_at';
        }
        if (! in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $filterPeriod  = $request->query('period');
        $filterStatus  = $request->query('status');
        $filterSource  = $request->query('source');
        $filterBatchId = $request->query('batch_id');
        $filterSearch  = $request->query('search');
        $filterUnitId  = $request->query('unit_id');
        $filterAccountType = $request->query('account_type');
        $showImported = $request->boolean('show_imported', false);

        $isOwner = $this->isOwnerOf($apartment);

        $memberAccountIds = ! $isOwner && $apartment
            ? Account::where('apartment_id', $apartment->id)
                ->where('user_id', auth()->id())
                ->pluck('id')
            : null;

        $dues = Due::query()
            ->with(['account', 'unit', 'category', 'batch.plan'])
            ->when($apartment, fn ($q) => $q->where('dues.apartment_id', $apartment->id))
            ->when($memberAccountIds, fn ($q) => $q->whereIn('account_id', $memberAccountIds))
            ->when($filterSearch, fn ($q) => $q->where(function ($sub) use ($filterSearch) {
                $sub->whereHas('account', fn ($a) => $a->where('name', 'like', '%' . $filterSearch . '%'))
                    ->orWhereHas('unit',    fn ($u) => $u->where('unit_no', 'like', '%' . $filterSearch . '%'))
                    ->orWhere('description', 'like', '%' . $filterSearch . '%');
            }))
            ->when($filterPeriod,  fn ($q) => $q->where('period', $filterPeriod))
            ->when($filterStatus,  fn ($q) => $q->where('status', $filterStatus))
            ->when($filterBatchId, fn ($q) => $q->where('due_batch_id', $filterBatchId))
            ->when($filterUnitId,  fn ($q) => $q->where('unit_id', $filterUnitId))
            ->when($filterAccountType, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('type', $filterAccountType)))
            ->when($filterSource === 'plan',   fn ($q) => $q->whereHas('batch', fn ($b) => $b->whereNotNull('due_plan_id')))
            ->when($filterSource === 'batch',  fn ($q) => $q->whereHas('batch', fn ($b) => $b->whereNull('due_plan_id')))
            ->when($filterSource === 'manual', fn ($q) => $q->whereNull('due_batch_id'))
            ->when(! $showImported, fn ($q) => $q->where('is_imported', false));

        if ($sortBy === 'unit_id') {
            $dues->orderByRaw('unit_id IS NULL, unit_id ' . $sortDirection);
        } elseif ($sortBy === 'created_at') {
            $dues->orderByRaw('COALESCE(created_at_manual, created_at) ' . $sortDirection);
        } else {
            $dues->orderBy($sortBy, $sortDirection);
        }

        $dues = $dues->paginate(25)->withQueryString();

        $activePlans = $apartment
            ? \App\Models\DuePlan::query()
                ->with('category:id,name')
                ->where('apartment_id', $apartment->id)
                ->where('is_active', true)
                ->orderBy('year')
                ->orderBy('name')
                ->get(['id', 'name', 'year', 'category_id'])
            : collect();

        $units = $apartment
            ? Unit::where('apartment_id', $apartment->id)->orderBy('unit_no')->get(['id', 'unit_no'])
            : collect();

        $filters = compact('filterPeriod', 'filterStatus', 'filterSource', 'filterBatchId', 'filterSearch', 'filterUnitId', 'filterAccountType', 'showImported');

        $hasImported = Due::query()
            ->when($apartment, fn ($q) => $q->where('dues.apartment_id', $apartment->id))
            ->when($memberAccountIds, fn ($q) => $q->whereIn('account_id', $memberAccountIds))
            ->where('is_imported', true)
            ->exists();

        return view('dues.index', compact('dues', 'apartment', 'sortBy', 'sortDirection', 'activePlans', 'filters', 'isOwner', 'units', 'showImported', 'hasImported'));
    }

    public function create(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        if (! $this->isOwnerOf($apartment)) {
            abort(403, 'Bu işlem için yönetici yetkisi gereklidir.');
        }

        $categories = Category::query()
            ->where('apartment_id', $apartment->id)
            ->where(fn ($query) => $query->where('type', Category::TYPE_INCOME)->orWhere('type', Category::TYPE_ALL))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $expenseCategories = Category::query()
            ->where('apartment_id', $apartment->id)
            ->where(fn ($query) => $query->where('type', Category::TYPE_EXPENSE)->orWhere('type', Category::TYPE_ALL))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $accounts = Account::query()
            ->where('accounts.apartment_id', $apartment->id)
            ->whereIn('accounts.type', [Account::TYPE_OWNER, Account::TYPE_TENANT])
            ->with('unit')
            ->leftJoin('units', 'accounts.unit_id', '=', 'units.id')
            ->orderByRaw('units.unit_no IS NULL, CAST(units.unit_no AS UNSIGNED)')
            ->orderBy('accounts.name')
            ->select('accounts.*')
            ->get();

        $selectedAccountId = $request->query('account_id');

        return view('dues.create', compact('apartment', 'categories', 'expenseCategories', 'accounts', 'selectedAccountId'));
    }

    public function createBatch(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        $categories = Category::query()
            ->where('apartment_id', $apartment->id)
            ->where(fn ($query) => $query->where('type', Category::TYPE_INCOME)->orWhere('type', Category::TYPE_ALL))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $expenseCategories = Category::query()
            ->where('apartment_id', $apartment->id)
            ->where(fn ($query) => $query->where('type', Category::TYPE_EXPENSE)->orWhere('type', Category::TYPE_ALL))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $unitsCollection = Unit::query()
            ->where('apartment_id', $apartment->id)
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'block', 'square_meters', 'share_coefficient']);

        $units = $unitsCollection->count();

        $unitsData = $unitsCollection->map(fn ($u) => [
            'label' => ($u->block ? $u->block . '/' : '') . $u->unit_no,
            'sqm'   => (float) ($u->square_meters ?? 0),
            'coef'  => (float) ($u->share_coefficient ?? 0),
        ]);

        // Get all expenses grouped by period for JavaScript calculation (SQLite compatible)
        $expensesByPeriod = Expense::query()
            ->where('apartment_id', $apartment->id)
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as period, SUM(amount) as total")
            ->groupBy('period')
            ->pluck('total', 'period')
            ->toArray();

        // Get category-wise expenses for filtered calculation
        $expensesByCategory = [];
        foreach ($expenseCategories as $category) {
            $categoryExpenses = Expense::query()
                ->where('apartment_id', $apartment->id)
                ->where('category_id', $category->id)
                ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as period, SUM(amount) as total")
                ->groupBy('period')
                ->pluck('total', 'period')
                ->toArray();
            $expensesByCategory[$category->id] = $categoryExpenses;
        }

        return view('dues.batch-create', compact('apartment', 'categories', 'expenseCategories', 'units', 'unitsData', 'expensesByPeriod', 'expensesByCategory'));
    }

    public function getExpensesForPeriod(Request $request, CurrentApartment $currentApartment)
    {
        try {
            $apartment = $currentApartment->getFor(auth()->user());

            if (! $apartment) {
                return response()->json(['error' => 'Apartman seçili değil'], 403);
            }

            $period = $request->validate(['period' => 'required|date_format:Y-m'])['period'];
            $periodStart = \Carbon\Carbon::parse($period . '-01')->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            $expenses = Expense::query()
                ->where('apartment_id', $apartment->id)
                ->whereDate('expense_date', '>=', $periodStart)
                ->whereDate('expense_date', '<=', $periodEnd)
                ->with('categoryRelation')
                ->orderBy('expense_date')
                ->get()
                ->map(fn ($expense) => [
                    'id' => $expense->id,
                    'reference_number' => $expense->reference_number,
                    'description' => $expense->description,
                    'amount' => $expense->amount,
                    'expense_date' => $expense->expense_date?->format('d.m.Y'),
                    'category_name' => $expense->categoryRelation?->name ?? '-',
                ]);

            return response()->json($expenses);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        if (! $this->isOwnerOf($apartment)) {
            abort(403, 'Bu işlem için yönetici yetkisi gereklidir.');
        }

        $validated = $request->validate([
            'source_type' => ['required', Rule::in([DueBatch::SOURCE_EXPENSES, DueBatch::SOURCE_MANUAL, DueBatch::SOURCE_INDIVIDUAL, DueBatch::SOURCE_FIXED])],
            'distribution_type' => ['required', Rule::in([DueBatch::DISTRIBUTION_EQUAL, DueBatch::DISTRIBUTION_INDIVIDUAL, DueBatch::DISTRIBUTION_SQUARE_METERS, DueBatch::DISTRIBUTION_SHARE_COEFFICIENT])],
            'target_audience' => ['required', Rule::in(['tenant_priority', 'owner_only'])],
            'period' => ['required', 'date_format:Y-m'],
            'due_date' => ['required', 'date'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('apartment_id', $apartment->id)->where('is_active', true),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'created_at_manual' => ['nullable', 'date'],
            'source_period' => ['required_if:source_type,'.DueBatch::SOURCE_EXPENSES, 'nullable', 'date_format:Y-m'],
            'selected_expense_ids' => ['nullable', 'string'],
            'source_amount' => ['required_if:source_type,'.DueBatch::SOURCE_MANUAL, 'nullable', 'numeric', 'min:0.01'],
            'fixed_amount'  => ['required_if:source_type,'.DueBatch::SOURCE_FIXED,  'nullable', 'numeric', 'min:0.01'],
            'account_id' => [
                'required_if:source_type,'.DueBatch::SOURCE_INDIVIDUAL,
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('apartment_id', $apartment->id),
            ],
            'individual_amount' => ['required_if:source_type,'.DueBatch::SOURCE_INDIVIDUAL, 'nullable', 'numeric', 'min:0.01'],
        ]);

        if ($validated['source_type'] === DueBatch::SOURCE_FIXED && $validated['distribution_type'] !== DueBatch::DISTRIBUTION_EQUAL) {
            $validated['distribution_type'] = DueBatch::DISTRIBUTION_EQUAL;
        }

        if ($validated['source_type'] === DueBatch::SOURCE_INDIVIDUAL && $validated['distribution_type'] !== DueBatch::DISTRIBUTION_INDIVIDUAL) {
            return back()->withErrors(['distribution_type' => 'Birebir borçlandırma için dağıtım yöntemi birebir olmalıdır.'])->withInput();
        }

        if ($validated['source_type'] !== DueBatch::SOURCE_INDIVIDUAL && $validated['distribution_type'] === DueBatch::DISTRIBUTION_INDIVIDUAL) {
            return back()->withErrors(['distribution_type' => 'Birebir dağıtım yalnızca birebir borçlandırma kaynağıyla kullanılabilir.'])->withInput();
        }

        $selectedExpenseIds = collect(explode(',', $request->input('selected_expense_ids', '')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $sourceAmount = $this->sourceAmount($apartment->id, $validated, $selectedExpenseIds);

        if ($sourceAmount <= 0) {
            return back()->withErrors(['source_amount' => 'Borçlandırılacak toplam tutar sıfırdan büyük olmalıdır.'])->withInput();
        }

        DB::transaction(function () use ($apartment, $validated, $selectedExpenseIds, $sourceAmount) {
            $batch = DueBatch::create([
                'apartment_id' => $apartment->id,
                'category_id' => $validated['category_id'],
                'source_type' => $validated['source_type'],
                'distribution_type' => $validated['distribution_type'],
                'target_audience' => $validated['target_audience'],
                'period' => $validated['period'],
                'source_period' => isset($validated['source_period']) ? $validated['source_period'].'-01' : null,
                'category_filter_ids' => $selectedExpenseIds,
                'source_amount' => $sourceAmount,
                'description' => $validated['description'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if ($validated['source_type'] === DueBatch::SOURCE_INDIVIDUAL) {
                $account = Account::query()->where('apartment_id', $apartment->id)->findOrFail($validated['account_id']);
                $this->createDue($batch, $account->unit, $account, $sourceAmount, $validated);

                return;
            }

            if ($validated['source_type'] === DueBatch::SOURCE_FIXED) {
                $perUnitAmount = (float) $validated['fixed_amount'];
                $periodStart   = \Carbon\Carbon::parse($validated['period'].'-01')->startOfMonth();
                $periodEnd     = $periodStart->copy()->endOfMonth();

                $units = Unit::query()
                    ->with(['ownerAccount', 'accounts'])
                    ->where('apartment_id', $apartment->id)
                    ->orderBy('unit_no')
                    ->get();

                foreach ($units as $unit) {
                    $account = $this->getAccountForPeriod($unit, $periodStart, $periodEnd, $validated['target_audience']);
                    if ($account) {
                        $this->createDue($batch, $unit, $account, $perUnitAmount, $validated);
                    }
                }

                return;
            }

            $periodStart = \Carbon\Carbon::parse($validated['period'].'-01')->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            $units = Unit::query()
                ->with(['ownerAccount', 'accounts'])
                ->where('apartment_id', $apartment->id)
                ->orderBy('unit_no')
                ->get();

            $unitAccounts = [];
            foreach ($units as $unit) {
                $account = $this->getAccountForPeriod($unit, $periodStart, $periodEnd, $validated['target_audience']);
                if ($account) {
                    $unitAccounts[] = ['unit' => $unit, 'account' => $account];
                }
            }

            if (empty($unitAccounts)) {
                return;
            }

            $distributionType = $validated['distribution_type'];
            $lastIndex = count($unitAccounts) - 1;

            if ($distributionType === DueBatch::DISTRIBUTION_EQUAL) {
                $amountPerUnit = round($sourceAmount / count($unitAccounts), 2);
                $allocated = 0;
                foreach ($unitAccounts as $index => $item) {
                    $amount = $index === $lastIndex ? round($sourceAmount - $allocated, 2) : $amountPerUnit;
                    $allocated += $amount;
                    $this->createDue($batch, $item['unit'], $item['account'], $amount, $validated);
                }
            } else {
                // Ağırlıklı dağıtım (metrekare veya pay çarpanı)
                $totalWeight = collect($unitAccounts)->sum(function ($item) use ($distributionType) {
                    return $distributionType === DueBatch::DISTRIBUTION_SQUARE_METERS
                        ? (float) ($item['unit']->square_meters ?? 0)
                        : (float) ($item['unit']->share_coefficient ?? 0);
                });

                if ($totalWeight <= 0) {
                    // Ağırlık bilgisi yoksa eşit dağıt
                    $amountPerUnit = round($sourceAmount / count($unitAccounts), 2);
                    $allocated = 0;
                    foreach ($unitAccounts as $index => $item) {
                        $amount = $index === $lastIndex ? round($sourceAmount - $allocated, 2) : $amountPerUnit;
                        $allocated += $amount;
                        $this->createDue($batch, $item['unit'], $item['account'], $amount, $validated);
                    }
                } else {
                    $allocated = 0;
                    foreach ($unitAccounts as $index => $item) {
                        $weight = $distributionType === DueBatch::DISTRIBUTION_SQUARE_METERS
                            ? (float) ($item['unit']->square_meters ?? 0)
                            : (float) ($item['unit']->share_coefficient ?? 0);
                        $amount = $index === $lastIndex
                            ? round($sourceAmount - $allocated, 2)
                            : round($sourceAmount * $weight / $totalWeight, 2);
                        $allocated += $amount;
                        $this->createDue($batch, $item['unit'], $item['account'], $amount, $validated);
                    }
                }
            }
        });

        return redirect()->route('dues.index')->with('status', 'Borçlandırma oluşturuldu.');
    }

    public function show(CurrentApartment $currentApartment, Due $due)
    {
        if ($response = $this->authorizeDue($currentApartment, $due)) {
            return $response;
        }

        $due->load(['allocations.payment', 'transactions', 'batch.plan']);

        $transferableAccounts = $due->account && $due->account->unit_id
            ? Account::query()
                ->where('apartment_id', $due->apartment_id)
                ->where('unit_id', $due->account->unit_id)
                ->where('id', '!=', $due->account_id)
                ->whereIn('type', [Account::TYPE_OWNER, Account::TYPE_TENANT])
                ->orderBy('name')
                ->get(['id', 'name', 'type'])
            : collect();

        return view('dues.show', compact('due', 'transferableAccounts'));
    }

    public function edit(CurrentApartment $currentApartment, Due $due)
    {
        if ($response = $this->authorizeDue($currentApartment, $due)) {
            return $response;
        }

        $due->load('allocations');
        $units = Unit::where('apartment_id', $due->apartment_id)->orderBy('unit_no')->get();
        $categories = Category::where('apartment_id', $due->apartment_id)->where('is_active', true)->orderBy('name')->get();
        $accounts = Account::where('accounts.apartment_id', $due->apartment_id)
            ->whereIn('accounts.type', [Account::TYPE_OWNER, Account::TYPE_TENANT])
            ->with('unit')
            ->leftJoin('units', 'accounts.unit_id', '=', 'units.id')
            ->orderByRaw('units.unit_no IS NULL, CAST(units.unit_no AS UNSIGNED)')
            ->orderBy('accounts.name')
            ->select('accounts.*')
            ->get();

        return view('dues.edit', compact('due', 'units', 'categories', 'accounts'));
    }

    public function update(Request $request, CurrentApartment $currentApartment, Due $due)
    {
        if ($response = $this->authorizeDue($currentApartment, $due)) {
            return $response;
        }

        $validated = $request->validate([
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('apartment_id', $due->apartment_id)],
            'unit_id'    => ['nullable', 'integer', Rule::exists('units', 'id')->where('apartment_id', $due->apartment_id)],
            'category_id'=> ['nullable', 'integer', Rule::exists('categories', 'id')->where('apartment_id', $due->apartment_id)],
            'period' => ['required', 'date_format:Y-m'],
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'created_at_manual' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($due, $validated) {
            $oldAmount = (float) $due->amount;
            $newAmount = (float) $validated['amount'];
            $remainingAmount = (float) $due->remaining_amount;

            // remaining_amount'ı güncelle (henüz ödeme yoksa yeni amount'a eşitle)
            // Floating point karşılaştırması için tolerans kullan
            $tolerance = 0.01;
            if (abs($remainingAmount - $oldAmount) < $tolerance) {
                $validated['remaining_amount'] = $newAmount;
            } else {
                // Ödeme varsa, kalan tutarı orantılı olarak güncelle
                $paidAmount = $oldAmount - $remainingAmount;
                $validated['remaining_amount'] = max(0, $newAmount - $paidAmount);
            }

            // Due'u güncelle
            $due->update($validated);

            // İlgili account_transaction'ı güncelle
            $transaction = $due->transactions()->first();
            if ($transaction) {
                $transaction->update([
                    'amount' => $newAmount,
                    'transaction_date' => $validated['due_date'],
                    'description' => $validated['description'] ?? $due->category?->name.' borçlandırması',
                ]);
            }
        });

        return redirect()->route('dues.index')->with('status', 'Aidat kaydı güncellendi.');
    }

    public function destroy(CurrentApartment $currentApartment, Due $due)
    {
        if ($response = $this->authorizeDue($currentApartment, $due)) {
            return $response;
        }

        if (in_array($due->status, ['paid', 'partial'])) {
            return redirect()->route('dues.show', $due)->with('error', 'Ödenmiş veya kısmen ödenmiş aidat silinemez. Önce ilgili ödemeleri iptal edin.');
        }

        $due->transactions()->delete();
        $due->delete();

        return redirect()->route('dues.index')->with('status', 'Aidat kaydı silindi.');
    }

    public function bulkDestroy(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $ids = array_filter(explode(',', $request->input('ids', '')));

        if (empty($ids)) {
            return redirect()->route('dues.index')->with('error', 'Silinecek kayıt seçilmedi.');
        }

        $dues = Due::query()
            ->whereIn('id', $ids)
            ->where('apartment_id', $apartment->id)
            ->whereNotIn('status', ['paid', 'partial'])
            ->get();

        $skipped = count($ids) - $dues->count();

        foreach ($dues as $due) {
            $due->transactions()->delete();
            $due->delete();
        }

        $msg = "{$dues->count()} aidat kaydı silindi.";
        if ($skipped > 0) {
            $msg .= " {$skipped} ödenmiş/kısmen ödenmiş kayıt atlandı.";
        }

        return redirect()->back()->with('status', $msg);
    }

    public function createPayment(CurrentApartment $currentApartment, Due $due)
    {
        if ($response = $this->authorizeDue($currentApartment, $due)) {
            return $response;
        }

        $cashBoxes = CashBox::query()
            ->where('apartment_id', $due->apartment_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('dues.payment', compact('due', 'cashBoxes'));
    }

    public function storePayment(Request $request, CurrentApartment $currentApartment, Due $due)
    {
        if ($response = $this->authorizeDue($currentApartment, $due)) {
            return $response;
        }

        $validated = $request->validate([
            'cash_box_id' => [
                'required',
                'integer',
                Rule::exists('cash_boxes', 'id')->where('apartment_id', $due->apartment_id)->where('is_active', true),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($due, $validated) {
            $payment = Payment::create([
                'apartment_id' => $due->apartment_id,
                'account_id' => $due->account_id,
                'amount' => $validated['amount'],
                'unallocated_amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'method' => null,
                'description' => $validated['description'] ?? 'Aidat Tahsilatı',
            ]);

            CashTransaction::create([
                'apartment_id' => $due->apartment_id,
                'cash_box_id' => $validated['cash_box_id'],
                'account_id' => $due->account_id,
                'category_id' => $due->category_id,
                'type' => 'income',
                'description' => $validated['description'] ?? 'Aidat Tahsilatı',
                'amount' => $validated['amount'],
                'transaction_date' => $validated['payment_date'],
                'is_active' => true,
            ]);

            AccountTransaction::create([
                'apartment_id' => $due->apartment_id,
                'account_id' => $due->account_id,
                'transactionable_type' => Payment::class,
                'transactionable_id' => $payment->id,
                'type' => 'credit',
                'description' => $validated['description'] ?? 'Aidat Tahsilatı',
                'amount' => $validated['amount'],
                'transaction_date' => $validated['payment_date'],
            ]);

            $due->remaining_amount = $due->remaining_amount ?: $due->amount;
            $allocationAmount = min($payment->unallocated_amount, $due->remaining_amount);

            if ($allocationAmount > 0) {
                $payment->allocations()->create([
                    'due_id' => $due->id,
                    'amount' => $allocationAmount,
                ]);

                $due->remaining_amount = max(0, $due->remaining_amount - $allocationAmount);
                $due->status = $due->remaining_amount <= 0 ? 'paid' : 'partial';
                $due->save();

                $payment->unallocated_amount = $payment->amount - $allocationAmount;
                $payment->save();
            }
        });

        return redirect()->route('dues.index')->with('status', 'Aidat ödemesi kaydedildi.');
    }

    public function bulkPay(Request $request, CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        abort_unless($account->apartment_id === $apartment->id, 403);

        $validated = $request->validate([
            'due_ids'      => ['required', 'array', 'min:1'],
            'due_ids.*'    => ['integer', Rule::exists('dues', 'id')->where('apartment_id', $apartment->id)],
            'cash_box_id'  => ['required', 'integer', Rule::exists('cash_boxes', 'id')->where('apartment_id', $apartment->id)->where('is_active', true)],
            'payment_date' => ['required', 'date'],
            'description'  => ['nullable', 'string', 'max:255'],
        ]);

        $dues = Due::query()
            ->whereIn('id', $validated['due_ids'])
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->whereIn('status', ['pending', 'overdue', 'partial', 'unpaid'])
            ->get();

        if ($dues->isEmpty()) {
            return back()->withErrors(['due_ids' => 'Seçilen aidatlar bulunamadı veya zaten ödendi.']);
        }

        $totalAmount = $dues->sum('remaining_amount');

        DB::transaction(function () use ($dues, $account, $apartment, $validated, $totalAmount) {
            $description = $validated['description'] ?: 'Çoklu Aidat Tahsilatı';

            $payment = Payment::create([
                'apartment_id'       => $apartment->id,
                'account_id'         => $account->id,
                'amount'             => $totalAmount,
                'unallocated_amount' => $totalAmount,
                'payment_date'       => $validated['payment_date'],
                'method'             => null,
                'description'        => $description,
            ]);

            CashTransaction::create([
                'apartment_id'     => $apartment->id,
                'cash_box_id'      => $validated['cash_box_id'],
                'account_id'       => $account->id,
                'category_id'      => null,
                'type'             => 'income',
                'description'      => $description,
                'amount'           => $totalAmount,
                'transaction_date' => $validated['payment_date'],
                'is_active'        => true,
            ]);

            AccountTransaction::create([
                'apartment_id'         => $apartment->id,
                'account_id'           => $account->id,
                'transactionable_type' => Payment::class,
                'transactionable_id'   => $payment->id,
                'type'                 => 'credit',
                'description'          => $description,
                'amount'               => $totalAmount,
                'transaction_date'     => $validated['payment_date'],
            ]);

            foreach ($dues as $due) {
                $allocationAmount = $due->remaining_amount;

                $payment->allocations()->create([
                    'due_id' => $due->id,
                    'amount' => $allocationAmount,
                ]);

                $due->remaining_amount = 0;
                $due->status = 'paid';
                $due->save();

                $payment->unallocated_amount -= $allocationAmount;
            }

            $payment->save();
        });

        return redirect()->route('accounts.show', $account)->with('status', $dues->count() . ' aidat tahsil edildi.');
    }

    private function authorizeDue(CurrentApartment $currentApartment, Due $due)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        if ($due->apartment_id !== $apartment->id) {
            abort(404);
        }
    }

    private function resolveApartment(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        return $apartment;
    }

    private function sourceAmount(int $apartmentId, array $validated, array $selectedExpenseIds): float
    {
        if ($validated['source_type'] === DueBatch::SOURCE_MANUAL) {
            return (float) $validated['source_amount'];
        }

        if ($validated['source_type'] === DueBatch::SOURCE_FIXED) {
            return (float) $validated['fixed_amount'];
        }

        if ($validated['source_type'] === DueBatch::SOURCE_INDIVIDUAL) {
            return (float) $validated['individual_amount'];
        }

        // If specific expenses are selected, sum only those
        if (!empty($selectedExpenseIds)) {
            return (float) Expense::query()
                ->where('apartment_id', $apartmentId)
                ->whereIn('id', $selectedExpenseIds)
                ->sum('amount');
        }

        // Otherwise sum all expenses for the period
        return (float) Expense::query()
            ->where('apartment_id', $apartmentId)
            ->whereDate('period_month', $validated['source_period'].'-01')
            ->sum('amount');
    }

    private function createDue(DueBatch $batch, ?Unit $unit, Account $account, float $amount, array $validated): void
    {
        $due = Due::create([
            'apartment_id' => $batch->apartment_id,
            'due_batch_id' => $batch->id,
            'unit_id' => $unit?->id ?? $account->unit_id,
            'account_id' => $account->id,
            'category_id' => $batch->category_id,
            'period' => $validated['period'],
            'amount' => $amount,
            'remaining_amount' => $amount,
            'due_date' => $validated['due_date'],
            'status' => 'unpaid',
            'description' => $validated['description'] ?? null,
            'created_at_manual' => $validated['created_at_manual'] ?? null,
        ]);

        AccountTransaction::create([
            'apartment_id' => $batch->apartment_id,
            'account_id' => $account->id,
            'transactionable_type' => Due::class,
            'transactionable_id' => $due->id,
            'type' => 'debit',
            'description' => $validated['description'] ?? $batch->category?->name.' borçlandırması',
            'amount' => $amount,
            'transaction_date' => $validated['created_at_manual'] ?? $validated['due_date'],
        ]);
    }

    private function getAccountForPeriod(Unit $unit, \Carbon\Carbon $periodStart, \Carbon\Carbon $periodEnd, string $targetAudience = 'tenant_priority'): ?Account
    {
        // Sadece sahiplere dağıt seçeneğinde direkt sahibi döndür
        if ($targetAudience === 'owner_only') {
            return $unit->ownerAccount;
        }

        // Kiracı öncelikli: önce o dönemde aktif kiracıyı ara
        $tenantAssignment = TenantAssignment::query()
            ->where('unit_id', $unit->id)
            ->where('move_in_date', '<=', $periodEnd)
            ->where(fn ($q) => $q->whereNull('move_out_date')->orWhere('move_out_date', '>=', $periodStart))
            ->with('account')
            ->first();

        if ($tenantAssignment && $tenantAssignment->account) {
            return $tenantAssignment->account;
        }

        // Kiracı yoksa veya dönemde aktif değilse sahibi döndür
        return $unit->ownerAccount;
    }

    public function transfer(CurrentApartment $currentApartment, Due $due, Request $request)
    {
        if ($response = $this->authorizeDue($currentApartment, $due)) {
            return $response;
        }

        if (in_array($due->status, ['paid', 'partial'])) {
            return back()->with('error', 'Ödenmiş veya kısmen ödenmiş aidat devredilemez.');
        }

        if (! $due->account || ! $due->account->unit_id) {
            return back()->with('error', 'Bu aidatın devri için bağlı ünite bilgisi bulunamadı.');
        }

        $validated = $request->validate([
            'target_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('apartment_id', $due->apartment_id)
                    ->where('unit_id', $due->account->unit_id)
                    ->whereIn('type', [Account::TYPE_OWNER, Account::TYPE_TENANT])
                    ->where('id', '!=', $due->account_id),
            ],
        ]);

        $targetAccount = Account::query()
            ->where('apartment_id', $due->apartment_id)
            ->where('id', $validated['target_account_id'])
            ->firstOrFail();

        $fromAccountName = $due->account->name;

        DB::transaction(function () use ($due, $targetAccount, $fromAccountName) {
            $due->update([
                'account_id'  => $targetAccount->id,
                'unit_id'     => $targetAccount->unit_id,
                'description' => ($due->description ? $due->description . ' ' : '') . '[Devir: ' . $fromAccountName . ']',
            ]);

            AccountTransaction::where('transactionable_type', Due::class)
                ->where('transactionable_id', $due->id)
                ->update(['account_id' => $targetAccount->id]);
        });

        return redirect()->route('accounts.show', $targetAccount->id)
            ->with('status', 'Aidat "' . $targetAccount->name . '" hesabına devredildi.');
    }

    public function export(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        $sortBy        = in_array($request->query('sort_by'), ['created_at','unit_id','due_date','amount','status']) ? $request->query('sort_by') : 'created_at';
        $sortDirection = in_array($request->query('sort_direction'), ['asc','desc']) ? $request->query('sort_direction') : 'desc';
        $showImported  = $request->boolean('show_imported', false);

        $dues = Due::query()
            ->with(['account', 'unit', 'category', 'batch.plan'])
            ->when($apartment, fn ($q) => $q->where('dues.apartment_id', $apartment->id))
            ->when($request->query('search'),       fn ($q) => $q->where(function ($s) use ($request) {
                $s->whereHas('account', fn ($a) => $a->where('name', 'like', '%'.$request->query('search').'%'))
                  ->orWhereHas('unit',  fn ($u) => $u->where('unit_no', 'like', '%'.$request->query('search').'%'))
                  ->orWhere('description', 'like', '%'.$request->query('search').'%');
            }))
            ->when($request->query('period'),       fn ($q) => $q->where('period', $request->query('period')))
            ->when($request->query('status'),       fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->query('batch_id'),     fn ($q) => $q->where('due_batch_id', $request->query('batch_id')))
            ->when($request->query('unit_id'),      fn ($q) => $q->where('unit_id', $request->query('unit_id')))
            ->when($request->query('account_type'), fn ($q) => $q->whereHas('account', fn ($a) => $a->where('type', $request->query('account_type'))))
            ->when($request->query('source') === 'plan',   fn ($q) => $q->whereHas('batch', fn ($b) => $b->whereNotNull('due_plan_id')))
            ->when($request->query('source') === 'batch',  fn ($q) => $q->whereHas('batch', fn ($b) => $b->whereNull('due_plan_id')))
            ->when($request->query('source') === 'manual', fn ($q) => $q->whereNull('due_batch_id'))
            ->when(! $showImported, fn ($q) => $q->where('is_imported', false));

        if ($sortBy === 'unit_id') {
            $dues->orderByRaw('unit_id IS NULL, unit_id '.$sortDirection);
        } elseif ($sortBy === 'created_at') {
            $dues->orderByRaw('COALESCE(created_at_manual, created_at) '.$sortDirection);
        } else {
            $dues->orderBy($sortBy, $sortDirection);
        }

        $dues = $dues->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Aidatlar');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Oluşturulma Tarihi: '.now()->format('d.m.Y H:i'));
        $sheet->getStyle('A2')->getFont()->setBold(true);

        $headers = ['Daire', 'Hesap', 'Tip', 'Açıklama', 'Oluşturulma', 'Tutar (TL)', 'Kalan (TL)', 'Durum'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1).'4', $h);
        }
        $sheet->getStyle('A4:H4')->getFont()->setBold(true);
        $sheet->getStyle('A4:H4')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE2E8F0');

        $statusMap = ['paid' => 'Ödendi', 'partial' => 'Kısmi', 'overdue' => 'Gecikti', 'pending' => 'Bekliyor'];
        $row = 5;
        foreach ($dues as $due) {
            $sheet->setCellValue('A'.$row, $due->unit ? str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-');
            $sheet->setCellValue('B'.$row, $due->account?->name ?? '-');
            $sheet->setCellValue('C'.$row, $due->category?->name ?? '-');
            $sheet->setCellValue('D'.$row, $due->description ?? '-');
            $sheet->setCellValue('E'.$row, $due->created_at_manual
                ? \Carbon\Carbon::parse($due->created_at_manual)->format('d.m.Y')
                : $due->created_at->format('d.m.Y'));
            $sheet->setCellValue('F'.$row, $due->amount);
            $sheet->getStyle('F'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->setCellValue('G'.$row, $due->remaining_amount ?? $due->amount);
            $sheet->getStyle('G'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->setCellValue('H'.$row, $statusMap[$due->computed_status] ?? $due->computed_status);
            $row++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'aidatlar_'.now()->format('Ymd_Hi').'.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
