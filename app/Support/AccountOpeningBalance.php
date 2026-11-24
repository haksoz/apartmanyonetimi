<?php

namespace App\Support;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Due;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountOpeningBalance
{
    public function save(Account $account, float $amount, ?string $side, ?string $date): void
    {
        DB::transaction(function () use ($account, $amount, $side, $date) {
            $account = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();
            // Account lock precedes document locks, as in supplier payments.
            $entries = $account->transactions()->where(fn ($q) => $q->where('is_opening', true)
                ->orWhere(fn ($q) => $q->whereNull('transactionable_type')->where('description', 'Açılış bakiyesi')))
                ->lockForUpdate()->get();
            if ($entries->count() > 1) {
                throw ValidationException::withMessages(['balance' => 'Birden fazla açılış kaydı var. Önce kayıtlar kontrol edilmeli.']);
            }
            $entry = $entries->first();
            $source = $entry?->transactionable;
            if ($source) {
                $source = $source->newQuery()->whereKey($source->id)->lockForUpdate()->firstOrFail();
            }
            $amount = round($amount, 2);
            if ($source) {
                $used = $source instanceof Expense ? $source->paymentAllocations()->exists() : $source->allocations()->exists();
                if ($used) {
                    if ($amount !== round((float) $entry->amount, 2) || $side !== $entry->type || $date !== $entry->transaction_date->toDateString()) {
                        throw ValidationException::withMessages(['balance' => 'Açılış kaydı eşleştirilmiş. Değiştirmek için önce mevcut eşleştirmeleri kaldırın.']);
                    }

                    return;
                }
            }
            if ($amount < 0.01) {
                $source?->delete();
                $entry?->delete();

                return;
            }
            if (! in_array($side, ['debit', 'credit'], true) || ! $date) {
                throw ValidationException::withMessages(['balance' => 'Açılış için tarih ve borç/alacak yönü zorunludur.']);
            }
            $common = ['apartment_id' => $account->apartment_id, 'account_id' => $account->id,
                'amount' => $amount, 'description' => 'Açılış bakiyesi', 'is_opening' => true];
            $supplier = $account->type === Account::TYPE_SUPPLIER;
            if ((! $supplier && $side === 'credit') || ($supplier && $side === 'debit')) {
                $class = Payment::class;
                $values = $common + ['payment_date' => $date, 'unallocated_amount' => $amount];
            } elseif ($supplier) {
                $class = Expense::class;
                $values = $common + ['expense_date' => $date, 'due_date' => $date,
                    'remaining_amount' => $amount, 'paid_amount' => 0, 'is_paid' => false];
            } else {
                $class = Due::class;
                $values = $common + ['unit_id' => $account->unit_id, 'created_at_manual' => $date,
                    'due_date' => $date, 'remaining_amount' => $amount, 'status' => 'unpaid'];
            }
            if ($source && ! ($source instanceof $class)) {
                $source->delete();
                $source = null;
            }
            if ($source) {
                $source->update($values);
            } else {
                $source = $class::create($values);
            }
            $entry ??= new AccountTransaction;
            $entry->fill($common + ['type' => $side, 'transaction_date' => $date,
                'transactionable_type' => $class, 'transactionable_id' => $source->id])->save();
        });
    }
}
