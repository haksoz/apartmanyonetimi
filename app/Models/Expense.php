<?php

namespace App\Models;

use App\Models\Traits\HasReferenceNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Relations\MorphMany;

class Expense extends Model
{
    use SoftDeletes, HasReferenceNumber;

    protected $fillable = [
        'apartment_id',
        'account_id',
        'category_id',
        'category',
        'description',
        'amount',
        'paid_amount',
        'remaining_amount',
        'expense_date',
        'due_date',
        'period_month',
        'is_paid',
        'is_imported',
        'reference_number',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'expense_date' => 'date',
        'due_date' => 'date',
        'period_month' => 'date',
        'is_paid' => 'boolean',
        'is_imported' => 'boolean',
    ];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(AccountTransaction::class, 'transactionable');
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    protected function getReferencePrefix(): string
    {
        return 'GDR';
    }
}
