<?php

namespace App\Models;

use App\Enums\DueType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentPlan extends Model
{
    public const GENERATION_ON_SCHEDULE = 'on_schedule';

    public const GENERATION_CREATE_ALL_NOW = 'create_all_now';

    public static function debtTypes(): array
    {
        return [DueType::MasrafYansitma, DueType::MalikPayi];
    }

    protected $fillable = [
        'apartment_id',
        'name',
        'description',
        'total_amount',
        'distribution_type',
        'target_audience',
        'due_type',
        'category_id',
        'installment_count',
        'generation_mode',
        'generate_day',
        'is_active',
        'skipped_periods',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'installment_count' => 'integer',
        'generate_day' => 'integer',
        'is_active' => 'boolean',
        'skipped_periods' => 'array',
        'due_type' => DueType::class,
    ];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function installments(): HasMany
    {
        return $this->hasMany(AssessmentInstallment::class)->orderBy('sequence');
    }

    public function unitShares(): HasMany
    {
        return $this->hasMany(AssessmentUnitShare::class);
    }

    public function installmentLines(): HasMany
    {
        return $this->hasMany(AssessmentInstallmentLine::class);
    }

    public function createsOnSchedule(): bool
    {
        return $this->generation_mode === self::GENERATION_ON_SCHEDULE;
    }

    public function installmentDescription(int $sequence): string
    {
        return $this->name.' '.$sequence.'/'.$this->installment_count;
    }
}
