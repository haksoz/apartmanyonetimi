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
            ->whereIn('type', [Account::TYPE_OWNER, Account::TYPE_TENANT, Account::TYPE_RESIDENT, Account::TYPE_SUPPLIER])
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
                'required',
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

        $account = Account::findOrFail($validated['account_id']);
        $payment = null;

        DB::transaction(function () use ($validated, $apartment, $request, &$payment) {
            $payment = Payment::create([
                'apartment_id' => $apartment->id,
                'account_id' => $validated['account_id'],
                'amount' => $validated['amount'],
                'unallocated_amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'method' => null,
                'description' => $validated['description'] ?? 'Ödeme',
            ]);

            CashTransaction::create([
                'apartment_id' => $apartment->id,
                'cash_box_id' => $validated['cash_box_id'],
                'account_id' => $validated['account_id'],
                'category_id' => null,
                'type' => 'income',
                'description' => $validated['description'] ?? 'Ödeme alındı',
                'amount' => $validated['amount'],
                'transaction_date' => $validated['payment_date'],
                'is_active' => true,
            ]);

            AccountTransaction::create([
                'apartment_id' => $apartment->id,
                'account_id' => $validated['account_id'],
                'transactionable_type' => Payment::class,
                'transactionable_id' => $payment->id,
                'type' => 'credit',
                'description' => $validated['description'] ?? 'Ödeme alındı',
                'amount' => $validated['amount'],
                'transaction_date' => $validated['payment_date'],
            ]);
        });

        if ($validated['action'] === 'allocate') {
            return redirect()->route('payments.allocations.create', $payment)->with('status', 'Ödeme kaydedildi. Şimdi borçlara tahsis edin.');
        }

        return redirect()->route('accounts.show', $account)->with('status', 'Ödeme kaydedildi.');
    }

    public function index(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        $payments = Payment::query()
            ->with('account')
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        return view('payments.index', compact('payments'));
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

        $payment->load(['account', 'allocations.due', 'transactions']);

        return view('payments.show', compact('payment'));
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

            // Cari işlem - alacak (tedarikçiye olan alacağımız azalır)
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
