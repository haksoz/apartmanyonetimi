<?php

namespace App\Services;

use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Due;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentAllocationInstruction;
use App\Support\LateCompensationScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DueCollection
{
    public function __construct(
        private readonly LateCompensationCalculator $calculator,
        private readonly DueCollectionGuard $guard,
    ) {}

    public function quote(Due $due, string $date): int
    {
        if (! $this->guard->tracked($due)) {
            return LateCompensationCalculator::cents($due->remaining_amount ?? $due->amount);
        }

        return max(0, $this->calculator->balance($due, $date)['total']);
    }

    public function historicalQuote(Due $due, string $date, ?int $paymentId = null): int
    {
        if ($this->guard->tracked($due)) {
            return max(0, $this->calculator->balance($due, $date, $paymentId)['total']);
        }

        $later = $due->allocations()->whereNull('expense_id')->whereHas('payment', fn ($query) => $query
            ->where('account_id', $due->account_id)->where('apartment_id', $due->apartment_id)
            ->where(fn ($order) => $order->whereDate('payment_date', '>', $date)
                ->when($paymentId !== null, fn ($days) => $days->orWhere(fn ($sameDay) => $sameDay
                    ->whereDate('payment_date', $date)->where('payments.id', '>', $paymentId)))))->get();

        return $this->quote($due, $date) + $later->sum(fn ($row) => LateCompensationCalculator::cents($row->principal_amount));
    }

    public function record(int $apartment, int $account, int $amount, array $input, string $description, ?string $transactionDescription = null): Payment
    {
        $cash = CashBox::where('apartment_id', $apartment)->where('is_active', true)
            ->lockForUpdate()->findOrFail($input['cash_box_id']);
        $payment = Payment::create([
            'apartment_id' => $apartment, 'account_id' => $account,
            'amount' => LateCompensationCalculator::money($amount), 'unallocated_amount' => LateCompensationCalculator::money($amount),
            'payment_date' => $input['payment_date'], 'description' => $description,
        ]);
        CashTransaction::create([
            'apartment_id' => $apartment, 'account_id' => $account, 'cash_box_id' => $cash->id,
            'payment_id' => $payment->id, 'type' => 'income', 'is_active' => true,
            'amount' => $payment->amount, 'transaction_date' => $payment->payment_date, 'description' => $transactionDescription ?? $description,
        ]);
        $payment->transactions()->create([
            'apartment_id' => $apartment, 'account_id' => $account, 'type' => 'credit',
            'amount' => $payment->amount, 'transaction_date' => $payment->payment_date, 'description' => $transactionDescription ?? $description,
        ]);

        return $payment;
    }

    public function collect(int $apartment, int $account, array $ids, array $input, string $description): int
    {
        return DB::transaction(function () use ($apartment, $account, $ids, $input, $description) {
            $this->guard->lockAccount($apartment, $account);
            $dues = $this->dues($apartment, $account, $ids);
            if (! isset($input['amount'])) {
                $dues = $dues->filter(fn (Due $due) => in_array($due->status, ['pending', 'overdue', 'partial', 'unpaid'], true));
            }
            $rows = $dues->map(fn (Due $due) => [
                'due_id' => $due->id,
                'amount' => $input['amount'] ?? LateCompensationCalculator::money($this->quote($due, $input['payment_date'])),
            ])->filter(fn ($row) => LateCompensationCalculator::cents($row['amount']) > 0)->values();
            if ($rows->isEmpty()) {
                $this->invalid('Seçilen borçlarda ödenecek tutar yok.');
            }
            $total = $rows->sum(fn ($row) => LateCompensationCalculator::cents($row['amount']));
            if (isset($input['expected_amount']) && LateCompensationCalculator::cents($input['expected_amount']) !== $total) {
                $this->invalid('Ödenecek tutar değişti. Tahsilat ekranını yeniden açın.');
            }
            if (! empty($input['full_payment']) && $total !== $this->quote($dues->sole(), $input['payment_date'])) {
                $this->invalid('Ödenecek tutar değişti. Tahsilat ekranını yeniden açın.');
            }
            $payment = $this->record($apartment, $account, $total, $input, $description);
            $this->manual($apartment, $account, [$payment->id], $rows);

            return $rows->count();
        });
    }

    public function manual(int $apartment, int $account, array $paymentIds, iterable $rows): array
    {
        return DB::transaction(function () use ($apartment, $account, $paymentIds, $rows) {
            $this->guard->lockAccount($apartment, $account);
            $payments = Payment::where('apartment_id', $apartment)->where('account_id', $account)
                ->whereIn('id', $paymentIds)->orderBy('id')->lockForUpdate()->get()->sortBy(
                    fn (Payment $payment) => [$payment->payment_date->toDateString(), $payment->id]
                )->values();
            abort_unless($payments->count() === count(array_unique($paymentIds)), 404);
            $rows = collect($rows)->values();
            $dues = $this->dues($apartment, $account, $rows->pluck('due_id')->all());
            $plans = [];
            $applied = [];
            $group = $payments->count() > 1 ? (string) Str::uuid() : null;
            $track = $dues->contains(fn (Due $due) => $this->guard->tracked($due))
                || $this->hasTrackedDues($apartment, $account);
            $historical = $track && $payments->isNotEmpty() && $this->guard->hasLater($payments->first());
            foreach ($this->manualChunks($payments, $rows) as [$payment, $dueId, $chunk]) {
                $due = $dues->get($dueId);
                $instruction = null;
                $itemId = null;
                if ($track) {
                    $instruction = $plans[$payment->id] ??= $payment->allocationInstructions()->create([
                        'mode' => PaymentAllocationInstruction::MODE_MANUAL, 'requested_amount' => 0,
                        'operation_group' => $group, 'created_by' => auth()->id(),
                    ]);
                    $itemId = $instruction->items()->create([
                        'due_id' => $due->id, 'position' => $instruction->items()->count(),
                        'requested_amount' => LateCompensationCalculator::money($chunk),
                    ])->id;
                    $instruction->update(['requested_amount' => LateCompensationCalculator::money(
                        LateCompensationCalculator::cents($instruction->requested_amount) + $chunk
                    )]);
                }
                if (! $historical) {
                    $applied[] = $this->apply($payment, $due, $chunk, $instruction, $itemId);
                }
            }

            if ($historical && $plans) {
                app(DueCollectionReplay::class)->run($apartment, $account, $payments->first()->payment_date->toDateString());
                return PaymentAllocation::whereIn('allocation_instruction_id', array_values(array_map(fn ($instruction) => $instruction->id, $plans)))->orderBy('id')->get()->all();
            }

            return $applied;
        });
    }

    public function manualChunks(Collection $payments, iterable $rows): iterable
    {
        $budgets = $payments->mapWithKeys(fn (Payment $payment) => [$payment->id => $this->available($payment)]);
        $position = 0;
        foreach ($rows as $row) {
            $requested = LateCompensationCalculator::cents($row['amount']);
            while ($requested > 0) {
                $payment = $payments->get($position);
                if (! $payment) {
                    $this->invalid('Girilen toplam tutar ödeme bakiyesini aşıyor.');
                }
                $chunk = min($requested, $budgets[$payment->id]);
                if ($chunk === 0) {
                    $position++;
                    continue;
                }
                yield [$payment, (int) $row['due_id'], $chunk];
                $budgets[$payment->id] -= $chunk;
                $requested -= $chunk;
            }
        }
    }

    public function fifoTargets(Collection $dues, string $date, bool $respectDates, array $excluded = []): Collection
    {
        return $dues->filter(fn (Due $due) => ! in_array($due->id, $excluded, true)
            && (! $respectDates || (($due->created_at?->copy()->timezone('Europe/Istanbul')->toDateString() ?? $date) <= $date
                && ($due->collection_account_since?->toDateString() ?? $date) <= $date)))
            ->sortBy(fn (Due $due) => [$due->due_date?->toDateString(), $due->id]);
    }

    public function fifo(Payment $payment): array
    {
        return DB::transaction(function () use ($payment) {
            $this->guard->lockAccount($payment->apartment_id, $payment->account_id);
            $payment = Payment::where('apartment_id', $payment->apartment_id)->where('account_id', $payment->account_id)
                ->lockForUpdate()->findOrFail($payment->id);
            $date = $payment->payment_date->toDateString();
            $dues = Due::where('apartment_id', $payment->apartment_id)->where('account_id', $payment->account_id)
                ->where('remaining_amount', '>', 0)->orderBy('id')->lockForUpdate()->get();
            $track = $this->hasTrackedDues($payment->apartment_id, $payment->account_id);
            $instruction = $track && $this->available($payment) > 0 ? $payment->allocationInstructions()->create([
                'mode' => PaymentAllocationInstruction::MODE_FIFO, 'requested_amount' => LateCompensationCalculator::money($this->available($payment)),
                'created_by' => auth()->id(),
            ]) : null;
            if ($instruction && $this->guard->hasLater($payment)) {
                app(DueCollectionReplay::class)->run($payment->apartment_id, $payment->account_id, $date);

                return $instruction->allocations()->with('due')->get()->map(fn ($allocation) =>
                    $this->closing($allocation->due, LateCompensationCalculator::cents($allocation->amount)))->all();
            }

            return $this->allocateFifo($payment, $dues, $instruction);
        });
    }

    public function replayInstruction(Payment $payment, PaymentAllocationInstruction $instruction): void
    {
        if ($instruction->mode === PaymentAllocationInstruction::MODE_FIFO) {
            $dues = Due::where('apartment_id', $payment->apartment_id)->where('account_id', $payment->account_id)
                ->where('remaining_amount', '>', 0)->orderBy('id')->get();
            $this->allocateFifo($payment, $dues, $instruction);

            return;
        }

        if (! in_array($instruction->mode, [PaymentAllocationInstruction::MODE_MANUAL, PaymentAllocationInstruction::MODE_LEGACY], true)) {
            app(DueCollectionReplay::class)->invalidHistory();
        }

        $date = $payment->payment_date->toDateString();
        foreach ($instruction->items()->get() as $item) {
            $due = Due::where('apartment_id', $payment->apartment_id)->where('account_id', $payment->account_id)->find($item->due_id);
            if (! $due) {
                $previous = Due::withTrashed()->where('apartment_id', $payment->apartment_id)->find($item->due_id);
                if ($previous && ($previous->collection_account_since !== null || ($previous->trashed() && $previous->account_id === $payment->account_id))) {
                    continue;
                }
                app(DueCollectionReplay::class)->invalidHistory();
            }
            $amount = min($this->available($payment), $this->quote($due, $date),
                LateCompensationCalculator::cents($item->requested_amount));
            if ($amount > 0) {
                $this->apply($payment, $due, $amount, $instruction, $item->id);
            }
        }
    }

    private function allocateFifo(Payment $payment, Collection $dues, ?PaymentAllocationInstruction $instruction): array
    {
        $date = $payment->payment_date->toDateString();
        $budget = $instruction ? LateCompensationCalculator::cents($instruction->requested_amount) : $this->available($payment);
        // FIFO items record targets explicitly removed by the manager.
        $excluded = $instruction?->items()->pluck('due_id')->all() ?? [];
        $closed = [];
        foreach ($this->fifoTargets($dues, $date, $instruction !== null, $excluded) as $due) {
            if ($budget <= 0 || $this->available($payment) <= 0) {
                break;
            }
            $amount = min($budget, $this->available($payment), $this->quote($due, $date));
            if ($amount > 0) {
                $this->apply($payment, $due, $amount, $instruction);
                $budget -= $amount;
                $closed[] = $this->closing($due, $amount);
            }
        }

        return $closed;
    }

    private function closing(Due $due, int $amount): array
    {
        return [
            'label' => trim(($due->description ?: 'Aidat').($due->due_date ? ' ('.$due->due_date->format('d.m.Y').')' : '')),
            'amount' => (float) LateCompensationCalculator::money($amount),
            'fully' => LateCompensationCalculator::cents($due->remaining_amount) === 0,
        ];
    }

    private function hasTrackedDues(int $apartment, int $account): bool
    {
        return app(DueCollectionReplay::class)->applies($apartment, $account);
    }

    private function dues(int $apartment, int $account, array $ids): Collection
    {
        $dues = Due::where('apartment_id', $apartment)->where('account_id', $account)
            ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        abort_unless($dues->count() === count(array_unique($ids)), 404);

        return $dues;
    }

    private function available(Payment $payment): int
    {
        if ($payment->unallocated_amount !== null) {
            return max(0, LateCompensationCalculator::cents($payment->unallocated_amount));
        }

        $allocations = $payment->relationLoaded('allocations') ? $payment->allocations : $payment->allocations()->get(['amount']);

        return max(0, LateCompensationCalculator::cents($payment->amount)
            - $allocations->sum(fn ($row) => LateCompensationCalculator::cents($row->amount)));
    }

    private function apply(Payment $payment, Due $due, int $amount, ?PaymentAllocationInstruction $instruction, ?int $itemId = null): PaymentAllocation
    {
        $date = $payment->payment_date->toDateString();
        $tracked = $this->guard->tracked($due);
        $remaining = LateCompensationCalculator::cents($due->remaining_amount ?? $due->amount);
        $compensation = 0;

        if ($tracked) {
            if (! app(LateCompensationScope::class)->previewEnabled()) {
                $this->guard->requiresReplay();
            }
            $balance = $this->calculator->balance($due, $date);
            if ($balance['remaining_principal'] !== $remaining) {
                $this->guard->requiresReplay();
            }
            $compensation = $balance['remaining_compensation'];
        }

        if ($amount > $this->available($payment)) {
            $this->invalid('Tahsis tutarı kalan ödeme bakiyesinden büyük olamaz.');
        }
        ['principal' => $principal, 'compensation' => $compensation] = $this->split($amount, $remaining, $compensation);

        $attributes = ['due_id' => $due->id, 'amount' => LateCompensationCalculator::money($amount)];
        if ($tracked || $instruction) {
            $attributes += [
                'principal_amount' => LateCompensationCalculator::money($principal),
                'late_compensation_amount' => LateCompensationCalculator::money($compensation),
                'allocation_instruction_id' => $instruction?->id,
                'allocation_instruction_item_id' => $itemId,
            ];
        }
        $allocation = $payment->allocations()->create($attributes);

        if ($compensation > 0) {
            $allocation->lateCompensationTransaction()->create([
                'apartment_id' => $payment->apartment_id, 'account_id' => $payment->account_id,
                'type' => 'debit', 'amount' => LateCompensationCalculator::money($compensation),
                'description' => 'Gecikme Tazminatı'.($due->reference_number ? ' · '.$due->reference_number : ''),
                'transaction_date' => $payment->payment_date,
            ]);
        }

        $due->update([
            'remaining_amount' => LateCompensationCalculator::money($remaining - $principal),
            'status' => $remaining === $principal ? 'paid' : ($principal > 0 ? 'partial' : $due->status),
        ]);
        $payment->update(['unallocated_amount' => LateCompensationCalculator::money($this->available($payment) - $amount)]);

        return $allocation;
    }

    /** Split an amount in kuruş without recording a payment or an allocation. */
    public function split(int $amount, int $principalDue, int $compensationDue): array
    {
        $compensation = min($amount, $compensationDue);
        $principal = $amount - $compensation;
        if ($principal > $principalDue) {
            $this->invalid('Tahsis tutarı borcun kalan tutarından büyük olamaz.');
        }

        return ['principal' => $principal, 'compensation' => $compensation];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['allocations' => $message]);
    }
}

