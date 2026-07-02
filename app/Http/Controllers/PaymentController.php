<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Due;
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

        $accountDebts = AccountTransaction::query()
            ->selectRaw('account_id, SUM(CASE WHEN type = ? THEN amount ELSE -amount END) as debt', ['debit'])
            ->whereIn('account_id', $accounts->pluck('id'))
            ->groupBy('account_id')
            ->pluck('debt', 'account_id')
            ->map(fn ($debt) => max(0, round((float) $debt, 2)));

        return view('payments.create', compact('accounts', 'cashBoxes', 'selectedAccountId', 'accountDebts'));
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
            'action' => ['required', Rule::in(['save', 'allocate', 'auto_allocate', 'fifo_popup'])],
            'allocations' => ['nullable', 'array'],
            'allocations.*.due_id' => [
                'nullable',
                'integer',
                Rule::exists('dues', 'id')->where('apartment_id', $apartment->id),
            ],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0.01'],
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

        if ($validated['action'] === 'fifo_popup' && ! $isSupplier) {
            $allocations = collect($validated['allocations'] ?? [])
                ->filter(fn ($a) => isset($a['amount']) && (float) $a['amount'] > 0)
                ->map(fn ($a) => ['due_id' => (int) $a['due_id'], 'amount' => (float) $a['amount']])
                ->values();

            if ($allocations->isNotEmpty()) {
                $totalAlloc = $allocations->sum('amount');

                if (round($totalAlloc, 2) > round((float) $payment->unallocated_amount, 2)) {
                    return back()->withErrors(['allocations' => 'Tahsis toplamı ödeme tutarını aşıyor.'])->withInput();
                }

                DB::transaction(function () use ($allocations, $payment, $apartment) {
                    foreach ($allocations as $alloc) {
                        $due = Due::query()
                            ->where('id', $alloc['due_id'])
                            ->where('apartment_id', $apartment->id)
                            ->where('account_id', $payment->account_id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $amount = round($alloc['amount'], 2);

                        if ($amount > round((float) $due->remaining_amount, 2)) {
                            abort(400, 'Tahsis edilen tutar borcun kalan tutarından büyük olamaz.');
                        }

                        $payment->allocations()->create([
                            'due_id' => $due->id,
                            'amount' => $amount,
                        ]);

                        $due->remaining_amount = max(0, $due->remaining_amount - $amount);
                        $due->status = $due->remaining_amount <= 0 ? 'paid' : 'partial';
                        $due->save();

                        $payment->decrement('unallocated_amount', $amount);
                    }
                });
            }

            return redirect()->route('accounts.show', $account)
                ->with('status', 'Ödeme kaydedildi ve aidatlara tahsis edildi.');
        }

        if ($validated['action'] === 'auto_allocate' && ! $isSupplier) {
            $closed = $this->autoAllocateFifo($payment, $account, $apartment);

            return redirect()->route('accounts.show', $account)
                ->with('status', $this->buildAutoAllocateMessage($payment, $closed));
        }

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

    /**
     * Açık aidatları eskiden yeniye (FIFO) otomatik olarak kapatır.
     *
     * @return array<int, array{label: string, amount: float, fully: bool}>
     */
    private function autoAllocateFifo(Payment $payment, Account $account, $apartment): array
    {
        $closed = [];

        DB::transaction(function () use ($payment, $account, $apartment, &$closed) {
            $dues = Due::query()
                ->where('apartment_id', $apartment->id)
                ->where('account_id', $account->id)
                ->where('remaining_amount', '>', 0)
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($dues as $due) {
                $available = round((float) $payment->unallocated_amount, 2);
                if ($available <= 0) {
                    break;
                }

                $amount = min($available, round((float) $due->remaining_amount, 2));
                if ($amount <= 0) {
                    continue;
                }

                $payment->allocateToDue($due, $amount);

                $closed[] = [
                    'label' => trim(($due->description ?: 'Aidat')
                        . ($due->due_date ? ' (' . $due->due_date->format('d.m.Y') . ')' : '')),
                    'amount' => $amount,
                    'fully' => round((float) $due->remaining_amount, 2) <= 0,
                ];
            }
        });

        return $closed;
    }

    /**
     * @param array<int, array{label: string, amount: float, fully: bool}> $closed
     */
    private function buildAutoAllocateMessage(Payment $payment, array $closed): string
    {
        if (empty($closed)) {
            return 'Ödeme kaydedildi ancak kapatılacak açık aidat bulunamadı.';
        }

        $lines = array_map(function ($item) {
            return $item['label'] . ' — ' . number_format($item['amount'], 2, ',', '.') . ' TL'
                . ($item['fully'] ? ' (tam kapandı)' : ' (kısmi)');
        }, $closed);

        $message = 'Ödeme kaydedildi. Otomatik kapatılan aidatlar: ' . implode('; ', $lines) . '.';

        $leftover = round((float) $payment->unallocated_amount, 2);
        if ($leftover > 0) {
            $message .= ' Kalan tahsis edilmemiş tutar: ' . number_format($leftover, 2, ',', '.') . ' TL.';
        }

        return $message;
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

        $payment->load(['account', 'allocations.due', 'allocations.expense', 'transactions', 'cashTransactions']);

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
                ]);
            }
        });

        return redirect()->route('payments.show', $payment)->with('status', 'Ödeme kaydı güncellendi.');
    }

    public function destroy(Request $request, CurrentApartment $currentApartment, Payment $payment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment || $payment->apartment_id !== $apartment->id) {
            abort(404);
        }

        $accountId = $payment->account_id;

        DB::transaction(function () use ($payment) {
            $payment->load('allocations.due', 'allocations.expense');

            foreach ($payment->allocations as $allocation) {
                if ($allocation->due) {
                    $due = $allocation->due;
                    $due->remaining_amount = min($due->amount, $due->remaining_amount + $allocation->amount);
                    $due->status = $due->remaining_amount >= $due->amount ? 'unpaid' : 'partial';
                    $due->save();
                } elseif ($allocation->expense) {
                    $expense = $allocation->expense;
                    $expense->paid_amount = max(0, $expense->paid_amount - $allocation->amount);
                    $expense->remaining_amount = min($expense->amount, $expense->amount - $expense->paid_amount);
                    $expense->is_paid = $expense->remaining_amount <= 0;
                    $expense->save();
                }
                $allocation->delete();
            }

            $payment->transactions()->delete();

            $deletedCash = CashTransaction::where('payment_id', $payment->id)->delete();

            if (! $deletedCash) {
                CashTransaction::where('apartment_id', $payment->apartment_id)
                    ->where('account_id', $payment->account_id)
                    ->where('amount', $payment->amount)
                    ->where('transaction_date', $payment->payment_date)
                    ->delete();
            }

            $payment->delete();
        });

        $redirectTo = $request->input('redirect_to');

        if ($redirectTo && str_starts_with($redirectTo, url('/'))) {
            return redirect($redirectTo)->with('status', 'Ödeme kaydı ve tüm tahsisler silindi.');
        }

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

    public function previewAllocations(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment) {
            return response()->json(['error' => 'Apartman bulunamadı.'], 403);
        }

        $request->validate([
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('apartment_id', $apartment->id)],
            'amount'     => ['required', 'numeric', 'min:0.01'],
        ]);

        $accountId = $request->query('account_id');
        if (! $accountId) {
            return response()->json(['has_dues' => false, 'dues' => [], 'leftover' => (float) $request->query('amount')]);
        }

        $account = Account::where('id', $accountId)->where('apartment_id', $apartment->id)->firstOrFail();

        if ($account->type === Account::TYPE_SUPPLIER) {
            return response()->json(['has_dues' => false, 'dues' => [], 'leftover' => (float) $request->query('amount')]);
        }

        $dues = Due::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        if ($dues->isEmpty()) {
            return response()->json(['has_dues' => false, 'dues' => [], 'leftover' => (float) $request->query('amount')]);
        }

        $budget    = round((float) $request->query('amount'), 2);
        $remaining = $budget;
        $result    = [];

        foreach ($dues as $due) {
            $suggested = min($remaining, round((float) $due->remaining_amount, 2));
            $result[]  = [
                'id'               => $due->id,
                'description'      => $due->description ?: 'Aidat',
                'due_date'         => $due->due_date?->format('d.m.Y') ?? '-',
                'remaining_amount' => round((float) $due->remaining_amount, 2),
                'suggested_amount' => $suggested > 0 ? $suggested : 0,
                'is_imported'      => (bool) $due->is_imported,
            ];
            $remaining = round($remaining - $suggested, 2);
        }

        return response()->json([
            'has_dues' => true,
            'dues'     => $result,
            'leftover' => max(0, $remaining),
        ]);
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
