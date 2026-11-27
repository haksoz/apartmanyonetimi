<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Due;
use App\Models\Payment;
use App\Models\PaymentAllocationInstruction;
use Illuminate\Validation\ValidationException;

class DueAllocationPreview
{
    public function __construct(
        private readonly DueCollection $collection,
        private readonly DueCollectionGuard $guard,
        private readonly LateCompensationCalculator $calculator,
    ) {}

    /** Project the same chronological allocation intents without applying them. Amounts use kuruş. */
    public function project(Account $account, array $ids, array $rows): array
    {
        $payments = Payment::where('apartment_id', $account->apartment_id)->where('account_id', $account->id)
            ->orderBy('payment_date')->orderBy('id')->with(['allocations', 'allocationInstructions.items'])->get();
        $selected = $payments->whereIn('id', $ids)->values();
        abort_unless($selected->isNotEmpty() && $selected->count() === count(array_unique($ids)), 404);
        $dues = Due::where('apartment_id', $account->apartment_id)->where('account_id', $account->id)->get()->keyBy('id');
        abort_unless(collect($rows)->every(fn ($row) => $dues->has($row['due_id'])), 404);
        $used = $payments->flatMap->allocations->whereNotNull('due_id')->groupBy('due_id');
        $targets = array_column($rows, 'due_id');
        foreach ($dues as $due) {
            if (! $due->late_compensation_applies && ! in_array($due->id, $targets, true)) {
                continue;
            }
            $principalPaid = ($used[$due->id] ?? collect())->sum(fn ($row) => LateCompensationCalculator::cents($row->principal_amount));
            if (LateCompensationCalculator::cents($due->amount) - $principalPaid !== LateCompensationCalculator::cents($due->remaining_amount)) {
                $this->invalid();
            }
        }
        $first = $selected->first();
        $historical = $this->guard->hasLater($first);
        $new = collect(iterator_to_array($this->collection->manualChunks($selected, $rows)))
            ->groupBy(fn ($chunk) => $chunk[0]->id);
        $apart = $account->apartment;
        $rates = $this->calculator->ratesFor($apart, $payments->last()->payment_date->toDateString());
        $principal = $dues->map(fn (Due $due) => LateCompensationCalculator::cents($due->amount))->all();
        $events = $results = [];

        foreach ($payments as $payment) {
            $date = $payment->payment_date->toDateString();
            $budget = LateCompensationCalculator::cents($payment->amount) - $payment->allocations->whereNull('due_id')
                ->sum(fn ($allocation) => LateCompensationCalculator::cents($allocation->amount));
            $newRows = [];
            $replay = $historical && $date >= $first->payment_date->toDateString();
            if ($replay) {
                foreach ($payment->allocationInstructions->where('mode', PaymentAllocationInstruction::MODE_LEGACY)->sortBy('id') as $instruction) {
                    $this->applyItems($instruction, $dues, $date, $budget, $principal, $events, $rates, $apart);
                }
            }
            foreach ($payment->allocations as $allocation) {
                if ($allocation->due_id === null) {
                    continue;
                }
                if ($replay && $allocation->allocation_instruction_id !== null) {
                    continue;
                }
                if (! $dues->has($allocation->due_id) || $allocation->expense_id !== null) {
                    $this->invalid();
                }
                if ($replay) {
                    $this->apply($dues[$allocation->due_id], $date, LateCompensationCalculator::cents($allocation->amount),
                        $budget, $principal, $events, $rates, $apart, true);
                } else {
                    $amount = LateCompensationCalculator::cents($allocation->amount);
                    $this->addEvent($events, $allocation->due_id, $date,
                        LateCompensationCalculator::cents($allocation->principal_amount),
                        LateCompensationCalculator::cents($allocation->late_compensation_amount));
                    $principal[$allocation->due_id] -= LateCompensationCalculator::cents($allocation->principal_amount);
                    $budget -= $amount;
                }
            }

            if ($replay) {
                foreach ($payment->allocationInstructions->where('mode', '!=', PaymentAllocationInstruction::MODE_LEGACY)->sortBy('id') as $instruction) {
                    $limit = LateCompensationCalculator::cents($instruction->requested_amount);
                    if ($instruction->mode === PaymentAllocationInstruction::MODE_FIFO) {
                        foreach ($this->collection->fifoTargets($dues, $date, true, $instruction->items->pluck('due_id')->all()) as $due) {
                            if ($limit <= 0 || $budget <= 0) {
                                break;
                            }
                            $applied = $this->apply($due, $date, $limit, $budget, $principal, $events, $rates, $apart, true);
                            $limit -= $applied['amount'];
                        }
                    } else {
                        $this->applyItems($instruction, $dues, $date, $budget, $principal, $events, $rates, $apart);
                    }
                }
            }

            foreach ($new[$payment->id] ?? [] as [, $dueId, $amount]) {
                $newRows[] = $this->apply($dues[$dueId], $date, $amount, $budget, $principal, $events, $rates, $apart, $historical);
            }
            if ($selected->contains('id', $payment->id)) {
                $results[] = [
                    'id' => $payment->id, 'date' => $date, 'reference' => $payment->reference_number,
                    'amount' => array_sum(array_column($newRows, 'amount')),
                    'principal' => array_sum(array_column($newRows, 'principal')),
                    'compensation' => array_sum(array_column($newRows, 'compensation')),
                    'unallocated' => $budget,
                ];
            }
        }

        return ['payments' => $results, 'hash' => hash('sha256', json_encode([$results, $rows, $ids], JSON_THROW_ON_ERROR))];
    }

    private function applyItems(PaymentAllocationInstruction $instruction, \Illuminate\Support\Collection $dues, string $date, int &$budget, array &$principal, array &$events, array $rates, Apartment $apartment): void
    {
        foreach ($instruction->items as $item) {
            if ($dues->has($item->due_id)) {
                $this->apply($dues[$item->due_id], $date, LateCompensationCalculator::cents($item->requested_amount),
                    $budget, $principal, $events, $rates, $apartment, true);
            }
        }
    }

    private function apply(Due $due, string $date, int $requested, int &$budget, array &$principal, array &$events, array $rates, Apartment $apartment, bool $cap): array
    {
        $remaining = $principal[$due->id];
        if ($remaining < 0 || $budget < 0) {
            $this->invalid();
        }
        $compensation = 0;
        if ($due->late_compensation_applies) {
            $day = LateCompensationCalculator::day($date);
            $balance = $this->calculator->calculate(LateCompensationCalculator::cents($due->amount),
                $this->calculator->startFor($due, $apartment, $day), $day, $rates, $events[$due->id] ?? []);
            $compensation = $balance['remaining_compensation'];
        }
        $amount = min($requested, $budget, $remaining + $compensation);
        if (! $cap && $amount !== $requested) {
            throw ValidationException::withMessages(['allocations' => 'Tahsis tutarı kalan ödeme veya borç bakiyesinden büyük olamaz.']);
        }
        $parts = $this->collection->split($amount, $remaining, $compensation);
        $this->addEvent($events, $due->id, $date, $parts['principal'], $parts['compensation']);
        $principal[$due->id] -= $parts['principal'];
        $budget -= $amount;

        return $parts + ['amount' => $amount];
    }

    private function addEvent(array &$events, int $id, string $day, int $principal, int $compensation): void
    {
        $events[$id][$day] ??= ['principal' => 0, 'compensation' => 0];
        $events[$id][$day]['principal'] += $principal;
        $events[$id][$day]['compensation'] += $compensation;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['allocations' => 'Finansal geçmiş önizleme için doğrulanamadı.']);
    }
}
