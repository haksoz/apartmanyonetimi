<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentAllocation extends Model
{
    protected $fillable = [
        'payment_id',
        'due_id',
        'expense_id',
        'amount',
        'principal_amount',
        'late_compensation_amount',
        'allocation_instruction_id',
        'allocation_instruction_item_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'late_compensation_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $allocation) {
            $compensation = BigDecimal::of($allocation->late_compensation_amount ?? '0');

            if ($allocation->getAttributeFromArray('principal_amount') === null
                && $compensation->isZero()
                && $allocation->allocation_instruction_id === null && $allocation->allocation_instruction_item_id === null) {
                return;
            }

            $payment = $allocation->payment()->first();
            if ($payment === null || $allocation->expense_id !== null || ! $allocation->due()
                ->where('apartment_id', $payment->apartment_id)
                ->where('account_id', $payment->account_id)
                ->exists()) {
                throw new DomainException('Borç tahsisi, ödeme ile aynı apartman ve cari hesaba ait olmalıdır.');
            }

            if ($allocation->allocation_instruction_id !== null
                && ! $allocation->instruction()->where('payment_id', $payment->id)->exists()) {
                throw new DomainException('Tahsis talimatı aynı ödemeye ait olmalıdır.');
            }

            if ($allocation->allocation_instruction_item_id !== null && ! PaymentAllocationInstructionItem::query()
                ->whereKey($allocation->allocation_instruction_item_id)->where('instruction_id', $allocation->allocation_instruction_id)
                ->where('due_id', $allocation->due_id)->exists()) {
                throw new DomainException('Tahsis, talimattaki borç satırıyla eşleşmelidir.');
            }

            $principal = BigDecimal::of($allocation->principal_amount);
            if ($principal->isNegative() || $compensation->isNegative()
                || ! $principal->plus($compensation)->isEqualTo($allocation->amount)) {
                throw new DomainException('Ana para ve Gecikme Tazminatı toplamı tahsis tutarına eşit olmalıdır.');
            }
        });
    }

    protected function principalAmount(): Attribute
    {
        return Attribute::get(function ($value) {
            $amount = $value ?? ($this->due_id !== null && $this->expense_id === null ? $this->amount : null);

            return $amount === null ? null : $this->asDecimal($amount, 2);
        });
    }

    public function instruction(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocationInstruction::class, 'allocation_instruction_id');
    }

    public function lateCompensationTransaction(): HasOne
    {
        return $this->hasOne(AccountTransaction::class, 'late_compensation_allocation_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function due(): BelongsTo
    {
        return $this->belongsTo(Due::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
