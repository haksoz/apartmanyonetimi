<?php

namespace App\Services;

use App\Models\Due;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class LateCompensationCalculator
{
    /** Return all monetary balances in integer kuruş, through the requested day. */
    public function balance(Due $due, DateTimeInterface|string $date, ?int $throughPaymentId = null): array
    {
        $day = self::day($date);
        $principal = self::cents($due->amount);

        if (! $due->late_compensation_applies) {
            return $this->totals($principal, self::cents($due->remaining_amount ?? $due->amount), 0, 0);
        }

        $apartment = $due->apartment;
        $start = $this->startFor($due, $apartment, $day);
        $rates = $this->ratesFor($apartment, $day->toDateString());
        $payments = $due->allocations()->whereNull('expense_id')->whereHas('payment', fn ($query) => $query
            ->where('apartment_id', $due->apartment_id)->where('account_id', $due->account_id)
            ->where(fn ($dates) => $dates->whereDate('payment_date', '<', $day->toDateString())
                ->orWhere(fn ($sameDay) => $sameDay->whereDate('payment_date', $day->toDateString())
                    ->when($throughPaymentId !== null, fn ($order) => $order->where('payments.id', '<=', $throughPaymentId)))))
            ->with('payment')->get()->groupBy(fn ($allocation) => $allocation->payment->payment_date->toDateString())
            ->map(fn ($allocations) => [
                'principal' => $allocations->sum(fn ($allocation) => self::cents($allocation->principal_amount)),
                'compensation' => $allocations->sum(fn ($allocation) => self::cents($allocation->late_compensation_amount)),
            ])->all();

        return $this->calculate($principal, $start, $day, $rates, $payments);
    }

    public function startFor(Due $due, \App\Models\Apartment $apartment, CarbonImmutable $day): CarbonImmutable
    {
        return $due->late_compensation_applies && $due->due_date && $apartment->late_compensation_starts_on
            ? max(self::day($due->due_date)->addDay(), self::day($apartment->late_compensation_starts_on),
                self::day($due->late_compensation_starts_on ?? $apartment->late_compensation_starts_on))
            : $day->addDay();
    }

    public function ratesFor(\App\Models\Apartment $apartment, string $date): array
    {
        return $apartment->lateCompensationPolicies()->whereDate('effective_from', '<=', $date)->get()
            ->mapWithKeys(fn ($policy) => [$policy->effective_from->toDateString() => $policy->is_active ? $policy->monthly_rate : 0])->all();
    }

    /**
     * Calculate from chronological inputs without loading or updating models.
     *
     * @param array<string, string|int> $rates Monthly percentages keyed by effective date. Use zero for passive periods.
     * @param array<string, array{principal: int, compensation: int}> $payments Daily allocation totals in kuruş.
     */
    public function calculate(int $principal, CarbonImmutable $start, CarbonImmutable $date, array $rates, array $payments): array
    {
        $events = array_unique([...array_keys($rates), ...array_keys($payments), $start->toDateString(), $date->addDay()->toDateString()]);
        sort($events, SORT_STRING);

        $remaining = $principal;
        $paid = 0;
        $accrued = BigDecimal::zero();
        $rate = BigDecimal::zero();
        $cursor = null;
        foreach ($events as $day) {
            $event = self::day($day);
            if ($cursor && $event > $cursor && $cursor >= $start && $remaining > 0) {
                $accrued = $accrued->plus($rate->multipliedBy($remaining)->multipliedBy((int) $cursor->diffInDays($event)));
            }
            if ($event > $date) {
                break;
            }
            if (isset($rates[$day])) {
                $rate = BigDecimal::of($rates[$day]);
            }
            $cursor = $event;

            if (isset($payments[$day])) {
                if ($event >= $start && $remaining > 0) {
                    $accrued = $accrued->plus($rate->multipliedBy($remaining));
                    $cursor = $event->addDay();
                }
                $remaining -= $payments[$day]['principal'];
                $paid += $payments[$day]['compensation'];
            }
        }

        // Rates are percentages per 30 days. Divide only after summing all intervals.
        $earned = $accrued->dividedBy(3000, 0, RoundingMode::HalfUp)->toInt();

        return $this->totals($principal, $remaining, $earned, $paid);
    }

    private function totals(int $principal, int $remaining, int $earned, int $paid): array
    {
        $unpaid = max(0, $earned - $paid);

        return [
            'initial_principal' => $principal,
            'remaining_principal' => $remaining,
            'accrued_compensation' => $earned,
            'paid_compensation' => $paid,
            'remaining_compensation' => $unpaid,
            'total' => $remaining + $unpaid,
        ];
    }

    public static function day(DateTimeInterface|string $date): CarbonImmutable
    {
        $value = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;

        return CarbonImmutable::parse($value, 'Europe/Istanbul')->startOfDay();
    }

    public static function cents(string|int|float|null $amount): int
    {
        return BigDecimal::of($amount ?? 0)->multipliedBy(100)->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    public static function money(int $cents): string
    {
        return (string) BigDecimal::ofUnscaledValue($cents, 2);
    }
}
