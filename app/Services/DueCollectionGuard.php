<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Due;
use App\Models\Payment;
use App\Models\PaymentAllocationInstruction;
use App\Support\LateCompensationScope;
use Illuminate\Validation\ValidationException;

class DueCollectionGuard
{
    public function lockAccount(int $apartment, ?int $account): void
    {
        if ($account === null) {
            return;
        }
        Account::withTrashed()->where('apartment_id', $apartment)->whereKey($account)->lockForUpdate()->firstOrFail();
    }

    public function tracked(Due $due): bool
    {
        return $due->late_compensation_applies && (
            app(LateCompensationScope::class)->previewEnabled()
            || $due->allocations()->where(fn ($query) => $query
                ->where('late_compensation_amount', '>', 0)->orWhereNotNull('allocation_instruction_id'))->exists()
        );
    }

    public function protectDue(Due $due): void
    {
        if ($this->tracked($due) && $due->allocations()->exists()) {
            $this->requiresReplay();
        }
    }

    public function protectPayment(Payment $payment): void
    {
        if ($payment->allocationInstructions()->exists()
            || $payment->allocations()->whereHas('due', fn ($query) => $query->where('late_compensation_applies', true))->exists()) {
            $this->requiresReplay();
        }
    }

    public function hasLater(Payment $payment, ?Due $due = null): bool
    {
        $date = $payment->payment_date->toDateString();
        $later = Payment::where('apartment_id', $payment->apartment_id)->where('account_id', $payment->account_id)
            ->where(fn ($query) => $query->whereDate('payment_date', '>', $date)
                ->orWhere(fn ($sameDay) => $sameDay->whereDate('payment_date', $date)->where('id', '>', $payment->id)));

        return $later->where(fn ($query) => $query
            ->whereHas('allocations', fn ($allocations) => $due
                ? $allocations->where('due_id', $due->id)
                : $allocations->whereNotNull('due_id'))
            ->orWhereHas('allocationInstructions', fn ($instructions) => $instructions->where('mode', PaymentAllocationInstruction::MODE_FIFO)))
            ->exists();
    }

    public function requiresReplay(): never
    {
        throw ValidationException::withMessages([
            'allocations' => 'Bu işlem Gecikme Tazminatı için kronolojik yeniden hesaplama gerektiriyor.',
        ]);
    }
}
