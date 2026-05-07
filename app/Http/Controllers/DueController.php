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

        $dues = Due::query()
            ->with(['account', 'unit', 'category', 'batch'])
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id));

        if ($sortBy === 'unit_id') {
            $dues->orderByRaw('unit_id IS NULL, unit_id ' . $sortDirection);
        } else {
            $dues->orderBy($sortBy, $sortDirection);
        }

        $dues = $dues->get();

        return view('dues.index', compact('dues', 'apartment', 'sortBy', 'sortDirection'));
    }

    public function create(CurrentApartment $currentApartment)
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
        $accounts = Account::query()
            ->where('apartment_id', $apartment->id)
            ->whereIn('type', [Account::TYPE_OWNER, Account::TYPE_TENANT, Account::TYPE_RESIDENT])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('dues.create', compact('apartment', 'categories', 'expenseCategories', 'accounts'));
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        $validated = $request->validate([
            'source_type' => ['required', Rule::in([DueBatch::SOURCE_EXPENSES, DueBatch::SOURCE_MANUAL, DueBatch::SOURCE_INDIVIDUAL])],
            'distribution_type' => ['required', Rule::in([DueBatch::DISTRIBUTION_EQUAL, DueBatch::DISTRIBUTION_INDIVIDUAL])],
            'period' => ['required', 'date_format:Y-m'],
            'due_date' => ['required', 'date'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('apartment_id', $apartment->id)->where('is_active', true),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'source_period' => ['required_if:source_type,'.DueBatch::SOURCE_EXPENSES, 'nullable', 'date_format:Y-m'],
            'category_filter_ids' => ['nullable', 'array'],
            'category_filter_ids.*' => [
                'integer',
                Rule::exists('categories', 'id')->where('apartment_id', $apartment->id)->where('is_active', true),
            ],
            'source_amount' => ['required_if:source_type,'.DueBatch::SOURCE_MANUAL, 'nullable', 'numeric', 'min:0.01'],
            'account_id' => [
                'required_if:source_type,'.DueBatch::SOURCE_INDIVIDUAL,
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('apartment_id', $apartment->id),
            ],
            'individual_amount' => ['required_if:source_type,'.DueBatch::SOURCE_INDIVIDUAL, 'nullable', 'numeric', 'min:0.01'],
        ]);

        if ($validated['source_type'] === DueBatch::SOURCE_INDIVIDUAL && $validated['distribution_type'] !== DueBatch::DISTRIBUTION_INDIVIDUAL) {
            return back()->withErrors(['distribution_type' => 'Birebir borçlandırma için dağıtım yöntemi birebir olmalıdır.'])->withInput();
        }

        if ($validated['source_type'] !== DueBatch::SOURCE_INDIVIDUAL && $validated['distribution_type'] !== DueBatch::DISTRIBUTION_EQUAL) {
            return back()->withErrors(['distribution_type' => 'Bu aşamada toplu borçlandırma için eşit böl dağıtımı destekleniyor.'])->withInput();
        }

        $categoryFilterIds = collect($request->input('category_filter_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $sourceAmount = $this->sourceAmount($apartment->id, $validated, $categoryFilterIds);

        if ($sourceAmount <= 0) {
            return back()->withErrors(['source_amount' => 'Borçlandırılacak toplam tutar sıfırdan büyük olmalıdır.'])->withInput();
        }

        DB::transaction(function () use ($apartment, $validated, $categoryFilterIds, $sourceAmount) {
            $batch = DueBatch::create([
                'apartment_id' => $apartment->id,
                'category_id' => $validated['category_id'],
                'source_type' => $validated['source_type'],
                'distribution_type' => $validated['distribution_type'],
                'period' => $validated['period'],
                'source_period' => isset($validated['source_period']) ? $validated['source_period'].'-01' : null,
                'category_filter_ids' => $categoryFilterIds,
                'source_amount' => $sourceAmount,
                'description' => $validated['description'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if ($validated['source_type'] === DueBatch::SOURCE_INDIVIDUAL) {
                $account = Account::query()->where('apartment_id', $apartment->id)->findOrFail($validated['account_id']);
                $this->createDue($batch, $account->unit, $account, $sourceAmount, $validated);

                return;
            }

            $units = Unit::query()
                ->with(['ownerAccount', 'occupantAccount', 'accounts'])
                ->where('apartment_id', $apartment->id)
                ->orderBy('unit_no')
                ->get()
                ->filter(fn (Unit $unit) => $unit->dueAccount());

            if ($units->isEmpty()) {
                return;
            }

            $amountPerUnit = round($sourceAmount / $units->count(), 2);
            $allocated = 0;
            $lastIndex = $units->count() - 1;

            foreach ($units->values() as $index => $unit) {
                $amount = $index === $lastIndex ? round($sourceAmount - $allocated, 2) : $amountPerUnit;
                $allocated += $amount;
                $this->createDue($batch, $unit, $unit->dueAccount(), $amount, $validated);
            }
        });

        return redirect()->route('dues.index')->with('status', 'Borçlandırma oluşturuldu.');
    }

    public function show(CurrentApartment $currentApartment, Due $due)
    {
        if ($response = $this->authorizeDue($currentApartment, $due)) {
            return $response;
        }

        return view('dues.show', compact('due'));
    }

    public function edit(CurrentApartment $currentApartment, Due $due)
    {
        if ($response = $this->authorizeDue($currentApartment, $due)) {
            return $response;
        }

        return view('dues.edit', compact('due'));
    }

    public function update(Request $request, CurrentApartment $currentApartment, Due $due)
    {
        if ($response = $this->authorizeDue($currentApartment, $due)) {
            return $response;
        }

        $validated = $request->validate([
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['unpaid', 'paid'])],
        ]);

        $due->update($validated);

        return redirect()->route('dues.index')->with('status', 'Aidat kaydı güncellendi.');
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
                'description' => $validated['description'] ?? 'Aidat ödemesi',
            ]);

            CashTransaction::create([
                'apartment_id' => $due->apartment_id,
                'cash_box_id' => $validated['cash_box_id'],
                'account_id' => $due->account_id,
                'category_id' => $due->category_id,
                'type' => 'expense',
                'description' => $validated['description'] ?? 'Aidat ödemesi',
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
                'description' => $validated['description'] ?? 'Aidat ödemesi',
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
                $due->status = $due->remaining_amount === 0 ? 'paid' : 'partial';
                $due->save();

                $payment->unallocated_amount = $payment->amount - $allocationAmount;
                $payment->save();
            }
        });

        return redirect()->route('dues.index')->with('status', 'Aidat ödemesi kaydedildi.');
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

    private function sourceAmount(int $apartmentId, array $validated, array $categoryFilterIds): float
    {
        if ($validated['source_type'] === DueBatch::SOURCE_MANUAL) {
            return (float) $validated['source_amount'];
        }

        if ($validated['source_type'] === DueBatch::SOURCE_INDIVIDUAL) {
            return (float) $validated['individual_amount'];
        }

        return (float) Expense::query()
            ->where('apartment_id', $apartmentId)
            ->whereDate('period_month', $validated['source_period'].'-01')
            ->when($categoryFilterIds, fn ($query) => $query->whereIn('category_id', $categoryFilterIds))
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
        ]);

        AccountTransaction::create([
            'apartment_id' => $batch->apartment_id,
            'account_id' => $account->id,
            'transactionable_type' => Due::class,
            'transactionable_id' => $due->id,
            'type' => 'debit',
            'description' => $validated['description'] ?? $batch->category?->name.' borçlandırması',
            'amount' => $amount,
            'transaction_date' => $validated['due_date'],
        ]);
    }
}
