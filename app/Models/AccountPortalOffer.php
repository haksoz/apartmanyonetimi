<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountPortalOffer extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'apartment_id',
        'account_id',
        'user_id',
        'offered_by',
        'status',
        'accepted_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offered_by');
    }
}
