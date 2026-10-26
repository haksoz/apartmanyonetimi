<?php

namespace App\Models;

use App\Enums\DueType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class DuePlan extends Model
{
    public const AMOUNT_TYPE_MONTHLY  = 'monthly';
    public const AMOUNT_TYPE_YEARLY   = 'yearly';
    public const AMOUNT_TYPE_PER_UNIT = 'per_unit';

    public const DISTRIBUTION_EQUAL             = 'equal';
    public const DISTRIBUTION_SQUARE_METERS     = 'square_meters';
    public const DISTRIBUTION_SHARE_COEFFICIENT = 'share_coefficient';

    public const GENERATION_ON_SCHEDULE   = 'on_schedule';

    protected $fillable = [
        'apartment_id',
        'due_type',
        'category_id',
        'name',
        'plan_year',
        'start_date',
        'end_date',
        'amount_type',
        'monthly_amount',
        'yearly_amount',
        'per_unit_amount',
        'distribution_type',
        'round_to_integer',
        'target_audience',
        'due_day',
        'auto_generate',
        'generation_mode',
        'generate_day',
        'description',
        'is_active',
        'skipped_periods',
    ];

    protected $casts = [
        'plan_year'         => 'integer',
        'monthly_amount'    => 'decimal:2',
        'yearly_amount'     => 'decimal:2',
        'per_unit_amount'   => 'decimal:2',
        'due_day'           => 'integer',
        'auto_generate'     => 'boolean',
        'round_to_integer'  => 'boolean',
        'generate_day'      => 'integer',
        'start_date'        => 'date',
        'end_date'          => 'date',
        'is_active'         => 'boolean',
        'skipped_periods'   => 'array',
        'due_type'          => DueType::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (DuePlan $plan) {
            $plan->target_audience = 'tenant_priority';
            $plan->generation_mode = self::GENERATION_ON_SCHEDULE;
            $plan->auto_generate = true;
            if ($plan->plan_year) {
                $plan->start_date = sprintf('%04d-01-01', $plan->plan_year);
                $plan->end_date = sprintf('%04d-12-31', $plan->plan_year);
            } elseif ($plan->start_date && ! $plan->plan_year) {
                $plan->plan_year = (int) $plan->start_date->format('Y');
                $plan->start_date = sprintf('%04d-01-01', $plan->plan_year);
                $plan->end_date = sprintf('%04d-12-31', $plan->plan_year);
            }

            if (($plan->is_active ?? true) && self::query()->where('apartment_id', $plan->apartment_id)
                ->where('plan_year', $plan->plan_year)->where('is_active', true)
                ->when($plan->exists, fn ($query) => $query->whereKeyNot($plan->id))->exists()) {
                throw ValidationException::withMessages(['is_active' => 'Bu yıl için başka bir aktif aidat planı var. Önce onu pasife alın.']);
            }
            if (! $plan->generation_mode) {
                $plan->generation_mode = self::GENERATION_ON_SCHEDULE;
            }
        });
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function pageParameters(): array
    {
        $parameters = ['year' => $this->plan_year];
        if (self::where('apartment_id', $this->apartment_id)->where('plan_year', $this->plan_year)->count() > 1) {
            $parameters['plan_id'] = $this->id;
        }
        return $parameters;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function getDueTypeLabelAttribute(): string
    {
        return $this->due_type?->label() ?? '-';
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

        return (float) number_format((float) $this->per_unit_amount, 2, '.', '');
    }

    public function isGeneratedForPeriod(string $period): bool
    {
        return $this->batches()
            ->where('period', $period)
            ->whereHas('dues')
            ->exists();
    }

    public function createsOnSchedule(): bool
    {
        return ($this->generation_mode ?: self::GENERATION_ON_SCHEDULE) === self::GENERATION_ON_SCHEDULE;
    }

    public function skipsPeriod(string $period): bool
    {
        return $this->createsOnSchedule() && in_array($period, $this->skipped_periods ?? [], true);
    }

    public function getDistributionLabelAttribute(): string
    {
        return match ($this->distribution_type) {
            self::DISTRIBUTION_EQUAL             => 'Eşit böl',
            self::DISTRIBUTION_SQUARE_METERS     => 'Metrekareye göre',
            self::DISTRIBUTION_SHARE_COEFFICIENT => 'Pay çarpanına göre',
            default                              => ucfirst((string) $this->distribution_type),
        };
    }
}
