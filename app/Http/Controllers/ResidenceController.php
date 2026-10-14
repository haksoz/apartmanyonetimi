<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Support\CurrentApartment;

class ResidenceController extends Controller
{
    public function __invoke(CurrentApartment $currentApartment)
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
            ->with([
                'unit',
                'activeTenantAssignment',
                'dues' => function ($query) {
                    $query->where('remaining_amount', '>', 0)
                        ->with(['category', 'batch.plan'])
                        ->orderByDesc('due_date');
                },
                'payments' => function ($query) {
                    $query->where('unallocated_amount', '>', 0)
                        ->orderByDesc('payment_date')
                        ->orderByDesc('id');
                },
                'transactions' => function ($query) {
                    $query->orderBy('transaction_date')
                        ->orderBy('id');
                },
            ])
            ->orderBy('id')
            ->get();

        $accounts->each(function (Account $account) {
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
