<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LateCompensationPolicy extends Model
{
    protected $fillable = ['effective_from', 'is_active', 'monthly_rate', 'created_by'];

    protected $attributes = ['is_active' => false];

    protected $casts = [
        'effective_from' => 'date',
        'is_active' => 'boolean',
        'monthly_rate' => 'decimal:6',
    ];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
