<?php

namespace App\Support;

use App\Models\AssessmentPlan;
use App\Models\Category;
use Illuminate\Validation\ValidationException;

class AssessmentWorkCategory
{
    public function proposal(AssessmentPlan $plan, array $input): array
    {
        $dueType = $input['due_type'] ?? ($plan->due_type instanceof \App\Enums\DueType ? $plan->due_type->value : $plan->due_type);
        if ($plan->exists) {
            $category = $plan->category;
        } elseif ($input['category_mode'] === 'existing') {
            $category = Category::where('apartment_id', $plan->apartment_id)
                ->where('allow_'.$dueType, true)->where('is_active', true)->find($input['category_id']);
            if (! $category) {
                throw ValidationException::withMessages(['category_id' => 'Kategori bu borç türünde kullanılamıyor.']);
            }
        } else {
            $name = trim($input['category_name']);
            $category = Category::withTrashed()->where('apartment_id', $plan->apartment_id)->where('name', $name)->first();
            if (! $category) {
                $normalize = fn ($value) => mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], trim($value)), 'UTF-8');
                $category = Category::withTrashed()->where('apartment_id', $plan->apartment_id)->get()
                    ->first(fn ($candidate) => $normalize($candidate->name) === $normalize($name));
            }
            if ($category) {
                throw ValidationException::withMessages([
                    'category_name' => 'Bu adla bir kategori zaten var. Yeni kategori için farklı bir ad yazın veya Mevcut kategoriyi kullan seçeneğini seçin.',
                ]);
            }
        }

        return [
            'id' => $category?->id,
            'name' => $category?->name ?? ($plan->exists ? '—' : trim($input['category_name'])),
            'is_new' => ! $plan->exists && ! $category,
            'snapshot' => $category?->getAttributes(),
            'due_type' => $dueType,
        ];
    }

    public function resolve(int $apartmentId, array $proposal): ?int
    {
        if (! $proposal['is_new']) {
            return $proposal['id'];
        }

        return Category::create([
            'apartment_id' => $apartmentId, 'name' => $proposal['name'],
            'type' => Category::TYPE_ALL, 'is_active' => true, 'is_system' => false,
            ...array_fill_keys(array_keys(Category::USAGE_PERMISSIONS), false),
            'allow_'.$proposal['due_type'] => true,
            'allow_expense' => true,
        ])->id;
    }
}
