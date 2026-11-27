<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Due;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\DueCollection;
use App\Services\DueAllocationPreview;
use App\Services\DueCollectionGuard;
use App\Services\DueCollectionReplay;
use App\Services\LateCompensationCalculator;
use App\Support\CurrentApartment;
use App\Support\ExpensePaymentGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentAllocationController extends Controller
{
    public function create(CurrentApartment $currentApartment, Payment $payment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        if ($payment->apartment_id !== $apartment->id) {
            abort(404);
        }

        $payment->load('allocations.due', 'account.unit');
        $payment->unallocated_amount = $payment->unallocated_amount ?? max(0, $payment->amount - $payment->allocated_amount);

        $history = app(DueCollectionReplay::class)->applies($apartment->id, $payment->account_id);
        $date = $payment->payment_date->toDateString();
        $dues = Due::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $payment->account_id)
            ->when(! $history, fn ($query) => $query->where('remaining_amount', '>', 0))
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $collection = app(DueCollection::class);
        $payable = $dues->mapWithKeys(fn (Due $due) => [$due->id => LateCompensationCalculator::money(
            $history ? $collection->historicalQuote($due, $date, $payment->id) : $collection->quote($due, $date)
        )]);
        $dues = $dues->filter(fn (Due $due) => $payable[$due->id] > 0)->values();
        $hasImportedDues = $dues->contains(fn ($due) => $due->is_imported);

        return view('payments.allocations.create', compact('payment', 'dues', 'hasImportedDues', 'payable'));
    }

    public function store(Request $request, CurrentApartment $currentApartment, Payment $payment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        if ($payment->apartment_id !== $apartment->id) {
            abort(404);
        }

        $validated = $request->validate([
            'allocations' => ['required', 'array'],
            'allocations.*.due_id' => [
                'required',
                'integer',
                Rule::exists('dues', 'id')->where('apartment_id', $apartment->id),
            ],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $allocations = collect($validated['allocations'])
            ->filter(fn ($allocation) => isset($allocation['amount']) && $allocation['amount'] > 0)
            ->map(fn ($allocation) => [
                'due_id' => (int) $allocation['due_id'],
                'amount' => (float) $allocation['amount'],
            ])
            ->values();

        if ($allocations->isEmpty()) {
            return back()->withErrors(['allocations' => 'Lütfen en az bir borç için geçerli bir tutar girin.']);
        }

        app(DueCollection::class)->manual($apartment->id, $payment->account_id, [$payment->id], $allocations);

        return redirect()->route('accounts.show', $payment->account_id)->with('status', 'Ödeme başarıyla borçlara tahsis edildi.');
    }

    public function supplierCreate(CurrentApartment $currentApartment, Payment $payment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($payment->apartment_id !== $apartment->id) abort(404);

        $payment->load('allocations.expense', 'account.unit');
        $payment->unallocated_amount = $payment->unallocated_amount ?? max(0, $payment->amount - $payment->allocated_amount);

        $expenses = Expense::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $payment->account_id)
            ->where('is_paid', false)
            ->where(fn ($q) => $q->whereNull('remaining_amount')->orWhere('remaining_amount', '>', 0))
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get()
            ->each(fn ($e) => $e->remaining_amount ??= $e->amount);

        $hasImportedExpenses = $expenses->contains(fn ($e) => $e->is_imported);

        return view('payments.allocations.supplier-create', compact('payment', 'expenses', 'hasImportedExpenses'));
    }

    public function supplierStore(Request $request, CurrentApartment $currentApartment, Payment $payment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($payment->apartment_id !== $apartment->id) abort(404);


        $validated = $request->validate([
            'allocations'              => ['required', 'array'],
            'allocations.*.expense_id' => ['required', 'integer', Rule::exists('expenses', 'id')->where('apartment_id', $apartment->id)],
            'allocations.*.amount'     => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $allocations = collect($validated['allocations'])
            ->filter(fn ($a) => isset($a['amount']) && $a['amount'] > 0)
            ->map(fn ($a) => ['expense_id' => (int) $a['expense_id'], 'amount' => (float) $a['amount']])
            ->values();

        if ($allocations->isEmpty()) {
            return back()->withErrors(['allocations' => 'Lütfen en az bir gider için geçerli bir tutar girin.']);
        }

        $totalAmount = $allocations->sum('amount');

        DB::transaction(function () use ($allocations, $payment, $totalAmount) {
            ExpensePaymentGuard::lockSupplier($payment->account_id, $payment->apartment_id);
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $payment->unallocated_amount ??= max(0, $payment->amount - $payment->allocated_amount);
            if ($payment->isDirty()) $payment->save();
            if ($totalAmount > $payment->unallocated_amount + 0.001) {
                throw \Illuminate\Validation\ValidationException::withMessages(['allocations' => 'Ödemenin kullanılabilir bakiyesi yetersiz.']);
            }
            foreach ($allocations->sortBy('expense_id') as $allocation) {
                $expense = Expense::where('apartment_id', $payment->apartment_id)
                    ->whereKey($allocation['expense_id'])->lockForUpdate()->firstOrFail();

                if ($expense->account_id !== $payment->account_id) abort(404);
                $expenseRemaining = $expense->remaining_amount ?? $expense->amount;
                if ($allocation['amount'] > $expenseRemaining + 0.001) {
                    abort(400, 'Tahsis edilen tutar giderin kalan tutarından büyük olamaz.');
                }

                $payment->allocations()->create([
                    'expense_id' => $expense->id,
                    'amount'     => $allocation['amount'],
                ]);

                $expense->paid_amount      = ($expense->paid_amount ?? 0) + $allocation['amount'];
                $expense->remaining_amount = max(0, $expenseRemaining - $allocation['amount']);
                $expense->is_paid          = $expense->remaining_amount <= 0;
                $expense->save();
            }

            $payment->decrement('unallocated_amount', $totalAmount);
        });

        return redirect()->route('accounts.show', $payment->account_id)->with('status', 'Ödeme başarıyla giderlere tahsis edildi.');
    }

    public function multiSupplierCreate(Request $request, CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($account->apartment_id !== $apartment->id) abort(404);

        $paymentIds = array_filter(explode(',', $request->input('payment_ids', '')));
        if (empty($paymentIds)) {
            return redirect()->route('accounts.show', $account)->with('error', 'Lütfen en az bir ödeme seçin.');
        }

        $payments = Payment::where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->whereIn('id', $paymentIds)
            ->where('unallocated_amount', '>', 0)
            ->orderBy('payment_date')
            ->get();

        if ($payments->isEmpty()) {
            return redirect()->route('accounts.show', $account)->with('error', 'Seçili ödemelerde dağıtılabilir bakiye bulunamadı.');
        }

        $totalBudget = $payments->sum('unallocated_amount');

        $expenses = Expense::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->where('is_paid', false)
            ->where(fn ($q) => $q->whereNull('remaining_amount')->orWhere('remaining_amount', '>', 0))
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get()
            ->each(fn ($e) => $e->remaining_amount ??= $e->amount);

        $hasImportedExpenses = $expenses->contains(fn ($e) => $e->is_imported);

        return view('payments.allocations.multi-supplier-create', compact('account', 'payments', 'expenses', 'totalBudget', 'hasImportedExpenses'));
    }

    public function multiSupplierStore(Request $request, CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($account->apartment_id !== $apartment->id) abort(404);

        $validated = $request->validate([
            'payment_ids'              => ['required', 'string'],
            'allocations'              => ['required', 'array'],
            'allocations.*.expense_id' => ['required', 'integer', Rule::exists('expenses', 'id')->where('apartment_id', $apartment->id)],
            'allocations.*.amount'     => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $paymentIds = array_filter(explode(',', $validated['payment_ids']));

        $allocations = collect($validated['allocations'])
            ->filter(fn ($a) => isset($a['amount']) && $a['amount'] > 0)
            ->map(fn ($a) => ['expense_id' => (int) $a['expense_id'], 'amount' => (float) $a['amount']])
            ->values();

        if ($allocations->isEmpty()) {
            return back()->withErrors(['allocations' => 'Lütfen en az bir gider için geçerli bir tutar girin.']);
        }

        $totalAmount = $allocations->sum('amount');
        DB::transaction(function () use ($allocations, $paymentIds, $account, $apartment, $totalAmount) {
            ExpensePaymentGuard::lockSupplier($account->id, $apartment->id);
            $payments = Payment::where('apartment_id', $apartment->id)->where('account_id', $account->id)
                ->whereIn('id', $paymentIds)->orderBy('id')->lockForUpdate()->get();
            if ($totalAmount > $payments->sum('unallocated_amount') + 0.001) {
                throw \Illuminate\Validation\ValidationException::withMessages(['allocations' => 'Ödemelerin kullanılabilir bakiyesi yetersiz.']);
            }
            $payments = $payments->sortBy('payment_date')->values();
            $paymentQueue = $payments->map(fn ($p) => ['model' => $p, 'remaining' => (float) $p->unallocated_amount])->toArray();
            $pIdx = 0;

            foreach ($allocations->sortBy('expense_id') as $allocation) {
                $expense = Expense::where('apartment_id', $apartment->id)->where('account_id', $account->id)
                    ->whereKey($allocation['expense_id'])->lockForUpdate()->firstOrFail();
                if ($allocation['amount'] > ($expense->remaining_amount ?? $expense->amount) + 0.001) {
                    abort(400, 'Tahsis edilen tutar giderin kalan tutarından büyük olamaz.');
                }
                $toAllocate = $allocation['amount'];

                while ($toAllocate > 0.001 && $pIdx < count($paymentQueue)) {
                    $payment   = $paymentQueue[$pIdx]['model'];
                    $available = $paymentQueue[$pIdx]['remaining'];
                    $chunk     = min($toAllocate, $available);

                    if ($chunk > 0.001) {
                        $payment->allocations()->create([
                            'expense_id' => $expense->id,
                            'amount'     => round($chunk, 2),
                        ]);
                        $payment->decrement('unallocated_amount', round($chunk, 2));
                        $paymentQueue[$pIdx]['remaining'] = round($available - $chunk, 2);
                        $toAllocate = round($toAllocate - $chunk, 2);
                    }

                    if ($paymentQueue[$pIdx]['remaining'] <= 0.001) $pIdx++;
                }

                $expRemaining              = $expense->remaining_amount ?? $expense->amount;
                $expense->paid_amount      = ($expense->paid_amount ?? 0) + $allocation['amount'];
                $expense->remaining_amount = max(0, $expRemaining - $allocation['amount']);
                $expense->is_paid          = $expense->remaining_amount <= 0;
                $expense->save();
            }
        });

        return redirect()->route('accounts.show', $account)->with('status', 'Ödemeler başarıyla giderlere tahsis edildi.');
    }

    public function multiCreate(Request $request, CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($account->apartment_id !== $apartment->id) abort(404);

        $paymentIds = array_filter(explode(',', $request->input('payment_ids', '')));
        if (empty($paymentIds)) {
            return redirect()->route('accounts.show', $account)->with('error', 'Lütfen en az bir ödeme seçin.');
        }

        $payments = Payment::where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->whereIn('id', $paymentIds)
            ->where('unallocated_amount', '>', 0)
            ->orderBy('payment_date')
            ->get();

        if ($payments->isEmpty()) {
            return redirect()->route('accounts.show', $account)->with('error', 'Seçili ödemelerde dağıtılabilir bakiye bulunamadı.');
        }

        $totalBudget = $payments->sum('unallocated_amount');

        $history = app(DueCollectionReplay::class)->applies($apartment->id, $account->id);
        $dues = Due::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->when(! $history, fn ($query) => $query->where('remaining_amount', '>', 0))
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $date = $payments->last()->payment_date->toDateString();
        $collection = app(DueCollection::class);
        $payable = $dues->mapWithKeys(fn (Due $due) => [$due->id => LateCompensationCalculator::money(
            $history ? $payments->max(fn (Payment $payment) => $collection->historicalQuote(
                $due, $payment->payment_date->toDateString(), $payment->id
            )) : $collection->quote($due, $date)
        )]);
        $dues = $dues->filter(fn (Due $due) => $payable[$due->id] > 0)->values();
        $hasImportedDues = $dues->contains(fn ($due) => $due->is_imported);

        return view('payments.allocations.multi-create', compact('account', 'payments', 'dues', 'totalBudget', 'hasImportedDues', 'payable', 'history'));
    }

    public function multiPreview(Request $request, CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $this->resolveApartment($currentApartment);
        abort_unless($apartment instanceof \App\Models\Apartment && $account->apartment_id === $apartment->id
            && app(DueCollectionReplay::class)->applies($apartment->id, $account->id), 404);
        $validated = $request->validate([
            'payment_ids' => ['required', 'array', 'min:1'],
            'payment_ids.*' => ['required', 'integer', 'distinct'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.due_id' => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);
        $rows = $this->previewRows($validated['allocations']);

        return response()->json(app(DueAllocationPreview::class)->project($account, array_map('intval', $validated['payment_ids']), $rows));
    }

    private function previewRows(iterable $rows): array
    {
        return collect($rows)->map(fn ($row) => [
            'due_id' => (int) $row['due_id'],
            'amount' => LateCompensationCalculator::money(LateCompensationCalculator::cents($row['amount'])),
        ])->values()->all();
    }

    public function multiStore(Request $request, CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($account->apartment_id !== $apartment->id) abort(404);

        $validated = $request->validate([
            'payment_ids'          => ['required', 'string'],
            'preview_hash' => ['nullable', 'regex:/^[a-f0-9]{64}$/D'],
            'allocations'          => ['required', 'array'],
            'allocations.*.due_id' => ['required', 'integer', Rule::exists('dues', 'id')->where('apartment_id', $apartment->id)],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $paymentIds = array_filter(explode(',', $validated['payment_ids']));

        $allocations = collect($validated['allocations'])
            ->filter(fn ($a) => isset($a['amount']) && $a['amount'] > 0)
            ->map(fn ($a) => ['due_id' => (int) $a['due_id'], 'amount' => (float) $a['amount']])
            ->values();

        if ($allocations->isEmpty()) {
            return back()->withErrors(['allocations' => 'Lütfen en az bir borç için geçerli bir tutar girin.']);
        }

        $ids = array_values(array_map('intval', $paymentIds));
        $rows = $this->previewRows($allocations);
        DB::transaction(function () use ($account, $apartment, $ids, $rows, $validated) {
            $projection = null;
            if (! empty($validated['preview_hash'])) {
                app(DueCollectionGuard::class)->lockAccount($apartment->id, $account->id);
                $projection = app(DueAllocationPreview::class)->project($account, $ids, $rows);
                if (! hash_equals($projection['hash'], $validated['preview_hash'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'allocations' => 'Bakiyeler değişti. Tahsisi yeniden önizleyin.',
                    ]);
                }
            }
            $applied = app(DueCollection::class)->manual($apartment->id, $account->id, $ids, $rows);
            if ($projection !== null) {
                $byPayment = collect($applied)->groupBy('payment_id');
                $payments = Payment::where('apartment_id', $apartment->id)->where('account_id', $account->id)
                    ->whereIn('id', $ids)->get()->keyBy('id');
                foreach ($projection['payments'] as $result) {
                    $parts = $byPayment[$result['id']] ?? collect();
                    if ($parts->sum(fn ($row) => LateCompensationCalculator::cents($row->principal_amount)) !== $result['principal']
                        || $parts->sum(fn ($row) => LateCompensationCalculator::cents($row->late_compensation_amount)) !== $result['compensation']
                        || LateCompensationCalculator::cents($payments[$result['id']]->unallocated_amount) !== $result['unallocated']) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'allocations' => 'Önizleme finansal sonuçla uyuşmadı. Tahsisi yeniden önizleyin.',
                        ]);
                    }
                }
            }
        });

        return redirect()->route('accounts.show', $account)->with('status', 'Ödemeler başarıyla aidatlara tahsis edildi.');
    }

    public function destroy(Request $request, CurrentApartment $currentApartment, Payment $payment, PaymentAllocation $allocation)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($payment->apartment_id !== $apartment->id) abort(404);
        if ($allocation->payment_id !== $payment->id) abort(404);

        $supplier = $payment->account?->type === Account::TYPE_SUPPLIER;
        DB::transaction(function () use ($allocation, $payment, $supplier) {
            if (! $supplier) {
                $guard = app(DueCollectionGuard::class);
                $guard->lockAccount($payment->apartment_id, $payment->account_id);
                $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $replay = app(DueCollectionReplay::class);
                if ($allocation->due_id && $replay->applies($payment->apartment_id, $payment->account_id)) {
                    $replay->remove($payment, [$allocation->id]);

                    return;
                }
                $guard->protectPayment($payment);
            }
            if ($supplier) {
                ExpensePaymentGuard::lockSupplier($payment->account_id, $payment->apartment_id);
                $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $allocation = PaymentAllocation::where('payment_id', $payment->id)->whereKey($allocation->id)->firstOrFail();
                if ($allocation->expense_id) {
                    $allocation->setRelation('expense', Expense::whereKey($allocation->expense_id)->lockForUpdate()->first());
                }
            }
            $allocationAmount = (float) $allocation->amount;

            if ($allocation->due_id && $allocation->due) {
                $due = $allocation->due;
                $newRemaining = min($due->amount, (float) $due->remaining_amount + $allocationAmount);
                $due->remaining_amount = $newRemaining;
                $due->status = $newRemaining >= $due->amount ? 'unpaid' : 'partial';
                $due->save();
            }

            if ($allocation->expense_id && $allocation->expense) {
                $expense = $allocation->expense;
                $newRemaining = (float) $expense->remaining_amount + $allocationAmount;
                $expense->update([
                    'remaining_amount' => $newRemaining,
                    'paid_amount'      => max(0, (float) $expense->paid_amount - $allocationAmount),
                    'is_paid'          => false,
                ]);
            }

            $payment->increment('unallocated_amount', $allocationAmount);
            $allocation->delete();
        });

        $redirectTo = $request->input('redirect_to');

        if ($redirectTo && str_starts_with($redirectTo, url('/'))) {
            return redirect($redirectTo)->with('status', 'Tahsis silindi.');
        }

        return redirect()->route('payments.show', $payment)
            ->with('status', 'Tahsis silindi.');
    }

    public function bulkDestroy(Request $request, CurrentApartment $currentApartment, Payment $payment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        if ($payment->apartment_id !== $apartment->id) {
            abort(404);
        }

        $validated = $request->validate([
            'allocation_ids' => ['required', 'array'],
            'allocation_ids.*' => ['integer', Rule::exists('payment_allocations', 'id')->where('payment_id', $payment->id)],
        ]);

        $supplier = $payment->account?->type === Account::TYPE_SUPPLIER;
        DB::transaction(function () use ($validated, $payment, $supplier) {
            if (! $supplier) {
                $guard = app(DueCollectionGuard::class);
                $guard->lockAccount($payment->apartment_id, $payment->account_id);
                $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $replay = app(DueCollectionReplay::class);
                if ($replay->applies($payment->apartment_id, $payment->account_id)) {
                    $replay->remove($payment, array_map('intval', $validated['allocation_ids']));

                    return;
                }
                $guard->protectPayment($payment);
            }
            if ($supplier) {
                ExpensePaymentGuard::lockSupplier($payment->account_id, $payment->apartment_id);
                $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            }
            $allocations = PaymentAllocation::whereIn('id', $validated['allocation_ids'])->orderBy('expense_id')->get();
            if ($supplier) {
                $lockedExpenses = Expense::where('apartment_id', $payment->apartment_id)
                    ->whereIn('id', $allocations->pluck('expense_id')->filter())
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($allocations as $allocation) {
                    if ($allocation->expense_id) {
                        $allocation->setRelation('expense', $lockedExpenses->get($allocation->expense_id));
                    }
                }
            }
            foreach ($allocations as $allocation) {
                if ($allocation->payment_id !== $payment->id) {
                    continue;
                }

                $allocationAmount = (float) $allocation->amount;

                if ($allocation->due_id && $allocation->due) {
                    $due = $allocation->due;
                    $newRemaining = min($due->amount, (float) $due->remaining_amount + $allocationAmount);
                    $due->remaining_amount = $newRemaining;
                    $due->status = $newRemaining >= $due->amount ? 'unpaid' : 'partial';
                    $due->save();
                }

                if ($allocation->expense_id && $allocation->expense) {
                    $expense = $allocation->expense;
                    $newRemaining = (float) $expense->remaining_amount + $allocationAmount;
                    $expense->update([
                        'remaining_amount' => $newRemaining,
                        'paid_amount' => max(0, (float) $expense->paid_amount - $allocationAmount),
                        'is_paid' => false,
                    ]);
                }

                $payment->increment('unallocated_amount', $allocationAmount);
                $allocation->delete();
            }
        });

        $redirectTo = $request->input('redirect_to');
        if ($redirectTo && str_starts_with($redirectTo, url('/'))) {
            return redirect($redirectTo)->with('status', 'Seçili tahsisler silindi.');
        }

        return redirect()->route('payments.show', $payment)
            ->with('status', 'Seçili tahsisler silindi.');
    }

    private function resolveApartment(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('subscriber.dashboard');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        return $apartment;
    }
}
