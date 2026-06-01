<?php

namespace App\Http\Controllers;

use App\Models\Due;
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
            ->orderByDesc('due_date')
            ->get();

        $hasImportedDues = $dues->contains(fn ($due) => $due->is_imported);

        $redirectTo = request('redirect_to');

        return view('payments.allocations.create', compact('payment', 'dues', 'hasImportedDues', 'redirectTo'));
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

        $redirectTo = $request->input('redirect_to');
        if ($redirectTo) {
            return redirect($redirectTo)->with('status', 'Ödeme başarıyla borçlara tahsis edildi.');
        }

        return redirect()->route('dues.index')->with('status', 'Ödeme başarıyla borçlara tahsis edildi.');
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
