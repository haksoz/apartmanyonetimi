<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;
use App\Enums\ExpenseRelation;

class Category extends Model
{
    use SoftDeletes;

    public const OPERATING_EXPENSE_CATEGORIES = [
        'Ortak Elektrik', 'Ortak Su', 'Ortak Temizlik', 'Ortak Asansör', 'Yönetim Giderleri',
    ];

    public const TYPE_ALL = 'all';
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';
    public const USAGE_PERMISSIONS = [
        'allow_aidat' => 'Aidat',
        'allow_malik_payi' => 'Kat Maliki Payı',
        'allow_expense' => 'Gider',
    ];

    protected $fillable = [
        'apartment_id',
        'name',
        'type',
        'is_active',
        'is_system',
        'expense_relations',
        'allow_aidat', 'allow_malik_payi', 'allow_expense',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'expense_relations' => 'array',
        'sort_order' => 'integer',
        'allow_aidat' => 'boolean',
        'allow_malik_payi' => 'boolean',
        'allow_expense' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            $category->expense_relations = ! $category->allow_expense ? []
                : (in_array($category->name, self::OPERATING_EXPENSE_CATEGORIES, true)
                    ? ['aidat'] : ($category->expense_relations ?? []));
        });
        static::updating(function (Category $category) {
            if (! $category->allow_expense) {
                $category->expense_relations = [];
            }
            if ($category->isExpenseRelationLocked() && $category->isDirty('expense_relations')) {
                throw new LogicException('Bu kategorinin gider ilişkisi İşletme Gideri olarak sabittir.');
            }
            if ($category->isDemirbasCategory() && $category->isDirty([
                'name', 'is_active', 'type', 'is_system', 'apartment_id', 'allow_malik_payi', 'allow_expense',
            ])) {
                throw new LogicException('Demirbaş kategorisinde yalnızca Aidat kullanım yetkisi ve gider ilişkileri değiştirilebilir.');
            }
            if ($category->isLocked() && $category->isDirty([
                'name', 'is_active', ...array_keys(self::USAGE_PERMISSIONS),
            ])) {
                throw new LogicException($category->getOriginal('name').' kategorisinin adı, aktifliği ve kullanım yetkileri değiştirilemez.');
            }
        });

        static::deleting(function (Category $category) {
            if ($category->isLocked() || $category->isDemirbasCategory()) {
                throw new LogicException($category->getOriginal('name').' kategorisi silinemez.');
            }
        });
    }

    public function getSuggestedExpenseRelationAttribute(): ExpenseRelation
    {
        $values = $this->expense_relations ?? [];
        return count($values) === 1 ? ExpenseRelation::from($values[0]) : ExpenseRelation::Unassigned;
    }

    public function isExpenseRelationLocked(): bool
    {
        return $this->exists && in_array($this->getOriginal('name'), self::OPERATING_EXPENSE_CATEGORIES, true);
    }

    public function isCommonExpenseCategory(): bool
    {
        return $this->exists && in_array($this->getOriginal('name'), [
            'Ortak Elektrik', 'Ortak Su', 'Ortak Temizlik', 'Ortak Asansör', 'Yönetim Giderleri', 'Bakım Onarım', 'Diğer',
        ], true);
    }

    public function isDemirbasCategory(): bool
    {
        return $this->exists && $this->getOriginal('name') === 'Demirbaş';
    }

    public function isLocked(): bool
    {
        return $this->isDefaultAidatCategory() || $this->isCommonExpenseCategory();
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

        return $query->orderByRaw('sort_order IS NULL')->orderBy('sort_order')->orderByRaw($sql)->orderBy('name')->orderBy('id');
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
            // Snapshot of the protected categories agreed from Akbey (apartment 8).
            // New apartments use this fixed template, never another apartment's live data.
            ['name' => 'Genel Aidat', 'type' => self::TYPE_INCOME, 'is_system' => true, 'sort_order' => 1],
            ['name' => 'Demirbaş', 'type' => self::TYPE_ALL, 'is_system' => true, 'sort_order' => 2],
            ['name' => 'Ortak Elektrik', 'type' => self::TYPE_EXPENSE, 'is_system' => false, 'sort_order' => 3],
            ['name' => 'Ortak Temizlik', 'type' => self::TYPE_EXPENSE, 'is_system' => false, 'sort_order' => 4],
            ['name' => 'Ortak Su', 'type' => self::TYPE_EXPENSE, 'is_system' => false, 'sort_order' => 5],
            ['name' => 'Ortak Asansör', 'type' => self::TYPE_EXPENSE, 'is_system' => false, 'sort_order' => 6],
            ['name' => 'Yönetim Giderleri', 'type' => self::TYPE_EXPENSE, 'is_system' => false, 'sort_order' => 7],
            ['name' => 'Bakım Onarım', 'type' => self::TYPE_EXPENSE, 'is_system' => false, 'sort_order' => 8],
            ['name' => 'Diğer', 'type' => self::TYPE_ALL, 'is_system' => true, 'sort_order' => 18],
        ];

        foreach ($defaults as $default) {
            $isAidat = $default['name'] === 'Genel Aidat';
            $bothRelations = in_array($default['name'], ['Demirbaş', 'Bakım Onarım', 'Diğer'], true);
            $permissions = [
                'allow_aidat' => true,
                'allow_malik_payi' => $bothRelations,
                'allow_expense' => ! $isAidat,
                'expense_relations' => $isAidat ? [] : ($bothRelations ? ['aidat', 'malik_payi'] : ['aidat']),
            ];
            if ($default['name'] === 'Genel Aidat' && self::withTrashed()->where('apartment_id', $apartmentId)->whereIn('name', ['Genel Aidat', 'Aidat'])->exists()) {
                continue;
            }
            $category = self::firstOrCreate(
                ['apartment_id' => $apartmentId, 'name' => $default['name']],
                ['type' => $default['type'], 'is_active' => true, 'is_system' => $default['is_system'], ...$permissions],
            );
            if ($category->wasRecentlyCreated) {
                $category->forceFill(['sort_order' => $default['sort_order']])->save();
            }
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
