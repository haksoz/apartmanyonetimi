<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocationInstructionItem extends Model
{
    protected $fillable = ['due_id', 'position', 'requested_amount'];

    protected $casts = ['requested_amount' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $payment = $item->instruction()->first()?->payment()->first();

            if ($payment === null || ! $item->due()
                ->where('apartment_id', $payment->apartment_id)
                ->where('account_id', $payment->account_id)
                ->exists()) {
                throw new DomainException('Tahsis talimatındaki borç, ödeme ile aynı apartman ve cari hesaba ait olmalıdır.');
            }
        });
    }

    public function instruction(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocationInstruction::class, 'instruction_id');
    }

    public function due(): BelongsTo
    {
        return $this->belongsTo(Due::class);
    }
}
