<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceCampaign extends Model
{
    protected $fillable = [
        'price_band_id',
        'name',
        'percent_off',
        'amount_off',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'percent_off' => 'decimal:2',
        'amount_off' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function band(): BelongsTo
    {
        return $this->belongsTo(PriceBand::class, 'price_band_id');
    }
}
