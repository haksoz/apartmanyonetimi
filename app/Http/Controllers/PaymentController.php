<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Payment;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function create(Request $request, CurrentApartment $currentApartment)
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
            ->whereIn('type', [Account::TYPE_OWNER, Account::TYPE_TENANT, Account::TYPE_SUPPLIER])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $cashBoxes = CashBox::query()
            ->where('apartment_id', $apartment->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedAccountId = $request->query('account_id');

        return view('payments.create', compact('accounts', 'cashBoxes', 'selectedAccountId'));
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor($request->user());

        if (! $apartment && $currentApartment->hasAvailableFor($request->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        $validated = $request->validate([
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('apartment_id', $apartment->id),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'cash_box_id' => [
                'required',
                'integer',
                Rule::exists('cash_boxes', 'id')
                    ->where('apartment_id', $apartment->id)
                    ->where('is_active', true),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'action' => ['required', Rule::in(['save', 'allocate'])],
        ]);

        // If no account specified, use the orphan (Hesapsız) account
        $accountId = $validated['account_id'] ?? null;
        if (!$accountId) {
            $account = $apartment->getOrphanAccount();
        } else {
            $account = Account::findOrFail($accountId);
        }
        $isSupplier = $account->type === Account::TYPE_SUPPLIER;
        $payment = null;

        DB::transaction(function () use ($validated, $apartment, $account, $isSupplier, &$payment) {
            $payment = Payment::create([
                'apartment_id' => $apartment->id,
                'account_id' => $account->id,
                'amount' => $validated['amount'],
                'unallocated_amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'method' => null,
                'description' => $validated['description'] ?? ($isSupplier ? 'Tedarikçi ödemesi' : 'Ödeme'),
            ]);

            CashTransaction::create([
                'apartment_id' => $apartment->id,
                'cash_box_id' => $validated['cash_box_id'],
                'account_id' => $account->id,
                'payment_id' => $payment->id,
                'category_id' => null,
                'type' => $isSupplier ? 'expense' : 'income',
                'description' => $validated['description'] ?? ($isSupplier ? 'Tedarikçi ödemesi' : 'Ödeme alındı'),
                'amount' => $validated['amount'],
                'transaction_date' => $validated['payment_date'],
                'is_active' => true,
            ]);

            AccountTransaction::create([
                'apartment_id' => $apartment->id,
                'account_id' => $account->id,
                'transactionable_type' => Payment::class,
                'transactionable_id' => $payment->id,
                'type' => $isSupplier ? 'debit' : 'credit',
                'description' => $validated['description'] ?? ($isSupplier ? 'Tedarikçi ödemesi' : 'Ödeme alındı'),
                'amount' => $validated['amount'],
                'transaction_date' => $validated['payment_date'],
            ]);
        });

        $redirectTo = $request->input('redirect_to');

        if ($validated['action'] === 'allocate') {
            return redirect()->route('payments.allocations.create', [
                'payment' => $payment,
                'redirect_to' => $redirectTo ?? route('accounts.show', $account),
            ])->with('status', 'Ödeme kaydedildi. Şimdi borçlara tahsis edin.');
        }

        if ($redirectTo) {
            return redirect($redirectTo)->with('status', 'Ödeme kaydedildi.');
        }

        return redirect()->route('accounts.show', $account)->with('status', 'Ödeme kaydedildi.');
    }

    public function index(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        $filter = $request->query('filter');

        $payments = Payment::query()
            ->with('account')
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->when($filter === 'orphan', fn ($query) => $query
                ->whereNull('account_id')
                ->where('unallocated_amount', '>', 0))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        $isOrphanFilter = $filter === 'orphan';

        return view('payments.index', compact('payments', 'isOrphanFilter'));
    }

    public function show(CurrentApartment $currentApartment, Payment $payment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        if ($payment->apartment_id !== $apartment->id) {
            abort(404);
        }

        $payment->load(['account', 'allocations.due', 'transactions', 'cashTransactions']);

        return view('payments.show', compact('payment'));
    }

    public function edit(CurrentApartment $currentApartment, Payment $payment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        if ($payment->apartment_id !== $apartment->id) {
            abort(404);
        }

        $payment->load(['account', 'allocations.due']);

        $cashBoxes = CashBox::query()
            ->where('apartment_id', $apartment->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Ödemenin ait olduğu kasa ID'sini bul (payment_id üzerinden - hem income hem expense destekler)
        $cashTransaction = CashTransaction::where('payment_id', $payment->id)->first();

        $selectedCashBoxId = $cashTransaction ? $cashTransaction->cash_box_id : null;

        return view('payments.edit', compact('payment', 'cashBoxes', 'selectedCashBoxId'));
    }

    public function update(Request $request, CurrentApartment $currentApartment, Payment $payment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment || $payment->apartment_id !== $apartment->id) {
            abort(404);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'cash_box_id' => [
                'required',
                'integer',
                Rule::exists('cash_boxes', 'id')
                    ->where('apartment_id', $apartment->id)
                    ->where('is_active', true),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $payment->load('allocations');
        $allocatedAmount = $payment->allocations->sum('amount');

        if ($validated['amount'] < $allocatedAmount) {
            return back()->withErrors(['amount' => 'Ödeme tutarı tahsis edilen tutardan küçük olamaz. Tahsis edilen: '.number_format($allocatedAmount, 2, ',', '.').' TL'])->withInput();
        }

        DB::transaction(function () use ($payment, $validated, $allocatedAmount) {
            $oldAmount = $payment->amount;

            // Payment'u güncelle
            $payment->update([
                'amount' => $validated['amount'],
                'unallocated_amount' => $validated['amount'] - $allocatedAmount,
                'payment_date' => $validated['payment_date'],
                'description' => $validated['description'] ?? 'Ödeme',
            ]);

            // İlgili account_transaction'ı güncelle
            $transaction = $payment->transactions()->first();
            if ($transaction) {
                $transaction->update([
                    'amount' => $validated['amount'],
                    'transaction_date' => $validated['payment_date'],
                    'description' => $validated['description'] ?? 'Ödeme alındı',
                ]);
            }

            // İlgili cash_transaction'ı güncelle (payment_id ile daha güvenli)
            $cashTransaction = CashTransaction::where('payment_id', $payment->id)->first();

            if ($cashTransaction) {
                $cashTransaction->update([
                    'amount' => $validated['amount'],
                    'transaction_date' => $validated['payment_date'],
                    'description' => $validated['description'] ?? 'Ödeme alındı',
                    'cash_box_id' => $validated['cash_box_id'],
                    'account_id' => $validated['account_id'],
                ]);
            }
        });

        return redirect()->route('payments.show', $payment)->with('status', 'Ödeme kaydı güncellendi.');
    }

    public function destroy(CurrentApartment $currentApartment, Payment $payment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment || $payment->apartment_id !== $apartment->id) {
            abort(404);
        }

        $accountId = $payment->account_id;

        DB::transaction(function () use ($payment) {
            $payment->load('allocations.due');

            foreach ($payment->allocations as $allocation) {
                $due = $allocation->due;
                $due->remaining_amount = min($due->amount, $due->remaining_amount + $allocation->amount);
                $due->status = $due->remaining_amount >= $due->amount ? 'unpaid' : 'partial';
                $due->save();
                $allocation->delete();
            }

            $payment->transactions()->delete();

            CashTransaction::where('apartment_id', $payment->apartment_id)
                ->where('account_id', $payment->account_id)
                ->where('amount', $payment->amount)
                ->where('transaction_date', $payment->payment_date)
                ->where('type', 'income')
                ->delete();

            $payment->delete();
        });

        // Hesaba dön veya ödemeler listesine git
        if ($accountId) {
            return redirect()->route('accounts.show', $accountId)->with('status', 'Ödeme kaydı ve tüm tahsisler silindi.');
        }

        return redirect()->route('payments.index')->with('status', 'Ödeme kaydı ve tüm tahsisler silindi.');
    }

    public function createSupplierRefund(Request $request, CurrentApartment $currentApartment)
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
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $cashBoxes = CashBox::query()
            ->where('apartment_id', $apartment->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedAccountId = $request->query('account_id');

        return view('supplier-refunds.create', compact('accounts', 'cashBoxes', 'selectedAccountId', 'apartment'));
    }

    public function storeSupplierRefund(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor($request->user());

        if (! $apartment && $currentApartment->hasAvailableFor($request->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        $validated = $request->validate([
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('apartment_id', $apartment->id)
                    ->where('type', Account::TYPE_SUPPLIER),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'cash_box_id' => [
                'required',
                'integer',
                Rule::exists('cash_boxes', 'id')
                    ->where('apartment_id', $apartment->id)
                    ->where('is_active', true),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $account = Account::findOrFail($validated['account_id']);

        DB::transaction(function () use ($validated, $apartment) {
            // Kasa işlemi - gelir olarak kaydet
            CashTransaction::create([
                'apartment_id' => $apartment->id,
                'cash_box_id' => $validated['cash_box_id'],
                'account_id' => $validated['account_id'],
                'category_id' => null,
                'type' => 'income',
                'description' => $validated['description'] ?? 'Tedarikçi iadesi',
                'amount' => $validated['amount'],
                'transaction_date' => $validated['transaction_date'],
                'is_active' => true,
            ]);

            // Cari işlem - tedarikçi iade etti, debit bakiye azalır (credit)
            AccountTransaction::create([
                'apartment_id' => $apartment->id,
                'account_id' => $validated['account_id'],
                'transactionable_type' => null,
                'transactionable_id' => null,
                'type' => 'credit',
                'description' => $validated['description'] ?? 'Tedarikçi iadesi',
                'amount' => $validated['amount'],
                'transaction_date' => $validated['transaction_date'],
            ]);
        });

        return redirect()->route('accounts.show', $account)->with('status', 'Tedarikçi iadesi kaydedildi.');
    }
}
