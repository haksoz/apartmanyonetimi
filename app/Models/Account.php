<?php

namespace App\Models;

use App\Models\Category;
use App\Models\Due;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    public const TYPE_OWNER = 'owner';

    public const TYPE_TENANT = 'tenant';

    public const TYPE_RESIDENT = 'resident';

    public const TYPE_SUPPLIER = 'supplier';

    protected $fillable = [
        'apartment_id',
        'unit_id',
        'type',
        'name',
        'phone',
        'email',
        'balance',
        'account_opening_date',
        'is_active',
        'default_category_id',
    ];

    protected $casts = [
        'account_opening_date' => 'date',
    ];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function tenantAssignments(): HasMany
    {
        return $this->hasMany(TenantAssignment::class);
    }

    public function getActiveTenantAssignmentAttribute(): ?TenantAssignment
    {
        return $this->tenantAssignments()
            ->whereNull('move_out_date')
            ->latest('move_in_date')
            ->latest()
            ->first();
    }

    public function getLedgerDebitAttribute(): float
    {
        return (float) $this->transactions()->where('type', 'debit')->sum('amount');
    }

    public function getLedgerCreditAttribute(): float
    {
        return (float) $this->transactions()->where('type', 'credit')->sum('amount');
    }

    public function getLedgerBalanceAttribute(): float
    {
        return $this->ledger_credit - $this->ledger_debit;
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_RESIDENT => 'Daire Sakini',
            self::TYPE_OWNER => 'Kat Maliki',
            self::TYPE_TENANT => 'Kiracı',
            self::TYPE_SUPPLIER => 'Tedarikçi',
            default => ucfirst((string) $this->type),
        };
    }
}
