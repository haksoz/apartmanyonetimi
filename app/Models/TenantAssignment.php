<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAssignment extends Model
{
    protected $fillable = [
        'apartment_id',
        'unit_id',
        'account_id',
        'move_in_date',
        'move_out_date',
    ];

    protected $casts = [
        'move_in_date' => 'date',
        'move_out_date' => 'date',
    ];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
