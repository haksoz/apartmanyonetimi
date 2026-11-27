<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Payment;
use App\Services\DueCollectionGuard;
use App\Services\DueCollectionReplay;
use App\Services\LateCompensationCalculator;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CashController extends Controller
{
    public function index(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('subscriber.dashboard');
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
                ->with(['account', 'cashBox'])
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


        return view('cash.create', compact('apartment', 'accounts', 'cashBoxes'));
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

        return view('cash.edit', compact('transaction', 'accounts', 'cashBoxes'));
    }

    public function update(Request $request, string $id, CurrentApartment $currentApartment)
    {
        $transaction = $this->findCashTransaction($id, $currentApartment);

        if ($transaction instanceof \Illuminate\Http\RedirectResponse) {
            return $transaction;
        }

        $validated = $this->validateCashTransaction($request, $transaction->apartment_id);

        // Eğer bu kasa hareketi bir ödemeye/tahsilata bağlıysa, tahsis kontrolü yap
        if ($transaction->payment_id) {
            $payment = Payment::with('allocations')->find($transaction->payment_id);
            if ($payment) {
                $allocatedAmount = $payment->allocations->sum('amount');
                if (! app(DueCollectionReplay::class)->applies($payment->apartment_id, $payment->account_id)
                    && $validated['amount'] < $allocatedAmount) {
                    return back()->withErrors(['amount' => 'Tutar tahsis edilen tutardan küçük olamaz. Tahsis edilen: '.number_format($allocatedAmount, 2, ',', '.').' TL'])->withInput();
                }
            }
        }

        DB::transaction(function () use ($transaction, $validated, $request) {
            $payment = $this->lockLinkedResidentPayment($transaction);
            if ($payment) {
                $guard = app(DueCollectionGuard::class);
                $replay = app(DueCollectionReplay::class);
                if (($validated['account_id'] ?? null) != $transaction->account_id
                    || $validated['type'] !== $transaction->type
                    || $request->boolean('is_active') !== (bool) $transaction->is_active) {
                    if ($replay->applies($payment->apartment_id, $payment->account_id)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'allocations' => 'Bağlı tahsilatın hesap, tür veya durum bilgisi doğrudan kasa hareketinden değiştirilemez.',
                        ]);
                    }
                    $guard->protectPayment($payment);
                }
                if (LateCompensationCalculator::cents($validated['amount']) !== LateCompensationCalculator::cents($payment->amount)
                    || LateCompensationCalculator::day($validated['transaction_date'])->toDateString() !== $payment->payment_date->toDateString()) {
                    if ($replay->applies($payment->apartment_id, $payment->account_id)) {
                        $replay->editPayment($payment, [
                            'amount' => $validated['amount'], 'payment_date' => $validated['transaction_date'],
                            'cash_box_id' => $validated['cash_box_id'], 'description' => $validated['description'],
                        ]);

                        return;
                    }
                    $guard->protectPayment($payment);
                }
            }
            $transaction->update([
                'account_id' => $validated['account_id'] ?? null,
                'cash_box_id' => $validated['cash_box_id'],
                'type' => $validated['type'],
                'description' => $validated['description'],
                'amount' => $validated['amount'],
                'transaction_date' => $validated['transaction_date'],
                'is_active' => $request->boolean('is_active'),
            ]);

            // İlişkili Payment'i senkronize güncelle (eğer varsa)
            if ($transaction->payment_id) {
                $payment = Payment::find($transaction->payment_id);
                if ($payment) {
                    $allocatedAmount = $payment->allocations()->sum('amount');
                    $payment->update([
                        'amount' => $validated['amount'],
                        'unallocated_amount' => $validated['amount'] - $allocatedAmount,
                        'payment_date' => $validated['transaction_date'],
                        'description' => $validated['description'] ?? $payment->description,
                    ]);

                    // Account transaction'ı da güncelle
                    $accountTransaction = $payment->transactions()->first();
                    if ($accountTransaction) {
                        $accountTransaction->update([
                            'amount' => $validated['amount'],
                            'transaction_date' => $validated['transaction_date'],
                            'description' => $validated['description'] ?? $accountTransaction->description,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('cash.show', $transaction)->with('status', 'Kasa hareketi güncellendi.');
    }

    public function destroy(string $id, CurrentApartment $currentApartment)
    {
        $transaction = $this->findCashTransaction($id, $currentApartment);

        if ($transaction instanceof \Illuminate\Http\RedirectResponse) {
            return $transaction;
        }

        DB::transaction(function () use ($transaction) {
            if ($payment = $this->lockLinkedResidentPayment($transaction)) {
                if (app(DueCollectionReplay::class)->applies($payment->apartment_id, $payment->account_id)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'allocations' => 'Bağlı kasa girişini kaldırmak için tahsilatı iptal edin.',
                    ]);
                }
                app(DueCollectionGuard::class)->protectPayment($payment);
            }
            $transaction->delete();
        });

        return redirect()->route('cash.index')->with('status', 'Kasa hareketi silindi.');
    }

    private function lockLinkedResidentPayment(CashTransaction $transaction): ?Payment
    {
        $payment = $transaction->payment;
        if (! $payment || $payment->account?->type === Account::TYPE_SUPPLIER) {
            return null;
        }

        app(DueCollectionGuard::class)->lockAccount($transaction->apartment_id, $payment->account_id);

        return Payment::where('apartment_id', $transaction->apartment_id)->lockForUpdate()->findOrFail($payment->id);
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

    private function findCashTransaction(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        return CashTransaction::query()
            ->with(['account', 'cashBox', 'expense', 'payment'])
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
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

}
