<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Support\CurrentApartment;
use App\Support\ResidentAccountVisibility;
use Illuminate\Http\Request;

class ResidenceController extends Controller
{
    public function __invoke(CurrentApartment $currentApartment, Request $request, ?Account $account = null)
    {
        $user = auth()->user();
        $apartment = $currentApartment->getFor($user);

        if (! $apartment && $currentApartment->hasAvailableFor($user)) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            if ($user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            return redirect()->route('onboarding.show');
        }

        $accounts = Account::query()
            ->where('apartment_id', $apartment->id)
            ->where('user_id', $user->id)
            ->whereIn('type', [Account::TYPE_OWNER, Account::TYPE_TENANT])
            ->when($account, fn ($query) => $query->whereKey($account->id))
            ->with(['unit', 'activeTenantAssignment', 'tenantAssignments'])
            ->orderBy('id')
            ->get();

        abort_if($account && $accounts->isEmpty(), 403);
        $accounts->each(function (Account $account) {
            if ($account->portalIsSuspended()) {
                return;
            }
            $account->load([
                'transactions' => fn ($query) => ResidentAccountVisibility::transactions($query, $account)->orderBy('transaction_date')->orderBy('id'),
                'dues' => function ($query) use ($account) {
                    $query->where('remaining_amount', '>', 0)->with(['category', 'batch.plan'])->orderByDesc('due_date');
                    if ($account->residenceHasEnded()) {
                        $query->whereHas('transactions', fn ($tx) => ResidentAccountVisibility::transactions($tx, $account));
                    }
                },
                'payments' => fn ($query) => $query->where('unallocated_amount', '>', 0)
                    ->when($account->residenceHasEnded() && $account->residence_started_at, fn ($q) => $q->whereDate('payment_date', '>=', $account->residence_started_at))
                    ->orderByDesc('payment_date'),
            ]);
            $running = 0.0;
            $debit = 0.0;
            $credit = 0.0;

            foreach ($account->transactions as $transaction) {
                $amount = (float) $transaction->amount;
                if ($transaction->type === 'debit') {
                    $debit += $amount;
                    $running += $amount;
                } else {
                    $credit += $amount;
                    $running -= $amount;
                }
                $transaction->running_balance = $running;
            }

            $account->setAttribute('status_debit', $debit);
            $account->setAttribute('status_credit', $credit);
            $account->setAttribute('status_balance', $credit - $debit);
            $account->setRelation('recentTransactions', $account->transactions->reverse()->take(8)->values());
        });

        return view('member.home', compact('apartment', 'accounts'));
    }
}
