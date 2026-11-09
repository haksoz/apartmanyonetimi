<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class DueSearch
{
    public static function apply(Builder $query, string $search): void
    {
        $search = self::normalize($search);
        $search = preg_replace('/\b(?:daire|d)\s*0*([0-9]+)\b/u', 'd$1', $search);
        $terms = preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($terms as $term) {
            $query->where(function (Builder $inner) use ($term) {
                // Use a literal LIKE pattern: user-entered % and _ are not wildcards.
                $pattern = '%'.strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                $isUnit = preg_match('/^d([0-9]+)$/', $term, $matches);
                if (! $isUnit) {
                    $inner->whereHas('account', fn ($account) => $account
                        ->whereRaw(self::column('name')." LIKE ? ESCAPE '!'", [$pattern]))
                        ->orWhereHas('unit', fn ($unit) => $unit
                            ->whereRaw(self::column('unit_no')." LIKE ? ESCAPE '!'", [$pattern]))
                        ->orWhereRaw(self::column('description')." LIKE ? ESCAPE '!'", [$pattern]);
                }

                if ($isUnit) {
                    $number = ltrim($matches[1], '0') ?: '0';
                    $inner->orWhereHas('unit', function ($unit) use ($number) {
                        $column = 'TRIM('.self::column('unit_no').')';
                        // Leading zeros are insignificant, but 8 must never match 80 or 18.
                        $trimmed = $unit->getConnection()->getDriverName() === 'sqlite'
                            ? "LTRIM({$column}, '0')"
                            : "TRIM(LEADING '0' FROM {$column})";
                        $unit->whereRaw("{$trimmed} = ?", [$number === '0' ? '' : $number]);
                    });
                    $description = self::column('description');
                    $description = "REPLACE({$description}, 'daire ', 'd')";
                    $description = "REPLACE({$description}, 'd ', 'd')";
                    for ($i = 0; $i < 10; $i++) {
                        $description = "REPLACE({$description}, 'd0', 'd')";
                    }
                    $description = $inner->getConnection()->getDriverName() === 'sqlite'
                        ? "(' ' || {$description} || ' ')"
                        : "CONCAT(' ', {$description}, ' ')";
                    $inner->orWhereRaw("{$description} LIKE ?", ['% d'.$number.' %']);
                }
            });
        }
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(strtr($value, ['İ' => 'i', 'I' => 'i']));
        $value = strtr($value, ['ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c']);

        return preg_replace('/[\s.,:;\/\\\\()\[\]{}\-]+/u', ' ', $value);
    }

    private static function column(string $column): string
    {
        $sql = "COALESCE({$column}, '')";
        foreach (['İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g', 'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o', 'Ç' => 'c', 'ç' => 'c'] as $from => $to) {
            $sql = "REPLACE({$sql}, '{$from}', '{$to}')";
        }
        $sql = "LOWER({$sql})";
        foreach (['.', ',', ':', ';', '/', '(', ')', '[', ']', '{', '}', '-', "\t", "\n", "\r"] as $separator) {
            $sql = "REPLACE({$sql}, '{$separator}', ' ')";
        }
        for ($i = 0; $i < 5; $i++) {
            $sql = "REPLACE({$sql}, '  ', ' ')";
        }

        return $sql;
    }
}
