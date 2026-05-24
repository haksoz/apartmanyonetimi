<?php

namespace App\Http\Controllers;

use App\Models\AccountTransaction;
use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Expense;
use App\Models\Payment;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;

class CashBoxController extends Controller
{
    public function show(string $id, CurrentApartment $currentApartment, Request $request)
    {
        $cashBox = $this->findCashBox($id, $currentApartment);

        if ($cashBox instanceof \Illuminate\Http\RedirectResponse) {
            return $cashBox;
        }

        $allTransactions = CashTransaction::query()
            ->where('cash_box_id', $cashBox->id)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $income  = $allTransactions->where('type', 'income')->sum('amount');
        $expense = $allTransactions->where('type', 'expense')->sum('amount');
        $balance = $income - $expense;

        // Running balance tüm kayıtlar üzerinden hesaplanır
        $running = 0;
        $runningMap = [];
        foreach ($allTransactions as $t) {
            $running += $t->type === 'income' ? $t->amount : -$t->amount;
            $runningMap[$t->id] = $running;
        }

        // AccountTransaction eşleştirmesi — account_id + tutar + tarih ile
        $accountIds = $allTransactions->pluck('account_id')->filter()->unique()->values();
        $accountTxs = $accountIds->isNotEmpty()
            ? AccountTransaction::query()
                ->where('apartment_id', $cashBox->apartment_id)
                ->whereIn('account_id', $accountIds)
                ->get()
                ->groupBy('account_id')
            : collect();

        $detailUrlMap = [];
        foreach ($allTransactions as $t) {
            $detailUrlMap[$t->id] = null;
            if (! $t->account_id) continue;

            $match = ($accountTxs[$t->account_id] ?? collect())
                ->first(fn ($at) =>
                    (string) $at->amount === (string) $t->amount &&
                    $at->transaction_date->toDateString() === $t->transaction_date->toDateString()
                );

            if (! $match) continue;

            if ($match->transactionable_type === Payment::class && $match->transactionable_id) {
                $detailUrlMap[$t->id] = route('payments.show', $match->transactionable_id);
            } elseif ($match->transactionable_type === Expense::class && $match->transactionable_id) {
                $detailUrlMap[$t->id] = route('expenses.show', $match->transactionable_id);
            }
        }

        // Sayfalama için ters sırada paginate
        $transactions = CashTransaction::query()
            ->with(['account', 'category'])
            ->where('cash_box_id', $cashBox->id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        foreach ($transactions as $t) {
            $t->running_balance = $runningMap[$t->id] ?? 0;
            $t->detail_url      = $detailUrlMap[$t->id] ?? null;
        }

        return view('cash.boxes.show', compact('cashBox', 'transactions', 'income', 'expense', 'balance'));
    }

    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        return view('cash.boxes.create', compact('apartment'));
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        $validated = $this->validateCashBox($request);

        CashBox::create([
            'apartment_id' => $apartment->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'iban' => $validated['iban'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('cash.index')->with('status', 'Kasa oluşturuldu.');
    }

    public function edit(string $id, CurrentApartment $currentApartment)
    {
        $cashBox = $this->findCashBox($id, $currentApartment);

        if ($cashBox instanceof \Illuminate\Http\RedirectResponse) {
            return $cashBox;
        }

        return view('cash.boxes.edit', compact('cashBox'));
    }

    public function update(Request $request, string $id, CurrentApartment $currentApartment)
    {
        $cashBox = $this->findCashBox($id, $currentApartment);

        if ($cashBox instanceof \Illuminate\Http\RedirectResponse) {
            return $cashBox;
        }

        $validated = $this->validateCashBox($request);

        $cashBox->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'iban' => $validated['iban'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('cash.index')->with('status', 'Kasa güncellendi.');
    }

    public function destroy(string $id, CurrentApartment $currentApartment)
    {
        $cashBox = $this->findCashBox($id, $currentApartment);

        if ($cashBox instanceof \Illuminate\Http\RedirectResponse) {
            return $cashBox;
        }

        if ($cashBox->transactions()->count() > 0) {
            return redirect()->route('cash.index')->with('error', 'Bu kasada işlem kaydı olduğu için silinemez.');
        }

        $cashBox->delete();

        return redirect()->route('cash.index')->with('status', 'Kasa silindi.');
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

    private function findCashBox(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        return CashBox::query()
            ->where('apartment_id', $apartment->id)
            ->findOrFail($id);
    }

    private function validateCashBox(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
