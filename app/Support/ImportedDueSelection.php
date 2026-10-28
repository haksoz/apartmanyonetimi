<?php

namespace App\Support;

use App\Enums\DueType;
use App\Models\Category;
use Illuminate\Validation\ValidationException;

class ImportedDueSelection
{
    public function validate(int $apartmentId, array $transactions, array $accounts, array $accountTypes, array $accountMapping, array $types, array $categories): array
    {
        $result = [];
        $errors = [];
        foreach ($transactions as $index => $transaction) {
            $key = $transaction['account_key'];
            $mapped = ! empty($accountMapping[$key]) ? \App\Models\Account::where('apartment_id', $apartmentId)->find($accountMapping[$key]) : null;
            $accountType = $mapped?->type ?? match ($accountTypes[$key] ?? '') {
                'former_owner' => 'owner', 'former_tenant' => 'tenant', default => $accountTypes[$key] ?? '',
            };
            if ($accountType === 'supplier' || $transaction['debit'] <= 0) {
                continue;
            }
            $row = $transaction['row_number'] ?? ($index + 1);
            $type = DueType::tryFrom((string) ($types[$row] ?? $transaction['due_type'] ?? ''));
            if (! $type) {
                $errors["due_types.$row"] = "Satır {$row}: borç türünü açıkça seçin (Aidat / Kat Maliki Payı).";
                continue;
            }
            $selection = $categories[$row] ?? null;
            if ($selection === 'new') {
                $name = trim($transaction['category_name'] ?? '');
                if ($name === '' || mb_strlen($name) > 255 || Category::withTrashed()->where('apartment_id', $apartmentId)->where('name', $name)->exists()) {
                    $errors["due_categories.$row"] = "Satır {$row}: yeni kategori adı boş/geçersiz veya zaten kullanılıyor. Mevcut kategoriyi seçin.";
                }
            } else {
                $category = is_scalar($selection) ? Category::where('apartment_id', $apartmentId)->where('is_active', true)
                    ->where($type->categoryPermission(), true)->find($selection) : null;
                if (! $category) {
                    $errors["due_categories.$row"] = "Satır {$row}: seçilen borç türünde kullanılabilen aktif bir kategori seçin.";
                }
            }
            if ($type === DueType::MalikPayi && $accountType !== 'owner') {
                $errors["due_types.$row"] = "Satır {$row}: Kat Maliki Payı kiracı hesabına yazılamaz.";
            }
            $result[$row] = ['type' => $type, 'category' => $selection, 'name' => trim($transaction['category_name'] ?? '')];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        return $result;
    }
}
