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
    public function scopeWithoutOpening($query)
    {
        return $query->where($this->qualifyColumn('is_opening'), false);
    }

    protected $fillable = [
        'is_opening',
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
        'is_opening' => 'boolean',
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

    public function allocationInstructions(): HasMany
    {
        return $this->hasMany(PaymentAllocationInstruction::class);
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

    public function expenses(): BelongsToMany
    {
        return $this->belongsToMany(Expense::class, 'payment_allocations')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function allocateToExpense(Expense $expense, float $amount): PaymentAllocation
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Allocation amount must be greater than zero.');
        }

        if ($amount > $this->unallocated_amount) {
            throw new \InvalidArgumentException('Allocation exceeds payment unallocated amount.');
        }

        if ($amount > $expense->remaining_amount) {
            throw new \InvalidArgumentException('Allocation exceeds expense remaining amount.');
        }

        $allocation = $this->allocations()->create([
            'expense_id' => $expense->id,
            'amount'     => $amount,
        ]);

        $this->decrement('unallocated_amount', $amount);

        $expense->paid_amount      = ($expense->paid_amount ?? 0) + $amount;
        $expense->remaining_amount = max(0, $expense->remaining_amount - $amount);
        $expense->is_paid          = $expense->remaining_amount <= 0;
        $expense->save();

        return $allocation;
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(AccountTransaction::class, 'transactionable');
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
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

        return \Illuminate\Support\Facades\DB::transaction(function () use ($due, $amount) {
            $allocations = app(\App\Services\DueCollection::class)->manual(
                $this->apartment_id, $this->account_id, [$this->id], [['due_id' => $due->id, 'amount' => $amount]],
            );
            if ($allocations === []) {
                throw new \InvalidArgumentException('Allocation has no payable target.');
            }
            $this->refresh();
            $due->refresh();

            return $allocations[0];
        });
    }

    protected function getReferencePrefix(): string
    {
        if ($this->account_id) {
            $accountType = Account::where('id', $this->account_id)->value('type');

            if ($accountType === Account::TYPE_SUPPLIER) {
                return 'ODE';
            }
        }

        return 'THZ';
    }
}
