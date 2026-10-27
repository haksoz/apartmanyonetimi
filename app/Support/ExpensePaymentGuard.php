<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Expense;
use Illuminate\Validation\ValidationException;

/** Shared checks for the existing full-expense payment endpoints. */
class ExpensePaymentGuard
{
    public static function cents($amount): int
    {
        return (int) round((float) $amount * 100);
    }

    // Supplier mutations take this lock before Payment, Expense and allocation locks.
    public static function lockSupplier(?int $accountId, int $apartmentId): void
    {
        if ($accountId) {
            Account::withTrashed()->where('apartment_id', $apartmentId)
                ->where('type', Account::TYPE_SUPPLIER)->whereKey($accountId)
                ->lockForUpdate()->first();
        }
    }

    public static function fullAmount(Expense $expense): int
    {
        $amount = self::cents($expense->amount);
        $allocations = $expense->paymentAllocations()->orderBy('id')->lockForUpdate()->get();
        $allocated = $allocations->sum(fn ($allocation) => self::cents($allocation->amount));
        $paid = $expense->paid_amount;
        $remaining = $expense->remaining_amount;

        if ($expense->is_paid || ($remaining !== null && self::cents($remaining) === 0)) {
            self::reject('Bu gider zaten ödenmiş. Yeni ödeme oluşturulmadı.');
        }

        // Only the historical, wholly unpaid NULL/NULL case is inferred.
        if ($paid === null && $remaining === null && $allocations->isEmpty()) {
            $paid = 0;
            $remaining = $expense->amount;
        }

        if ($amount <= 0 || $paid === null || $remaining === null
            || self::cents($paid) < 0 || self::cents($remaining) < 0
            || self::cents($paid) + self::cents($remaining) !== $amount
            || self::cents($paid) !== $allocated) {
            self::reject('Giderin bakiye ve tahsis bilgileri tutarsız. Ödeme oluşturulmadı; kayıt kontrol edilmelidir.');
        }

        if ($allocated > 0 || $allocations->isNotEmpty() || self::cents($paid) > 0) {
            self::reject('Bu gidere daha önce ödeme tahsis edilmiş. Tam ödeme ekranından yeniden ödeme yapılamaz.');
        }

        return $amount;
    }

    private static function reject(string $message): never
    {
        throw ValidationException::withMessages(['amount' => $message]);
    }
}
