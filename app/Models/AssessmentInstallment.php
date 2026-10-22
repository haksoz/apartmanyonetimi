<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentInstallment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'assessment_plan_id',
        'sequence',
        'due_date',
        'period',
        'status',
        'generated_at',
        'cancelled_at',
        'due_batch_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'generated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'sequence' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AssessmentPlan::class, 'assessment_plan_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AssessmentInstallmentLine::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DueBatch::class, 'due_batch_id');
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
