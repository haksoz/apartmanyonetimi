<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPayment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'subscription_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference_code',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class);
    }
}
