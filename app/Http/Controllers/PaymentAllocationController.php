<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Due;
use App\Models\Expense;
use App\Models\Payment;
use App\Support\CurrentApartment;
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

        $payment->load('allocations.due');
        $payment->unallocated_amount = $payment->unallocated_amount ?? max(0, $payment->amount - $payment->allocated_amount);

        $dues = Due::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $payment->account_id)
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $hasImportedDues = $dues->contains(fn ($due) => $due->is_imported);

        return view('payments.allocations.create', compact('payment', 'dues', 'hasImportedDues'));
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

        $payment->unallocated_amount = $payment->unallocated_amount ?? max(0, $payment->amount - $payment->allocated_amount);
        if ($payment->isDirty()) {
            $payment->save();
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

        $totalAmount = $allocations->sum('amount');

        if ($totalAmount > $payment->unallocated_amount) {
            return back()->withErrors(['allocations' => 'Girilen toplam tutar, ödemede kalan bakiyeden büyük olamaz.']);
        }

        DB::transaction(function () use ($allocations, $payment, $totalAmount) {
            foreach ($allocations as $allocation) {
                $due = Due::findOrFail($allocation['due_id']);

                if ($due->account_id !== $payment->account_id) {
                    abort(404);
                }

                if ($allocation['amount'] > $due->remaining_amount) {
                    abort(400, 'Tahsis edilen tutar borcun kalan tutarından büyük olamaz.');
                }

                $payment->allocations()->create([
                    'due_id' => $due->id,
                    'amount' => $allocation['amount'],
                ]);

                $due->remaining_amount = max(0, $due->remaining_amount - $allocation['amount']);
                $due->status = $due->remaining_amount === 0 ? 'paid' : 'partial';
                $due->save();
            }

            $payment->decrement('unallocated_amount', $totalAmount);
        });

        return redirect()->route('dues.index')->with('status', 'Ödeme başarıyla borçlara tahsis edildi.');
    }

    public function supplierCreate(CurrentApartment $currentApartment, Payment $payment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($payment->apartment_id !== $apartment->id) abort(404);

        $payment->load('allocations.expense');
        $payment->unallocated_amount = $payment->unallocated_amount ?? max(0, $payment->amount - $payment->allocated_amount);

        $expenses = Expense::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $payment->account_id)
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

        $payment->unallocated_amount = $payment->unallocated_amount ?? max(0, $payment->amount - $payment->allocated_amount);
        if ($payment->isDirty()) $payment->save();

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

        if ($totalAmount > $payment->unallocated_amount + 0.001) {
            return back()->withErrors(['allocations' => 'Girilen toplam tutar, ödemede kalan bakiyeden büyük olamaz.']);
        }

        DB::transaction(function () use ($allocations, $payment, $totalAmount) {
            foreach ($allocations as $allocation) {
                $expense = Expense::findOrFail($allocation['expense_id']);

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

        $payments = Payment::where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->whereIn('id', $paymentIds)
            ->where('unallocated_amount', '>', 0)
            ->orderBy('payment_date')
            ->get();

        $allocations = collect($validated['allocations'])
            ->filter(fn ($a) => isset($a['amount']) && $a['amount'] > 0)
            ->map(fn ($a) => ['expense_id' => (int) $a['expense_id'], 'amount' => (float) $a['amount']])
            ->values();

        if ($allocations->isEmpty()) {
            return back()->withErrors(['allocations' => 'Lütfen en az bir gider için geçerli bir tutar girin.']);
        }

        $totalAmount = $allocations->sum('amount');
        $totalBudget = $payments->sum('unallocated_amount');

        if ($totalAmount > $totalBudget + 0.001) {
            return back()->withErrors(['allocations' => 'Girilen toplam tutar, ödemelerin toplam bakiyesinden büyük olamaz.']);
        }

        DB::transaction(function () use ($allocations, $payments) {
            $paymentQueue = $payments->map(fn ($p) => ['model' => $p, 'remaining' => (float) $p->unallocated_amount])->toArray();
            $pIdx = 0;

            foreach ($allocations as $allocation) {
                $expense    = Expense::findOrFail($allocation['expense_id']);
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

        $dues = Due::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $hasImportedDues = $dues->contains(fn ($due) => $due->is_imported);

        return view('payments.allocations.multi-create', compact('account', 'payments', 'dues', 'totalBudget', 'hasImportedDues'));
    }

    public function multiStore(Request $request, CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($account->apartment_id !== $apartment->id) abort(404);

        $validated = $request->validate([
            'payment_ids'          => ['required', 'string'],
            'allocations'          => ['required', 'array'],
            'allocations.*.due_id' => ['required', 'integer', Rule::exists('dues', 'id')->where('apartment_id', $apartment->id)],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $paymentIds = array_filter(explode(',', $validated['payment_ids']));

        $payments = Payment::where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->whereIn('id', $paymentIds)
            ->where('unallocated_amount', '>', 0)
            ->orderBy('payment_date')
            ->get();

        $allocations = collect($validated['allocations'])
            ->filter(fn ($a) => isset($a['amount']) && $a['amount'] > 0)
            ->map(fn ($a) => ['due_id' => (int) $a['due_id'], 'amount' => (float) $a['amount']])
            ->values();

        if ($allocations->isEmpty()) {
            return back()->withErrors(['allocations' => 'Lütfen en az bir borç için geçerli bir tutar girin.']);
        }

        $totalAmount = $allocations->sum('amount');
        $totalBudget = $payments->sum('unallocated_amount');

        if ($totalAmount > $totalBudget + 0.001) {
            return back()->withErrors(['allocations' => 'Girilen toplam tutar, ödemelerin toplam bakiyesinden büyük olamaz.']);
        }

        DB::transaction(function () use ($allocations, $payments) {
            // Ödemeleri sırayla tüket — FIFO ödeme sırası
            $paymentQueue = $payments->map(fn ($p) => ['model' => $p, 'remaining' => (float) $p->unallocated_amount])->toArray();
            $pIdx = 0;

            foreach ($allocations as $allocation) {
                $due = Due::findOrFail($allocation['due_id']);
                $toAllocate = $allocation['amount'];

                while ($toAllocate > 0.001 && $pIdx < count($paymentQueue)) {
                    $payment     = $paymentQueue[$pIdx]['model'];
                    $available   = $paymentQueue[$pIdx]['remaining'];
                    $chunk       = min($toAllocate, $available);

                    if ($chunk > 0.001) {
                        $payment->allocations()->create([
                            'due_id' => $due->id,
                            'amount' => round($chunk, 2),
                        ]);
                        $payment->decrement('unallocated_amount', round($chunk, 2));
                        $paymentQueue[$pIdx]['remaining'] = round($available - $chunk, 2);
                        $toAllocate = round($toAllocate - $chunk, 2);
                    }

                    if ($paymentQueue[$pIdx]['remaining'] <= 0.001) {
                        $pIdx++;
                    }
                }

                $due->remaining_amount = max(0, $due->remaining_amount - $allocation['amount']);
                $due->status = $due->remaining_amount <= 0 ? 'paid' : 'partial';
                $due->save();
            }
        });

        return redirect()->route('accounts.show', $account)->with('status', 'Ödemeler başarıyla aidatlara tahsis edildi.');
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
}
