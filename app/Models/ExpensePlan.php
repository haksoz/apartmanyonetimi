<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpensePlan extends Model
{
    public const GENERATION_ON_SCHEDULE = 'on_schedule';

    public const GENERATION_CREATE_ALL_NOW = 'create_all_now';

    protected $fillable = [
        'apartment_id',
        'name',
        'description',
        'description_suffix',
        'category_id',
        'account_id',
        'amount',
        'start_date',
        'end_date',
        'generate_day',
        'generation_mode',
        'due_days',
        'is_active',
        'skipped_periods',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'generate_day' => 'integer',
        'due_days' => 'integer',
        'is_active' => 'boolean',
        'skipped_periods' => 'array',
    ];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public static function occurrenceKey(int $planId, string $periodYm): string
    {
        return $planId.':'.$periodYm;
    }

    public function coversPeriod(string $periodYm): bool
    {
        $periodStart = $periodYm.'-01';
        if ($periodStart < $this->start_date->format('Y-m-01')) {
            return false;
        }
        if ($this->end_date && $periodStart > $this->end_date->format('Y-m-01')) {
            return false;
        }

        return true;
    }
}
