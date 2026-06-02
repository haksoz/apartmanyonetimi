<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Due;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
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

        $account = $payment->account;
        $isSupplier = $account?->type === Account::TYPE_SUPPLIER;

        if ($isSupplier) {
            $dues = collect();
            $expenses = Expense::query()
                ->where('apartment_id', $apartment->id)
                ->where('account_id', $payment->account_id)
                ->where('is_paid', false)
                ->orderBy('expense_date')
                ->get();
            $hasImportedDues = false;
        } else {
            $dues = Due::query()
                ->where('apartment_id', $apartment->id)
                ->where('account_id', $payment->account_id)
                ->where('remaining_amount', '>', 0)
                ->orderBy('due_date')
                ->orderBy('id')
                ->get();
            $expenses = collect();
            $hasImportedDues = $dues->contains(fn ($due) => $due->is_imported);
        }

        $redirectTo = request('redirect_to');

        return view('payments.allocations.create', compact('payment', 'dues', 'expenses', 'hasImportedDues', 'isSupplier', 'redirectTo'));
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

        $account = $payment->account;
        $isSupplier = $account?->type === Account::TYPE_SUPPLIER;
        $action = $request->input('action', 'manual');
        $redirectTo = $request->input('redirect_to');

        // FIFO: Eskiden yeniye otomatik tahsis
        if ($action === 'fifo') {
            return $this->storeFifo($payment, $isSupplier, $redirectTo);
        }

        if ($isSupplier) {
            $validated = $request->validate([
                'expense_allocations' => ['required', 'array'],
                'expense_allocations.*.expense_id' => [
                    'required',
                    'integer',
                    Rule::exists('expenses', 'id')->where('apartment_id', $apartment->id),
                ],
                'expense_allocations.*.amount' => ['nullable', 'numeric', 'min:0.01'],
            ]);

            $allocations = collect($validated['expense_allocations'])
                ->filter(fn ($a) => isset($a['amount']) && $a['amount'] > 0)
                ->map(fn ($a) => [
                    'expense_id' => (int) $a['expense_id'],
                    'amount' => (float) $a['amount'],
                ])
                ->values();

            if ($allocations->isEmpty()) {
                return back()->withErrors(['allocations' => 'Lütfen en az bir gider için geçerli bir tutar girin.']);
            }

            $totalAmount = round($allocations->sum('amount'), 2);

            if ($totalAmount > round((float) $payment->unallocated_amount + 0.005, 2)) {
                return back()->withErrors(['allocations' => 'Girilen toplam tutar, ödemede kalan bakiyeden büyük olamaz.']);
            }

            DB::transaction(function () use ($allocations, $payment, $totalAmount, $apartment) {
                foreach ($allocations as $alloc) {
                    $expense = Expense::where('apartment_id', $apartment->id)->findOrFail($alloc['expense_id']);

                    if ($expense->account_id !== $payment->account_id) {
                        abort(404);
                    }

                    $payment->allocations()->create([
                        'due_id' => null,
                        'expense_id' => $expense->id,
                        'amount' => $alloc['amount'],
                    ]);

                    // Gider tamamen ödendiyse kapat
                    $alreadyPaid = $payment->allocations()->where('expense_id', $expense->id)->sum('amount');
                    if ($alreadyPaid >= $expense->amount) {
                        $expense->update(['is_paid' => true]);
                    }
                }

                $payment->decrement('unallocated_amount', $totalAmount);
            });
        } else {
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

            $totalAmount = round($allocations->sum('amount'), 2);

            if ($totalAmount > round((float) $payment->unallocated_amount + 0.005, 2)) {
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
        }

        if ($redirectTo) {
            return redirect($redirectTo)->with('status', 'Ödeme başarıyla tahsis edildi.');
        }

        return redirect()->route('dues.index')->with('status', 'Ödeme başarıyla tahsis edildi.');
    }

    public function destroy(CurrentApartment $currentApartment, PaymentAllocation $allocation)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        // Tahsisin bu apartmana ait olduğunu kontrol et
        if ($allocation->payment->apartment_id !== $apartment->id) {
            abort(403);
        }

        DB::transaction(function () use ($allocation) {
            $due = $allocation->due;
            $payment = $allocation->payment;
            $amount = $allocation->amount;

            // Tahsisi sil
            $allocation->delete();

            // Borcun kalan tutarını artır
            $due->remaining_amount += $amount;

            // Borcun durumunu güncelle
            if ($due->remaining_amount >= $due->amount) {
                $due->status = 'unpaid';
            } else {
                $due->status = 'partial';
            }
            $due->save();

            // Ödemenin tahsis edilmemiş tutarını artır
            $payment->increment('unallocated_amount', $amount);
        });

        return back()->with('status', 'Tahsis başarıyla geri alındı.');
    }

    private function storeFifo(Payment $payment, bool $isSupplier, ?string $redirectTo)
    {
        DB::transaction(function () use ($payment, $isSupplier) {
            $remaining = (float) $payment->unallocated_amount;

            if ($remaining <= 0) {
                return;
            }

            if ($isSupplier) {
                $items = Expense::query()
                    ->where('account_id', $payment->account_id)
                    ->where('is_paid', false)
                    ->orderBy('expense_date')
                    ->orderBy('id')
                    ->get();

                foreach ($items as $expense) {
                    if ($remaining <= 0) break;

                    $toAllocate = min($remaining, (float) $expense->amount);

                    $payment->allocations()->create([
                        'due_id' => null,
                        'expense_id' => $expense->id,
                        'amount' => $toAllocate,
                    ]);

                    $remaining -= $toAllocate;

                    if ($toAllocate >= (float) $expense->amount) {
                        $expense->update(['is_paid' => true]);
                    }
                }
            } else {
                $items = Due::query()
                    ->where('account_id', $payment->account_id)
                    ->where('remaining_amount', '>', 0)
                    ->orderBy('due_date')
                    ->orderBy('id')
                    ->get();

                foreach ($items as $due) {
                    if ($remaining <= 0) break;

                    $toAllocate = min($remaining, (float) $due->remaining_amount);

                    $payment->allocations()->create([
                        'due_id' => $due->id,
                        'expense_id' => null,
                        'amount' => $toAllocate,
                    ]);

                    $remaining -= $toAllocate;
                    $due->remaining_amount = max(0, (float) $due->remaining_amount - $toAllocate);
                    $due->status = $due->remaining_amount === 0 ? 'paid' : 'partial';
                    $due->save();
                }
            }

            $used = (float) $payment->unallocated_amount - $remaining;
            if ($used > 0) {
                $payment->decrement('unallocated_amount', $used);
            }
        });

        $msg = 'Eskiden yeniye tahsis tamamlandı.';

        if ($redirectTo) {
            return redirect($redirectTo)->with('status', $msg);
        }

        return redirect()->route('accounts.show', $payment->account_id)->with('status', $msg);
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
