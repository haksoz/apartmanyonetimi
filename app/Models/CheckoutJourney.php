<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckoutJourney extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['last_seen_at' => 'datetime', 'closed_at' => 'datetime', 'quoted_amount' => 'decimal:2'];

    public function getReferenceAttribute(): string
    {
        return 'GCS-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class)->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'order_id')->withTrashed();
    }

    public function events(): HasMany
    {
        return $this->hasMany(CheckoutJourneyEvent::class);
    }

    public function getProgressLabelAttribute(): string
    {
        $order = $this->order;
        return match (true) {
            $order?->status === UserSubscription::STATUS_ACTIVE => 'Etkinleşti',
            $order && $order->paymentAttempts->contains('review_required', true) => 'İnceleme gerekli',
            $order?->status === UserSubscription::STATUS_CANCELLED => 'İptal edildi',
            $this->closed_at !== null || $order?->checkout_abandoned_at !== null => 'Ekrandan çıkıldı',
            $order !== null => 'Ödeme bekleniyor',
            $this->last_seen_at->lt(now()->subMinutes(30)) => 'Tamamlanmadı',
            default => 'Devam ediyor',
        };
    }

    public function getStageLabelAttribute(): string
    {
        return ['plan' => 'Dönem seçimi', 'billing' => 'Fatura bilgileri', 'payment' => 'Ödeme yöntemi', 'distance' => 'Satış sözleşmesi', 'pre' => 'Ön bilgilendirme', 'submitted' => 'Ödeme başlatıldı'][$this->stage] ?? $this->stage;
    }
}
