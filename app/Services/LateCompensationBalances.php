<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\Due;
use App\Models\PaymentAllocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LateCompensationBalances
{
    public function __construct(private readonly LateCompensationCalculator $calculator) {}

    public function hasHistory(Apartment $apartment): bool
    {
        return $apartment->late_compensation_starts_on !== null
            && $apartment->lateCompensationPolicies()->where('is_active', true)
                ->whereDate('effective_from', '<=', now('Europe/Istanbul')->toDateString())->exists();
    }

    /** Return report values in kuruş keyed by due ID. */
    public function forDues(Apartment $apartment, iterable $dues, ?string $date = null, bool $includePrincipal = false): Collection
    {
        $result = collect();
        if (! $this->hasHistory($apartment)) {
            return $result;
        }

        $day = LateCompensationCalculator::day($date ?? now('Europe/Istanbul'));
        $cutoff = $day->toDateString();
        $dues = collect($dues)->filter(fn (Due $due) => $due->apartment_id === $apartment->id
            && ($includePrincipal || $due->late_compensation_applies))->unique('id')->values();
        if ($dues->isEmpty()) {
            return $result;
        }

        $rates = $this->calculator->ratesFor($apartment, $cutoff);

        foreach ($dues->chunk(500) as $chunk) {
            $events = PaymentAllocation::join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
                ->whereIn('payment_allocations.due_id', $chunk->pluck('id'))->whereNull('payment_allocations.expense_id')
                ->where('payments.apartment_id', $apartment->id)->whereDate('payments.payment_date', '<=', $cutoff)
                ->get(['payment_allocations.*', 'payments.account_id as balance_account_id', 'payments.payment_date as balance_payment_date'])
                ->groupBy('due_id');

            foreach ($chunk as $due) {
                $start = $this->calculator->startFor($due, $apartment, $day);
                $payments = ($events[$due->id] ?? collect())->filter(fn ($row) => (int) $row->balance_account_id === $due->account_id)
                    ->groupBy(fn ($row) => substr($row->balance_payment_date, 0, 10))->map(fn ($rows) => [
                        'principal' => $rows->sum(fn ($row) => LateCompensationCalculator::cents($row->principal_amount)),
                        'compensation' => $rows->sum(fn ($row) => LateCompensationCalculator::cents($row->late_compensation_amount)),
                    ])->all();
                $result[$due->id] = $this->calculator->calculate(
                    LateCompensationCalculator::cents($due->amount), $start, $day, $rates, $payments
                );
            }
        }

        return $result;
    }

    public function attach(Apartment $apartment, iterable $dues, ?string $date = null): Collection
    {
        $balances = $this->forDues($apartment, $dues, $date);
        foreach ($dues as $due) {
            if ($balances->has($due->id)) {
                $due->setRelation('lateCompensationBalance', $balances[$due->id]);
            }
        }

        return $balances;
    }

    public function receipts(int $apartment, string $from, string $to, string $type = 'all', ?int $account = null)
    {
        return DB::table('payment_allocations as allocation')
            ->join('payments as payment', 'payment.id', '=', 'allocation.payment_id')
            ->join('dues as due', 'due.id', '=', 'allocation.due_id')
            ->where('payment.apartment_id', $apartment)->where('due.apartment_id', $apartment)
            ->whereColumn('payment.account_id', 'due.account_id')
            ->whereNull('allocation.expense_id')->whereNull('due.deleted_at')->where('payment.is_opening', false)
            ->whereDate('payment.payment_date', '>=', $from)->whereDate('payment.payment_date', '<=', $to)
            ->when($type !== 'all', fn ($query) => $query->where('due.due_type', $type))
            ->when($account !== null, fn ($query) => $query->where('payment.account_id', $account))
            ->selectRaw('COALESCE(SUM(COALESCE(allocation.principal_amount, allocation.amount)), 0) AS principal, COALESCE(SUM(allocation.late_compensation_amount), 0) AS compensation')
            ->first();
    }
}
