<?php

namespace App\Services;

use App\Models\Due;
use App\Models\CashTransaction;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentAllocationInstruction;
use App\Support\LateCompensationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DueCollectionReplay
{
    public function __construct(private readonly DueCollectionGuard $guard) {}

    public function applies(int $apartment, ?int $account): bool
    {
        return $account !== null && app(LateCompensationScope::class)->previewEnabled()
            && (Due::where('apartment_id', $apartment)->where('account_id', $account)->where('late_compensation_applies', true)->exists()
                || Payment::where('apartment_id', $apartment)->where('account_id', $account)->whereHas('allocationInstructions')->exists());
    }

    public function paymentChange(Payment $payment, string $date, callable $change): mixed
    {
        $start = min($payment->payment_date->toDateString(), LateCompensationCalculator::day($date)->toDateString());

        return $this->run($payment->apartment_id, $payment->account_id, $start, $change);
    }

    public function run(int $apartment, int $account, string $start, ?callable $change = null, array $discard = []): mixed
    {
        return DB::transaction(function () use ($apartment, $account, $start, $change, $discard) {
            $this->guard->lockAccount($apartment, $account);
            $payments = Payment::where('apartment_id', $apartment)->where('account_id', $account)
                ->whereDate('payment_date', '>=', $start)
                ->where(fn ($query) => $query->whereHas('allocationInstructions')
                    ->orWhereHas('allocations', fn ($rows) => $rows->whereNotNull('due_id')))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($payments as $payment) {
                $this->validatedBudget($payment);
            }
            $dues = Due::where('apartment_id', $apartment)->where('account_id', $account)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $rows = PaymentAllocation::whereIn('payment_id', $payments->modelKeys())->whereNotNull('due_id')
                ->orderBy('id')->lockForUpdate()->get();

            $restored = [];
            foreach ($rows as $row) {
                if ($row->expense_id !== null || ! $dues->has($row->due_id)
                    || LateCompensationCalculator::cents($row->amount) <= 0
                    || ($row->allocation_instruction_id !== null && $row->instruction?->payment_id !== $row->payment_id)) {
                    $this->invalidHistory();
                }
                if ($row->allocation_instruction_id === null && ! in_array($row->id, $discard, true)) {
                    $this->fixed($payments[$row->payment_id], [$row]);
                }
                $restored[$row->due_id] = ($restored[$row->due_id] ?? 0) + LateCompensationCalculator::cents($row->principal_amount);
            }
            foreach ($restored as $id => $principal) {
                $due = $dues[$id];
                $observed = $due->allocations()->whereNull('expense_id')
                    ->whereHas('payment', fn ($query) => $query->where('apartment_id', $apartment)->where('account_id', $account))
                    ->get()->sum(fn ($allocation) => LateCompensationCalculator::cents($allocation->principal_amount));
                $initial = LateCompensationCalculator::cents($due->amount);
                $remaining = LateCompensationCalculator::cents($due->remaining_amount);
                if ($remaining < 0 || $initial - $remaining !== $observed || $remaining + $principal > $initial) {
                    $this->invalidHistory();
                }
                $this->setPrincipal($due, $remaining + $principal);
            }
            foreach ($rows as $row) {
                $row->lateCompensationTransaction()->delete();
                $row->delete();
            }
            foreach ($payments as $payment) {
                $this->restoreBudget($payment);
            }

            $result = $change ? $change() : null;
            $collection = app(DueCollection::class);
            foreach (Payment::where('apartment_id', $apartment)->where('account_id', $account)
                ->whereDate('payment_date', '>=', $start)->whereHas('allocationInstructions')
                ->orderBy('payment_date')->orderBy('id')->get() as $payment) {
                foreach ($payment->allocationInstructions()
                    ->orderByRaw("CASE WHEN mode = 'legacy' THEN 0 ELSE 1 END")->orderBy('id')->get() as $instruction) {
                    $collection->replayInstruction($payment, $instruction);
                }
            }

            return $result;
        });
    }

    public function remove(Payment $payment, array $ids): void
    {
        DB::transaction(function () use ($payment, $ids) {
            $this->guard->lockAccount($payment->apartment_id, $payment->account_id);
            $payment = Payment::where('apartment_id', $payment->apartment_id)->lockForUpdate()->findOrFail($payment->id);
            $rows = $payment->allocations()->whereIn('id', $ids)->whereNotNull('due_id')->get();
            abort_unless($rows->count() === count(array_unique($ids)), 404);
            $commands = [];
            foreach ($rows->whereNotNull('allocation_instruction_id')->groupBy('allocation_instruction_id') as $id => $selected) {
                $instruction = $payment->allocationInstructions()->findOrFail($id);
                if ($instruction->mode === PaymentAllocationInstruction::MODE_FIFO) {
                    $commands[] = [$instruction, $selected, true, $instruction->allocations()->count() === $selected->count()];
                    continue;
                }
                $items = [];
                foreach ($selected as $row) {
                    $item = $row->allocation_instruction_item_id
                        ? $instruction->items()->whereKey($row->allocation_instruction_item_id)->first()
                        : $instruction->items()->where('due_id', $row->due_id)->get();
                    if ($item instanceof \Illuminate\Support\Collection) {
                        $item = $item->count() === 1 ? $item->first() : null;
                    }
                    if (! $item) {
                        $this->invalidHistory();
                    }
                    $items[] = $item->id;
                }
                $commands[] = [$instruction, $items, false, false];
            }

            $this->run($payment->apartment_id, $payment->account_id, $payment->payment_date->toDateString(),
                function () use ($commands) {
                    foreach ($commands as [$instruction, $items, $fifo, $cancel]) {
                        if ($cancel) {
                            $instruction->delete();
                            continue;
                        }
                        if ($fifo) {
                            $position = (int) $instruction->items()->max('position') + 1;
                            $budget = LateCompensationCalculator::cents($instruction->requested_amount);
                            foreach ($items as $removed) {
                                $instruction->items()->create([
                                    'due_id' => $removed->due_id, 'position' => $position++, 'requested_amount' => $removed->amount,
                                ]);
                                $budget -= LateCompensationCalculator::cents($removed->amount);
                            }
                            if ($budget <= 0) {
                                $instruction->delete();
                            } else {
                                $instruction->update(['requested_amount' => LateCompensationCalculator::money($budget)]);
                            }
                            continue;
                        }
                        $instruction->items()->whereIn('id', $items)->delete();
                        if ($instruction->items()->doesntExist()) {
                            $instruction->delete();
                        } else {
                            $instruction->update(['requested_amount' => LateCompensationCalculator::money($instruction->items()->get()->sum(
                                fn ($item) => LateCompensationCalculator::cents($item->requested_amount)
                            ))]);
                        }
                    }
                }, $rows->modelKeys());
        });
    }

    public function editPayment(Payment $payment, array $attributes): void
    {
        $this->paymentChange($payment, $attributes['payment_date'], function () use ($payment, $attributes) {
            $payment->refresh();
            $cash = $this->cashEntry($payment);
            $credits = $payment->transactions()->where('type', 'credit')->lockForUpdate()->get();
            if ($credits->count() !== 1) {
                $this->invalidHistory();
            }
            $credit = $credits->first();
            $amount = LateCompensationCalculator::cents($attributes['amount']);
            $instructions = $payment->allocationInstructions()->get();
            if ($instructions->count() === 1 && $instructions->first()->mode === PaymentAllocationInstruction::MODE_FIFO
                && ! $payment->allocations()->exists()) {
                $fifo = $instructions->first();
                $reserved = LateCompensationCalculator::cents($fifo->requested_amount);
                if ($reserved === LateCompensationCalculator::cents($payment->amount) && $fifo->items()->doesntExist()) {
                    $fifo->update(['requested_amount' => LateCompensationCalculator::money($amount)]);
                }
            }
            $payment->update([
                'amount' => LateCompensationCalculator::money($amount), 'payment_date' => $attributes['payment_date'],
                'description' => $attributes['description'] ?? $payment->description,
            ]);
            $this->restoreBudget($payment);
            $movement = [
                'amount' => $payment->amount, 'transaction_date' => $payment->payment_date,
                'description' => $payment->description,
            ];
            $credit->update($movement);
            $cash->update($movement + ['cash_box_id' => $attributes['cash_box_id']]);
        });
    }

    public function cancelPayment(Payment $payment): void
    {
        $this->paymentChange($payment, $payment->payment_date->toDateString(), function () use ($payment) {
            if ($payment->allocations()->exists()) {
                $this->invalidHistory();
            }
            $this->cashEntry($payment)->delete();
            $payment->transactions()->delete();
            $payment->delete();
        });
    }

    private function cashEntry(Payment $payment): CashTransaction
    {
        $rows = $payment->cashTransactions()->where('apartment_id', $payment->apartment_id)
            ->where('account_id', $payment->account_id)->where('type', 'income')->lockForUpdate()->get();
        if ($rows->count() !== 1) {
            $this->invalidHistory();
        }

        return $rows->first();
    }

    private function fixed(Payment $payment, iterable $allocations): PaymentAllocationInstruction
    {
        $instruction = $payment->allocationInstructions()->create([
            'mode' => PaymentAllocationInstruction::MODE_LEGACY, 'requested_amount' => 0,
        ]);
        $total = 0;
        foreach ($allocations as $position => $allocation) {
            $total += LateCompensationCalculator::cents($allocation->amount);
            $instruction->items()->create([
                'due_id' => $allocation->due_id, 'position' => $position, 'requested_amount' => $allocation->amount,
            ]);
        }
        $instruction->update(['requested_amount' => LateCompensationCalculator::money($total)]);

        return $instruction;
    }

    private function validatedBudget(Payment $payment): void
    {
        $amount = LateCompensationCalculator::cents($payment->amount);
        $allocated = $payment->allocations()->get(['amount'])->sum(fn ($row) => LateCompensationCalculator::cents($row->amount));
        if ($allocated > $amount || ($payment->unallocated_amount !== null
            && $amount - $allocated !== LateCompensationCalculator::cents($payment->unallocated_amount))) {
            $this->invalidHistory();
        }
    }

    public function restoreBudget(Payment $payment): void
    {
        $available = LateCompensationCalculator::cents($payment->amount)
            - $payment->allocations()->get(['amount'])->sum(fn ($row) => LateCompensationCalculator::cents($row->amount));
        if ($available < 0) {
            $this->invalidHistory();
        }
        $payment->update(['unallocated_amount' => LateCompensationCalculator::money($available)]);
    }

    private function setPrincipal(Due $due, int $remaining): void
    {
        $due->update([
            'remaining_amount' => LateCompensationCalculator::money($remaining),
            'status' => $remaining === 0 ? 'paid' : ($remaining < LateCompensationCalculator::cents($due->amount) ? 'partial' : 'unpaid'),
        ]);
    }

    public function invalidHistory(): never
    {
        throw ValidationException::withMessages(['allocations' => 'Finansal geçmiş doğrulanamadığı için yeniden hesaplama yapılmadı.']);
    }
}
