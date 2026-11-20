<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckoutJourney extends Model
{
    public const FORM_TIMEOUT_SECONDS = 120;
    public const RENEWAL_RESPONSE_SECONDS = 20;

    protected $guarded = ['id'];

    protected $casts = ['last_seen_at' => 'datetime', 'closed_at' => 'datetime', 'quoted_amount' => 'decimal:2'];

    protected static function booted(): void
    {
        static::creating(function (CheckoutJourney $journey) {
            if (! $journey->reference_number) {
                $journey->reference_number = static::nextReferenceNumber();
            }
        });
    }

    public static function nextReferenceNumber(): string
    {
        $prefix = 'GCS-'.now()->format('y').'-';
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $number = $prefix.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            if (! static::where('reference_number', $number)->exists()) return $number;
        }

        throw new \RuntimeException('Benzersiz geçiş takip numarası üretilemedi.');
    }

    public function getReferenceAttribute(): string
    {
        return $this->reference_number ?: 'GCS-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
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
        $attempt = $order?->paymentAttempts->sortByDesc('id')->first();
        return match (true) {
            $order?->status === UserSubscription::STATUS_ACTIVE => 'Etkinleşti',
            $order && $order->paymentAttempts->contains('review_required', true) => 'İnceleme gerekli',
            $attempt?->token_failure_code && ! $attempt->iframe_token => 'Ödeme ekranı açılamadı',
            $attempt?->status === 'failed' => 'Ödeme başarısız',
            $order?->status === UserSubscription::STATUS_CANCELLED && $this->events()->where('type', 'superseded')->exists() => 'Yeni denemeyle değiştirildi',
            $order?->status === UserSubscription::STATUS_CANCELLED => 'İptal edildi',
            $this->is_card_screen_expired => 'Ödeme ekranının süresi doldu',
            $this->closed_at !== null && $this->events()->where('type', 'timeout_closed')->exists() => 'Süre dolduğu için kapatıldı',
            $this->closed_at !== null || $order?->checkout_abandoned_at !== null => 'Ekrandan çıkıldı',
            $order !== null => 'Ödeme bekleniyor',
            $this->isFormTimedOut() => 'Tamamlanmadı',
            default => 'Devam ediyor',
        };
    }

    public function isFormTimedOut(): bool
    {
        return ! $this->order_id && ! $this->closed_at
            && $this->last_seen_at->lte(now()->subSeconds(self::FORM_TIMEOUT_SECONDS));
    }

    public function getIsCardScreenExpiredAttribute(): bool
    {
        $order = $this->order;
        if (! $order || ! $order->isPending() || $order->payment_method !== 'kredi_kartı'
            || $order->paymentAttempts->contains('review_required', true)) return false;
        if ($this->events()->where('type', 'card_expired')->exists()) return true;
        if ($this->closed_at || $order->checkout_abandoned_at) return false;
        $attempt = $order->paymentAttempts->sortByDesc('id')->first();

        return $attempt && in_array($attempt->status, ['pending', 'unknown'], true)
            && $attempt->submitted_at?->lte(now()->subMinutes((int) config('paytr.payment_timeout_minutes', 30)));
    }

    public function scopeExpiredCardScreen(Builder $query): Builder
    {
        return $query->whereHas('order', fn ($query) => $query->where('status', 'pending')->where('payment_method', 'kredi_kartı')
            ->whereDoesntHave('paymentAttempts', fn ($query) => $query->where('review_required', true)))
            ->where(fn ($query) => $query->whereHas('events', fn ($query) => $query->where('type', 'card_expired'))
                ->orWhere(fn ($query) => $query->whereNull('closed_at')
                    ->whereHas('order', fn ($query) => $query->whereNull('checkout_abandoned_at')
                        ->whereHas('paymentAttempts', fn ($query) => $query->where('provider', 'paytr')
                            ->whereIn('status', ['pending', 'unknown'])
                            ->where('submitted_at', '<=', now()->subMinutes((int) config('paytr.payment_timeout_minutes', 30)))))));
    }

    public function getStageLabelAttribute(): string
    {
        return ['plan' => 'Dönem seçimi', 'billing' => 'Fatura bilgileri', 'payment' => 'Ödeme yöntemi', 'distance' => 'Satış sözleşmesi', 'pre' => 'Ön bilgilendirme', 'submitted' => 'Ödeme başlatıldı'][$this->stage] ?? $this->stage;
    }
}
