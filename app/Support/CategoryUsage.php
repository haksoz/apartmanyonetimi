<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

class CategoryUsage
{
    public const LABELS = [
        'allow_aidat' => 'Aidat (Daire Sakini) Borçlandırmasında Göster',
        'allow_malik_payi' => 'Kat Maliki Payı Borçlandırmasında Göster',
        'allow_expense' => 'Gider Girişinde Göster',
    ];

    public static function attributes(array $input): array
    {
        $permissions = collect(array_keys(Category::USAGE_PERMISSIONS))
            ->mapWithKeys(fn ($key) => [$key => (bool) ($input[$key] ?? false)])->all();
        if ($permissions['allow_expense'] && ! $permissions['allow_aidat'] && ! $permissions['allow_malik_payi']) {
            throw ValidationException::withMessages(['allow_expense' => 'Gider girişi için en az bir borçlandırma kullanım alanı seçilmelidir.']);
        }
        $relations = [];
        if ($permissions['allow_expense']) {
            if ($permissions['allow_aidat']) {
                $relations[] = 'aidat';
            }
            if ($permissions['allow_malik_payi']) {
                $relations[] = 'malik_payi';
            }
        }

        return [...$permissions, 'expense_relations' => $relations];
    }
}
