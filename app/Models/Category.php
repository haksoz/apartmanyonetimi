<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class Category extends Model
{
    use SoftDeletes;

    public const TYPE_ALL = 'all';
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';
    public const USAGE_PERMISSIONS = [
        'allow_aidat' => 'Aidat',
        'allow_malik_payi' => 'Malik Payı',
        'allow_masraf_yansitma' => 'Masraf Yansıtma',
        'allow_ceza' => 'Ceza',
        'allow_faiz' => 'Faiz',
        'allow_expense' => 'Gider',
    ];

    protected $fillable = [
        'apartment_id',
        'name',
        'type',
        'is_active',
        'is_system',
        'allow_aidat', 'allow_malik_payi', 'allow_masraf_yansitma',
        'allow_ceza', 'allow_faiz', 'allow_expense',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'allow_aidat' => 'boolean',
        'allow_malik_payi' => 'boolean',
        'allow_masraf_yansitma' => 'boolean',
        'allow_ceza' => 'boolean',
        'allow_faiz' => 'boolean',
        'allow_expense' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (Category $category) {
            if ($category->isDefaultAidatCategory() && $category->isDirty([
                'name', 'is_active', ...array_keys(self::USAGE_PERMISSIONS),
            ])) {
                throw new LogicException('Genel Aidat kategorisinin adı, aktifliği ve kullanım yetkileri değiştirilemez.');
            }
        });

        static::deleting(function (Category $category) {
            if ($category->isDefaultAidatCategory()) {
                throw new LogicException('Genel Aidat kategorisi silinemez.');
            }
        });
    }

    public function isDefaultAidatCategory(): bool
    {
        return $this->exists && Apartment::query()
            ->whereKey($this->apartment_id)
            ->where('default_aidat_category_id', $this->getKey())
            ->exists();
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
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

    public function scopeOrderedForDisplay($query)
    {
        $rank = [
            'Genel Aidat' => 1,
            'Aidat' => 1,
            'Demirbaş' => 2,
            'Elektrik' => 3,
            'Su' => 4,
            'Asansör' => 5,
            'Temizlik' => 6,
            'Yönetim' => 7,
            'Bakım' => 8,
            'Ceza' => 9,
            'Faiz' => 10,
            'Diğer' => 1000,
        ];

        $sql = 'CASE name';
        foreach ($rank as $name => $position) {
            $sql .= " WHEN '".str_replace("'", "''", $name)."' THEN {$position}";
        }
        $sql .= ' ELSE 100 END';

        return $query->orderByRaw($sql)->orderBy('name');
    }

    public function scopeAllowedFor($query, string $permission)
    {
        if (! array_key_exists($permission, self::USAGE_PERMISSIONS)) {
            throw new \InvalidArgumentException("Bilinmeyen kategori kullanım yetkisi: {$permission}");
        }

        return $query->where($permission, true);
    }

    public function getUsageLabelsAttribute(): array
    {
        return collect(self::USAGE_PERMISSIONS)->filter(fn ($label, $key) => $this->{$key})->values()->all();
    }

    public static function createDefaultsFor(int $apartmentId): void
    {
        $defaults = [
            ['name' => 'Genel Aidat', 'type' => self::TYPE_INCOME, 'is_system' => true, 'allow_aidat' => true],
            ['name' => 'Demirbaş', 'type' => self::TYPE_ALL, 'is_system' => true],
            ['name' => 'Elektrik', 'type' => self::TYPE_EXPENSE, 'is_system' => false],
            ['name' => 'Su', 'type' => self::TYPE_EXPENSE, 'is_system' => false],
            ['name' => 'Asansör', 'type' => self::TYPE_EXPENSE, 'is_system' => false],
            ['name' => 'Temizlik', 'type' => self::TYPE_EXPENSE, 'is_system' => false],
            ['name' => 'Yönetim', 'type' => self::TYPE_EXPENSE, 'is_system' => false],
            ['name' => 'Bakım', 'type' => self::TYPE_EXPENSE, 'is_system' => false],
            ['name' => 'Ceza', 'type' => self::TYPE_INCOME, 'is_system' => true],
            ['name' => 'Faiz', 'type' => self::TYPE_INCOME, 'is_system' => true],
            ['name' => 'Diğer', 'type' => self::TYPE_ALL, 'is_system' => true],
        ];

        foreach ($defaults as $default) {
            $permissions = array_fill_keys(array_keys(self::USAGE_PERMISSIONS), false);
            if ($default['type'] !== self::TYPE_INCOME) {
                $permissions['allow_expense'] = true;
            }
            if ($default['type'] !== self::TYPE_EXPENSE) {
                $permissions['allow_malik_payi'] = true;
                $permissions['allow_masraf_yansitma'] = true;
            }
            if ($default['name'] === 'Genel Aidat') {
                $permissions = [...array_fill_keys(array_keys(self::USAGE_PERMISSIONS), false), 'allow_aidat' => true];
            } elseif ($default['name'] === 'Ceza') {
                $permissions = [...array_fill_keys(array_keys(self::USAGE_PERMISSIONS), false), 'allow_ceza' => true];
            } elseif ($default['name'] === 'Faiz') {
                $permissions = [...array_fill_keys(array_keys(self::USAGE_PERMISSIONS), false), 'allow_faiz' => true];
            }
            if ($default['name'] === 'Genel Aidat' && self::withTrashed()->where('apartment_id', $apartmentId)->whereIn('name', ['Genel Aidat', 'Aidat'])->exists()) {
                continue;
            }
            self::firstOrCreate(
                ['apartment_id' => $apartmentId, 'name' => $default['name']],
                ['type' => $default['type'], 'is_active' => true, 'is_system' => $default['is_system'], ...$permissions],
            );
        }

        $aidat = self::where('apartment_id', $apartmentId)->whereIn('name', ['Genel Aidat', 'Aidat'])
            ->orderByRaw("name = 'Genel Aidat' desc")->first();
        if ($aidat) {
            if ($aidat->name === 'Aidat' && ! self::withTrashed()->where('apartment_id', $apartmentId)->where('name', 'Genel Aidat')->exists()) {
                $aidat->name = 'Genel Aidat';
            }
            $aidat->is_active = true;
            foreach (array_keys(self::USAGE_PERMISSIONS) as $permission) {
                $aidat->{$permission} = $permission === 'allow_aidat';
            }
            $aidat->save();
            Apartment::whereKey($apartmentId)->whereNull('default_aidat_category_id')
                ->update(['default_aidat_category_id' => $aidat->id]);
        }
    }
}
