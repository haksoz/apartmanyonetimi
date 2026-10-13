<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApartmentDataOperation extends Model
{
    public const ACTION_WIPE = 'operational_wipe';

    public const ACTION_RENEW = 'setup_renew';

    public const ACTION_NEW_APARTMENT = 'new_free_apartment';

    public const RESULT_PENDING = 'pending';

    public const RESULT_COMPLETED = 'completed';

    public const RESULT_BLOCKED = 'blocked';

    public const RESULT_QUOTE = 'quote';

    public const RESULT_AWAITING = 'awaiting_confirmation';

    public const RESULT_ARCHIVE_FAILED = 'archive_failed';

    public const RESULT_FAILED = 'failed';

    public const ARCHIVE_UNAVAILABLE = 'unavailable';

    public const ARCHIVE_SKIPPED = 'not_required';

    public const ARCHIVE_ACCEPTED = 'accepted';

    /** Geçici: kullanıcı yedek kontrolünü atladı. Yedek alındığı anlamına gelmez. */
    public const ARCHIVE_BYPASSED = 'bypassed';

    protected $fillable = [
        'user_id',
        'actor_name',
        'apartment_id',
        'source_apartment_id',
        'apartment_name',
        'unit_count_before',
        'subscription_no_before',
        'outcome_apartment_id',
        'action',
        'result',
        'archive_status',
        'archive_reference',
        'archive_retain_until',
        'requested_unit_count',
        'scope',
        'note',
    ];

    protected $casts = [
        'archive_retain_until' => 'datetime',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function context(User $actor, Apartment $apartment): array
    {
        return [
            'user_id' => $actor->id,
            'actor_name' => $actor->name,
            'apartment_id' => $apartment->id,
            'source_apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count_before' => (int) $apartment->unit_count,
            'subscription_no_before' => Subscription::query()
                ->where('apartment_id', $apartment->id)
                ->value('subscription_no'),
        ];
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_WIPE => 'İşlem Verisini Sil',
            self::ACTION_RENEW => 'Verileri Sil ve Kurulumu Yenile',
            self::ACTION_NEW_APARTMENT => 'Yeni ücretsiz apartman',
            default => $this->action,
        };
    }

    public function resultLabel(): string
    {
        return match ($this->result) {
            self::RESULT_COMPLETED => 'Başarılı',
            self::RESULT_ARCHIVE_FAILED, self::RESULT_FAILED, self::RESULT_BLOCKED => 'Başarısız',
            default => 'Tamamlanamadı',
        };
    }

    public function archiveLabel(): string
    {
        return match ($this->archive_status) {
            self::ARCHIVE_BYPASSED => 'Atlandı. Yedek alınmadı.',
            self::ARCHIVE_ACCEPTED => 'Kontrol geçti. Arşiv dosyası yok.',
            self::ARCHIVE_SKIPPED => 'Silme olmadığı için kontrol gerekmedi.',
            default => 'Atlanmadı. Yedek alınmadı.',
        };
    }

    public function liveApartment(): ?Apartment
    {
        $id = $this->source_apartment_id ?: $this->apartment_id;

        if (! $id) {
            return null;
        }

        return Apartment::withTrashed()->find($id);
    }

    public function apartmentPresenceLabel(): string
    {
        $apartment = $this->liveApartment();

        if (! $apartment || $apartment->trashed()) {
            return 'Kayıt yok';
        }

        return $apartment->is_active ? 'Aktif' : 'Pasif';
    }

    public function recordedSubscriptionLabel(): string
    {
        return $this->subscription_no_before
            ? 'İşlem anındaki abonelik numarası: '.$this->subscription_no_before
            : 'İşlem anında abonelik numarası yok';
    }

    public function liveSubscriptionRecordLabel(): string
    {
        $live = $this->liveSubscription();

        if (! $live) {
            return 'Güncel abonelik kaydı yok';
        }

        if ($this->subscription_no_before && $live->subscription_no !== $this->subscription_no_before) {
            return 'Güncel abonelik numarası değişmiş. İşlem anı: '.$this->subscription_no_before.'. Şimdi: '.$live->subscription_no;
        }

        return 'Güncel abonelik kaydı duruyor: '.$live->subscription_no;
    }

    public function liveSubscriptionItemsLabel(): string
    {
        $id = $this->source_apartment_id ?: $this->apartment_id;
        $items = $id
            ? SubscriptionItem::query()->where('apartment_id', $id)->count()
            : 0;

        if ($this->liveSubscription() && $items === 0) {
            return 'Abonelik kaydı var; abonelik kalemi bulunamadı';
        }

        if ($items === 0) {
            return 'Güncel abonelik kalemi yok';
        }

        return 'Güncel abonelik kalemi var: '.$items;
    }

    public function livePaidPeriodLabel(): string
    {
        $id = $this->source_apartment_id ?: $this->apartment_id;

        $subscription = $this->liveSubscription();

        if (! $id || ! $subscription) {
            return 'Güncel ücretli dönem yok';
        }

        $covering = SubscriptionItem::query()
            ->where('apartment_id', $id)
            ->where('apartment_subscription_id', $subscription->id)
            ->covering()
            ->exists();

        if ($covering) {
            return 'Güncel ücretli dönem aktif';
        }

        $paid = SubscriptionItem::query()
            ->where('apartment_id', $id)
            ->where('plan', SubscriptionItem::PLAN_PAID)
            ->exists();

        return $paid ? 'Güncel ücretli dönem aktif değil' : 'Güncel ücretli dönem yok';
    }

    private function liveSubscription(): ?Subscription
    {
        $id = $this->source_apartment_id ?: $this->apartment_id;

        if (! $id) {
            return null;
        }

        return Subscription::query()->where('apartment_id', $id)->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }
}
