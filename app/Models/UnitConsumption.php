<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitConsumption extends Model
{
    protected $fillable = ['apartment_id', 'unit_id', 'consumption_type', 'consumption_period_id', 'measurement_unit', 'method', 'value', 'previous_value', 'consumption'];

    protected $casts = ['value' => 'decimal:4', 'previous_value' => 'decimal:4', 'consumption' => 'decimal:4'];

    public function readingPeriod(): BelongsTo
    {
        return $this->belongsTo(ConsumptionPeriod::class, 'consumption_period_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
