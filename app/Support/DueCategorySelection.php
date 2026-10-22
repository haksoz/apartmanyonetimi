<?php

namespace App\Support;

use App\Enums\DueType;
use App\Models\Category;
use Illuminate\Validation\Rule;

class DueCategorySelection
{
    public function options(int $apartmentId)
    {
        return Category::query()->where('apartment_id', $apartmentId)
            ->whereIn('type', [Category::TYPE_INCOME, Category::TYPE_ALL])
            ->where('name', '!=', 'Aidat')->where('is_active', true)
            ->orderedForDisplay()->get();
    }

    public function rules(mixed $dueType, int $apartmentId): array
    {
        $selectsCategory = is_string($dueType) && (DueType::tryFrom($dueType)?->selectsCategory() ?? false);

        return [
            Rule::requiredIf($selectsCategory), 'nullable', 'integer',
            Rule::exists('categories', 'id')->where(function ($query) use ($apartmentId, $selectsCategory) {
                $query->where('apartment_id', $apartmentId)->where('is_active', true);
                if ($selectsCategory) {
                    $query->whereIn('type', [Category::TYPE_INCOME, Category::TYPE_ALL])->where('name', '!=', 'Aidat');
                }
            }),
        ];
    }

    public function resolve(int $apartmentId, string $dueType, ?int $categoryId): ?int
    {
        $name = DueType::tryFrom($dueType)?->lockedCategoryName();
        if (! $name) {
            return $categoryId;
        }
        $category = Category::withTrashed()->firstOrCreate(
            ['apartment_id' => $apartmentId, 'name' => $name],
            ['type' => Category::TYPE_INCOME, 'is_active' => true, 'is_system' => true],
        );
        if ($category->trashed()) {
            $category->restore();
        }
        if ($category->type !== Category::TYPE_INCOME || ! $category->is_system || ! $category->is_active) {
            $category->update(['type' => Category::TYPE_INCOME, 'is_active' => true, 'is_system' => true]);
        }

        return $category->id;
    }
}
