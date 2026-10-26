<?php

namespace App\Support;

use App\Enums\DueType;
use App\Models\Category;
use Illuminate\Validation\Rule;

class DueCategorySelection
{
    public function options(int $apartmentId, ?int $selectedId = null)
    {
        return Category::query()->when($selectedId, fn ($query) => $query->withTrashed())->where('apartment_id', $apartmentId)
            ->where(fn ($query) => $query->where('is_active', true)->when($selectedId, fn ($query) => $query->orWhere('id', $selectedId)))
            ->orderedForDisplay()->get();
    }

    public function rules(mixed $dueType, int $apartmentId, ?int $existingCategoryId = null, ?string $existingDueType = null): array
    {
        $type = is_string($dueType) ? DueType::tryFrom($dueType) : null;
        $selectsCategory = (bool) $type;

        return [
            Rule::requiredIf($selectsCategory), 'nullable', 'integer',
            Rule::exists('categories', 'id')->where(function ($query) use ($apartmentId, $type, $existingCategoryId, $existingDueType, $dueType) {
                $query->where('apartment_id', $apartmentId);
                $preservesExisting = $existingCategoryId && $existingDueType === $dueType;
                $query->where(function ($allowed) use ($type, $existingCategoryId, $preservesExisting) {
                    $allowed->where(function ($fresh) use ($type) {
                        $fresh->where('is_active', true);
                        if ($type) {
                            $fresh->where($type->categoryPermission(), true);
                        }
                    });
                    if ($preservesExisting) {
                        $allowed->orWhere('id', $existingCategoryId);
                    }
                });
            }),
        ];
    }

    public function resolve(int $apartmentId, string $dueType, ?int $categoryId): ?int
    {
        return $categoryId;
    }
}
