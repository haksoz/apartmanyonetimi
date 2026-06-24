<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    use HasFactory;

    public const PERIOD_MONTHLY = 'monthly';

    public const PERIOD_YEARLY = 'yearly';

    protected $fillable = [
        'user_id',
        'package_id',
        'period',
        'price',
        'started_at',
        'expires_at',
        'is_active',
        'feature_auto_dues',
        'feature_user_portal',
        'feature_reports',
        'feature_multi_apartment',
        'multi_apartment_limit_override',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'feature_auto_dues' => 'boolean',
        'feature_user_portal' => 'boolean',
        'feature_reports' => 'boolean',
        'feature_multi_apartment' => 'boolean',
        'multi_apartment_limit_override' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
