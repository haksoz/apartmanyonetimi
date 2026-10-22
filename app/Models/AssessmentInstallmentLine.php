<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentInstallmentLine extends Model
{
    protected $fillable = [
        'assessment_plan_id',
        'assessment_installment_id',
        'unit_id',
        'amount',
        'due_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AssessmentPlan::class, 'assessment_plan_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(AssessmentInstallment::class, 'assessment_installment_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function due(): BelongsTo
    {
        return $this->belongsTo(Due::class);
    }
}
