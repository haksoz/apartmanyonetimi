<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DueBatch extends Model
{
    public const SOURCE_EXPENSES = 'expenses';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_INDIVIDUAL = 'individual';

    public const DISTRIBUTION_EQUAL = 'equal';

    public const DISTRIBUTION_INDIVIDUAL = 'individual';

    public const DISTRIBUTION_SQUARE_METERS = 'square_meters';

    public const DISTRIBUTION_SHARE_COEFFICIENT = 'share_coefficient';

    protected $fillable = [
        'apartment_id',
        'due_plan_id',
        'category_id',
        'source_type',
        'distribution_type',
        'target_audience',
        'period',
        'source_period',
        'category_filter_ids',
        'source_amount',
        'description',
        'created_by',
    ];

    protected $casts = [
        'source_period' => 'date',
        'category_filter_ids' => 'array',
        'source_amount' => 'decimal:2',
    ];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DuePlan::class, 'due_plan_id');
    }

    public function getSourceTypeLabelAttribute(): string
    {
        return match ($this->source_type) {
            self::SOURCE_EXPENSES => 'Dönem giderlerinden',
            self::SOURCE_MANUAL => 'Manuel toplam',
            self::SOURCE_INDIVIDUAL => 'Birebir borçlandırma',
            default => ucfirst((string) $this->source_type),
        };
    }

    public function getDistributionTypeLabelAttribute(): string
    {
        return match ($this->distribution_type) {
            self::DISTRIBUTION_EQUAL => 'Eşit böl',
            self::DISTRIBUTION_INDIVIDUAL => 'Birebir',
            self::DISTRIBUTION_SQUARE_METERS => 'Metrekareye göre',
            self::DISTRIBUTION_SHARE_COEFFICIENT => 'Pay çarpanına göre',
            default => ucfirst((string) $this->distribution_type),
        };
    }

    public function getTargetAudienceLabelAttribute(): string
    {
        return match ($this->target_audience) {
            'tenant_priority' => 'Kiracı Öncelikli',
            'owner_only' => 'Sadece Sahipler',
            default => ucfirst((string) $this->target_audience),
        };
    }
}
