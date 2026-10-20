<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentUnitShare extends Model
{
    protected $fillable = [
        'assessment_plan_id',
        'unit_id',
        'share_amount',
        'weight',
        'square_meters',
        'share_coefficient',
    ];

    protected $casts = [
        'share_amount' => 'decimal:2',
        'weight' => 'decimal:4',
        'square_meters' => 'decimal:2',
        'share_coefficient' => 'decimal:4',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AssessmentPlan::class, 'assessment_plan_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
