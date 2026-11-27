<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

use App\Models\Account;

class AccountTransaction extends Model
{
    protected $fillable = [
        'is_opening',
        'late_compensation_allocation_id',
        'apartment_id',
        'account_id',
        'transactionable_type',
        'transactionable_id',
        'type',
        'description',
        'amount',
        'transaction_date',
        'is_imported',
    ];

    protected $casts = [
        'is_opening' => 'boolean',
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (AccountTransaction $transaction) {
            if ($transaction->late_compensation_allocation_id === null) {
                return;
            }

            $allocation = $transaction->lateCompensationAllocation()
                ->whereNotNull('due_id')->whereNull('expense_id')
                ->whereHas('payment', fn ($query) => $query
                    ->where('apartment_id', $transaction->apartment_id)
                    ->where('account_id', $transaction->account_id))
                ->whereHas('due', fn ($query) => $query
                    ->where('apartment_id', $transaction->apartment_id)
                    ->where('account_id', $transaction->account_id))
                ->first();

            if ($allocation === null || $transaction->type !== 'debit'
                || $allocation->late_compensation_amount === null
                || ! BigDecimal::of($allocation->late_compensation_amount)->isPositive()
                || $allocation->late_compensation_amount !== $transaction->amount) {
                throw new DomainException('Gecikme Tazminatı cari hareketi geçerli bir borç tahsisiyle eşleşmelidir.');
            }
        });
    }

    public function lateCompensationAllocation(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocation::class, 'late_compensation_allocation_id');
    }

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
