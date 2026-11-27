<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentAllocationInstruction extends Model
{
    use SoftDeletes;

    public const MODE_MANUAL = 'manual';

    public const MODE_FIFO = 'fifo';

    public const MODE_LEGACY = 'legacy';

    protected $fillable = ['mode', 'requested_amount', 'operation_group', 'created_by'];

    protected $casts = ['requested_amount' => 'decimal:2'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PaymentAllocationInstructionItem::class, 'instruction_id')->orderBy('position');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'allocation_instruction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
