<?php

namespace App\Http\Controllers;

use App\Models\AccountTransaction;
use App\Models\Account;
use App\Models\Payment;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function index(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('subscriber.dashboard');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        $request->validate([
            'kind' => ['sometimes', 'in:all,receipts,payments,charges'],
            'filter' => ['sometimes', 'in:all,unallocated,orphan'],
        ]);
        $kind = $request->input('kind', 'all');
        $filter = $request->input('filter', 'all');
        $paymentType = (new Payment)->getMorphClass();

        $entries = AccountTransaction::query()->where(fn ($query) => $query
            ->where('apartment_id', $apartment->id)
            ->orWhere(fn ($legacy) => $legacy->whereNull('apartment_id')
                ->whereHas('account', fn ($account) => $account->withTrashed()->where('apartment_id', $apartment->id))))
            ->select(['id', 'apartment_id', 'account_id', 'type', 'description', 'amount',
                'transaction_date', 'transactionable_type', 'transactionable_id'])
            ->selectRaw('id as sort_id, 1 as source_priority');

        // Preserve payments without an accounting entry, without creating financial records.
        $unpostedPayments = Payment::query()->where('apartment_id', $apartment->id)
            ->whereDoesntHave('transactions')
            ->selectRaw('-id as id, apartment_id, account_id')
            ->selectRaw("CASE WHEN EXISTS (SELECT 1 FROM cash_transactions WHERE cash_transactions.payment_id = payments.id AND cash_transactions.type = 'expense') OR EXISTS (SELECT 1 FROM accounts WHERE accounts.id = payments.account_id AND accounts.type = ?) THEN 'debit' ELSE 'credit' END as type", [Account::TYPE_SUPPLIER])
            ->selectRaw('description, amount, payment_date as transaction_date, ? as transactionable_type, id as transactionable_id, id as sort_id, 0 as source_priority', [$paymentType]);

        $transactions = AccountTransaction::query()
            ->fromSub($entries->toBase()->unionAll($unpostedPayments->toBase()), 'account_transactions')
            ->with(['account' => fn ($query) => $query->withTrashed(), 'transactionable'])
            ->when(in_array($kind, ['receipts', 'payments']), fn ($query) => $query
                ->where('transactionable_type', $paymentType)
                ->where('type', $kind === 'receipts' ? 'credit' : 'debit'))
            ->when($kind === 'charges', fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('transactionable_type')->orWhere('transactionable_type', '!=', $paymentType)))
            ->when($filter !== 'all', fn ($query) => $query->whereHasMorph('transactionable', [Payment::class], function ($query) use ($filter, $apartment) {
                $query->where('apartment_id', $apartment->id)->where('unallocated_amount', '>', 0);
                if ($filter === 'orphan') {
                    $query->where(fn ($query) => $query->whereNull('account_id')
                        ->orWhereHas('account', fn ($account) => $account->withTrashed()->where('is_hidden', true)));
                }
            }))
            ->orderByDesc('transaction_date')
            ->orderByDesc('sort_id')
            ->orderByDesc('source_priority')
            ->paginate(25)->withQueryString();

        return view('ledger.index', compact('transactions', 'kind', 'filter'));
    }
}
