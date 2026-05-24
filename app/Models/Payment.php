<?php

namespace App\Models;

use App\Models\Traits\HasReferenceNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Payment extends Model
{
    use HasReferenceNumber;
    protected $fillable = [
        'apartment_id',
        'account_id',
        'reference_number',
        'amount',
        'unallocated_amount',
        'payment_date',
        'method',
        'description',
        'is_imported',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'unallocated_amount' => 'decimal:2',
        'payment_date' => 'date',
        'is_imported' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function dues(): BelongsToMany
    {
        return $this->belongsToMany(Due::class, 'payment_allocations')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(AccountTransaction::class, 'transactionable');
    }

    public function getAllocatedAmountAttribute(): float
    {
        return (float) $this->allocations()->sum('amount');
    }

    public function allocateToDue(Due $due, float $amount): PaymentAllocation
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Allocation amount must be greater than zero.');
        }

        if ($amount > $this->unallocated_amount) {
            throw new \InvalidArgumentException('Allocation exceeds payment unallocated amount.');
        }

        if ($amount > $due->remaining_amount) {
            throw new \InvalidArgumentException('Allocation exceeds due remaining amount.');
        }

        $allocation = $this->allocations()->create([
            'due_id' => $due->id,
            'amount' => $amount,
        ]);

        $this->decrement('unallocated_amount', $amount);

        $due->remaining_amount = max(0, $due->remaining_amount - $amount);
        $due->status = $due->remaining_amount === 0 ? 'paid' : 'partial';
        $due->save();

        return $allocation;
    }

    protected function getReferencePrefix(): string
    {
        return 'ODE';
    }
}
