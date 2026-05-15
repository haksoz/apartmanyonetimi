<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $fillable = [
        'apartment_id',
        'owner_account_id',
        'occupant_account_id',
        'unit_no',
        'floor',
        'block',
        'resident_name',
        'phone',
        'square_meters',
        'share_coefficient',
    ];

    protected $casts = [
        'square_meters' => 'decimal:2',
        'share_coefficient' => 'decimal:4',
    ];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function ownerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'owner_account_id');
    }

    public function occupantAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'occupant_account_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function dueAccount(): ?Account
    {
        return $this->occupantAccount
            ?? $this->ownerAccount
            ?? $this->accounts()->whereIn('type', [Account::TYPE_TENANT, Account::TYPE_OWNER, Account::TYPE_RESIDENT])->first();
    }

    public function ownerHistories(): HasMany
    {
        return $this->hasMany(UnitOwnerHistory::class)->orderByDesc('start_date');
    }

    public function tenantAssignments(): HasMany
    {
        return $this->hasMany(TenantAssignment::class)->orderByDesc('move_in_date');
    }
}
