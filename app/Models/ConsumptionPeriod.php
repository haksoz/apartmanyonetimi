<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsumptionPeriod extends Model
{
    protected $fillable = ['apartment_id', 'consumption_type', 'start_date', 'end_date', 'method', 'measurement_unit'];

    protected $casts = ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d'];

    public function batches(): HasMany
    {
        return $this->hasMany(DueBatch::class);
    }

    public function scopeUnused($query)
    {
        return $query->whereDoesntHave('batches', fn ($batch) => $batch->whereNull('consumption_cancelled_at'));
    }

    public function setStartDateAttribute(string $value): void
    {
        $this->attributes['start_date'] = $value;
    }

    public function setEndDateAttribute(string $value): void
    {
        $this->attributes['end_date'] = $value;
    }
}
