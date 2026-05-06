<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Category;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashController extends Controller
{
    public function index(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $apartmentId = $apartment?->id;

        $selectedCashBox = null;
        $cashBoxId = $request->query('cash_box_id');

        if ($cashBoxId) {
            $selectedCashBox = CashBox::query()
                ->where('apartment_id', $apartmentId)
                ->where('is_active', true)
                ->find($cashBoxId);
        }

        $transactions = collect();

        if ($selectedCashBox) {
            $transactions = CashTransaction::query()
                ->with(['account', 'cashBox', 'category'])
                ->where('apartment_id', $apartmentId)
                ->where('cash_box_id', $selectedCashBox->id)
                ->latest('transaction_date')
                ->latest()
                ->get();
        }

        $cashBoxes = CashBox::query()
            ->with(['transactions' => fn ($query) => $query->where('is_active', true)])
            ->when($apartmentId, fn ($query) => $query->where('apartment_id', $apartmentId))
            ->orderBy('name')
            ->get();
        $income = CashTransaction::query()
            ->when($apartmentId, fn ($query) => $query->where('apartment_id', $apartmentId))
            ->where('type', 'income')
            ->where('is_active', true)
            ->sum('amount');
        $expense = CashTransaction::query()
            ->when($apartmentId, fn ($query) => $query->where('apartment_id', $apartmentId))
            ->where('type', 'expense')
            ->where('is_active', true)
            ->sum('amount');
        $balance = $income - $expense;

        return view('cash.index', compact('transactions', 'cashBoxes', 'income', 'expense', 'balance', 'apartment', 'selectedCashBox'));
    }

    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        $accounts = Account::query()
            ->where('apartment_id', $apartment->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $cashBoxes = CashBox::query()
            ->where('apartment_id', $apartment->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = $this->categories($apartment->id);

        return view('cash.create', compact('apartment', 'accounts', 'cashBoxes', 'categories'));
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        $validated = $this->validateCashTransaction($request, $apartment->id);

        CashTransaction::create([
            'apartment_id' => $apartment->id,
            'cash_box_id' => $validated['cash_box_id'],
            'account_id' => $validated['account_id'] ?? null,
            'category_id' => $validated['category_id'],
            'type' => $validated['type'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'transaction_date' => $validated['transaction_date'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('cash.index')->with('status', 'Kasa hareketi oluşturuldu.');
    }

    public function show(string $id, CurrentApartment $currentApartment)
    {
        $transaction = $this->findCashTransaction($id, $currentApartment);

        if ($transaction instanceof \Illuminate\Http\RedirectResponse) {
            return $transaction;
        }

        return view('cash.show', compact('transaction'));
    }

    public function edit(string $id, CurrentApartment $currentApartment)
    {
        $transaction = $this->findCashTransaction($id, $currentApartment);

        if ($transaction instanceof \Illuminate\Http\RedirectResponse) {
            return $transaction;
        }

        $accounts = Account::query()
            ->where('apartment_id', $transaction->apartment_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $cashBoxes = CashBox::query()
            ->where('apartment_id', $transaction->apartment_id)
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $transaction->cash_box_id))
            ->orderBy('name')
            ->get();
        $categories = $this->categories($transaction->apartment_id, $transaction->category_id);

        return view('cash.edit', compact('transaction', 'accounts', 'cashBoxes', 'categories'));
    }

    public function update(Request $request, string $id, CurrentApartment $currentApartment)
    {
        $transaction = $this->findCashTransaction($id, $currentApartment);

        if ($transaction instanceof \Illuminate\Http\RedirectResponse) {
            return $transaction;
        }

        $validated = $this->validateCashTransaction($request, $transaction->apartment_id);

        $transaction->update([
            'account_id' => $validated['account_id'] ?? null,
            'cash_box_id' => $validated['cash_box_id'],
            'category_id' => $validated['category_id'],
            'type' => $validated['type'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'transaction_date' => $validated['transaction_date'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('cash.show', $transaction)->with('status', 'Kasa hareketi güncellendi.');
    }

    public function destroy(string $id, CurrentApartment $currentApartment)
    {
        $transaction = $this->findCashTransaction($id, $currentApartment);

        if ($transaction instanceof \Illuminate\Http\RedirectResponse) {
            return $transaction;
        }

        $transaction->delete();

        return redirect()->route('cash.index')->with('status', 'Kasa hareketi silindi.');
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

    private function findCashTransaction(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        return CashTransaction::query()
            ->with(['account', 'cashBox', 'category'])
            ->where('apartment_id', $apartment->id)
            ->findOrFail($id);
    }

    private function validateCashTransaction(Request $request, int $apartmentId): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'cash_box_id' => [
                'required',
                'integer',
                Rule::exists('cash_boxes', 'id')->where('apartment_id', $apartmentId),
            ],
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('apartment_id', $apartmentId),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('apartment_id', $apartmentId)
                    ->where('is_active', true),
            ],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function categories(int $apartmentId, ?int $selectedId = null)
    {
        return Category::query()
            ->where('apartment_id', $apartmentId)
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $selectedId))
            ->orderBy('name')
            ->get();
    }
}
