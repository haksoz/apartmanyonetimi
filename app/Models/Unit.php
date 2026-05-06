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
}
