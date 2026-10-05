<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceBand extends Model
{
    protected $fillable = [
        'label',
        'min_units',
        'max_units',
        'monthly_price',
        'yearly_price',
        'is_quote',
        'is_active',
        'valid_from',
        'valid_until',
        'sort_order',
    ];

    protected $casts = [
        'min_units' => 'integer',
        'max_units' => 'integer',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'is_quote' => 'boolean',
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function campaigns(): HasMany
    {
        return $this->hasMany(PriceCampaign::class);
    }
}
