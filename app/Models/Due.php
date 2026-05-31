<?php

namespace App\Models;

use App\Models\Traits\HasReferenceNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Due extends Model
{
    use HasReferenceNumber, SoftDeletes;
    protected $fillable = [
        'apartment_id',
        'due_batch_id',
        'unit_id',
        'account_id',
        'category_id',
        'reference_number',
        'period',
        'amount',
        'remaining_amount',
        'due_date',
        'status',
        'description',
        'created_at_manual',
        'is_imported',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'due_date' => 'date',
        'created_at_manual' => 'date',
        'is_imported' => 'boolean',
    ];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DueBatch::class, 'due_batch_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations')
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

    public function getComputedStatusAttribute(): string
    {
        if ($this->remaining_amount == 0) {
            return 'paid';
        }
        if ($this->remaining_amount < $this->amount) {
            return 'partial';
        }
        if ($this->due_date && $this->due_date->isPast()) {
            return 'overdue';
        }
        return 'pending';
    }

    protected function getReferencePrefix(): string
    {
        return 'BRC';
    }
}
