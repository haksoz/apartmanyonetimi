<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DuePlan extends Model
{
    public const AMOUNT_TYPE_MONTHLY  = 'monthly';
    public const AMOUNT_TYPE_YEARLY   = 'yearly';
    public const AMOUNT_TYPE_PER_UNIT = 'per_unit';

    public const DISTRIBUTION_EQUAL             = 'equal';
    public const DISTRIBUTION_SQUARE_METERS     = 'square_meters';
    public const DISTRIBUTION_SHARE_COEFFICIENT = 'share_coefficient';

    protected $fillable = [
        'apartment_id',
        'category_id',
        'name',
        'year',
        'amount_type',
        'monthly_amount',
        'yearly_amount',
        'per_unit_amount',
        'distribution_type',
        'target_audience',
        'due_day',
        'description',
        'is_active',
    ];

    protected $casts = [
        'monthly_amount'  => 'decimal:2',
        'yearly_amount'   => 'decimal:2',
        'per_unit_amount' => 'decimal:2',
        'due_day'        => 'integer',
        'year'           => 'integer',
        'is_active'      => 'boolean',
    ];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(DueBatch::class);
    }

    public function getMonthlyAmountResolvedAttribute(): float
    {
        if ($this->amount_type === self::AMOUNT_TYPE_MONTHLY) {
            return (float) $this->monthly_amount;
        }
        if ($this->amount_type === self::AMOUNT_TYPE_YEARLY) {
            return round((float) $this->yearly_amount / 12, 2);
        }
        // per_unit: daire başı tutar * daire sayısı = aylık toplam (aidat oluşturma sırasında hesaplanır)
        return (float) number_format((float) $this->per_unit_amount, 2, '.', '');
    }

    public function isGeneratedForPeriod(string $period): bool
    {
        return $this->batches()
            ->where('period', $period)
            ->whereHas('dues')
            ->exists();
    }

    public function getDistributionLabelAttribute(): string
    {
        return match ($this->distribution_type) {
            self::DISTRIBUTION_EQUAL             => 'Eşit',
            self::DISTRIBUTION_SQUARE_METERS     => 'Metrekareye göre',
            self::DISTRIBUTION_SHARE_COEFFICIENT => 'Pay çarpanına göre',
            default                              => ucfirst((string) $this->distribution_type),
        };
    }
}
