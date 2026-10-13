<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class LegalAcceptance extends Model
{
    protected $fillable = [
        'user_id',
        'user_subscription_id',
        'apartment_id',
        'document_key',
        'document_version',
        'legal_document_version_id',
        'accepted_content',
        'accepted_at',
        'ip_address',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $acceptance): void {
            if ($acceptance->isDirty('accepted_content') && filled($acceptance->getOriginal('accepted_content'))) {
                throw new RuntimeException('Kabul edilen sözleşme metni değiştirilemez.');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function legalDocumentVersion(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentVersion::class);
    }
}
