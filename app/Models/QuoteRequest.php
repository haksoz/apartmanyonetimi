<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONTACTED,
        self::STATUS_CONVERTED,
        self::STATUS_CANCELLED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Bekliyor',
        self::STATUS_CONTACTED => 'Görüşüldü',
        self::STATUS_CONVERTED => 'Dönüştürüldü',
        self::STATUS_CANCELLED => 'İptal',
    ];

    protected $fillable = [
        'user_id',
        'apartment_name',
        'unit_count',
        'status',
    ];

    protected static function booted(): void
    {
        static::saving(function (QuoteRequest $request) {
            if ((int) $request->unit_count < 101) {
                throw new \InvalidArgumentException('Teklif talebi en az 101 daire içindir.');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(User $user, string $apartmentName, int $unitCount): self
    {
        $apartmentName = trim($apartmentName);

        $existing = static::query()
            ->where('user_id', $user->id)
            ->where('apartment_name', $apartmentName)
            ->where('unit_count', $unitCount)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONTACTED])
            ->first();

        if ($existing) {
            return $existing;
        }

        return static::create([
            'user_id' => $user->id,
            'apartment_name' => $apartmentName,
            'unit_count' => $unitCount,
            'status' => self::STATUS_PENDING,
        ]);
    }
}
