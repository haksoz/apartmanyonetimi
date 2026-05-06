<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    public const TYPE_ALL = 'all';
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';

    protected $fillable = [
        'apartment_id',
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_INCOME => 'Gelir',
            self::TYPE_EXPENSE => 'Gider',
            default => 'Tümü',
        };
    }

    public static function createDefaultsFor(int $apartmentId): void
    {
        $defaults = [
            ['name' => 'Aidat', 'type' => self::TYPE_INCOME],
            ['name' => 'Demirbaş', 'type' => self::TYPE_INCOME],
            ['name' => 'Elektrik', 'type' => self::TYPE_EXPENSE],
            ['name' => 'Su', 'type' => self::TYPE_EXPENSE],
            ['name' => 'Asansör', 'type' => self::TYPE_EXPENSE],
            ['name' => 'Temizlik', 'type' => self::TYPE_EXPENSE],
            ['name' => 'Yönetim', 'type' => self::TYPE_EXPENSE],
            ['name' => 'Bakım', 'type' => self::TYPE_EXPENSE],
            ['name' => 'Diğer', 'type' => self::TYPE_ALL],
        ];

        foreach ($defaults as $default) {
            self::firstOrCreate(
                ['apartment_id' => $apartmentId, 'name' => $default['name']],
                ['type' => $default['type'], 'is_active' => true],
            );
        }
    }
}
