<?php

namespace App\Support;

use App\Models\AssessmentPlan;
use App\Models\Category;
use Illuminate\Validation\ValidationException;

class AssessmentWorkCategory
{
    public function proposal(AssessmentPlan $plan, array $input): array
    {
        if ($plan->exists) {
            $category = $plan->category;
        } elseif ($input['category_mode'] === 'existing') {
            $category = Category::where('apartment_id', $plan->apartment_id)
                ->where('type', Category::TYPE_ALL)->where('is_active', true)->find($input['category_id']);
            if (! $category) {
                throw ValidationException::withMessages(['category_id' => 'Kategori artık kullanılamıyor. Aktif, Tümü tipinde bir kategori seçin.']);
            }
        } else {
            $name = trim($input['category_name']);
            $category = Category::withTrashed()->where('apartment_id', $plan->apartment_id)->where('name', $name)->first();
            if (! $category) {
                $normalize = fn ($value) => mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], trim($value)), 'UTF-8');
                $category = Category::withTrashed()->where('apartment_id', $plan->apartment_id)->get()
                    ->first(fn ($candidate) => $normalize($candidate->name) === $normalize($name));
            }
            if ($category && ($category->trashed() || ! $category->is_active || $category->type !== Category::TYPE_ALL)) {
                throw ValidationException::withMessages(['category_name' => 'Bu adla farklı tipte, pasif veya silinmiş bir kategori var. Başka bir ad kullanın; mevcut kategori değiştirilmeyecek.']);
            }
        }

        return [
            'id' => $category?->id,
            'name' => $category?->name ?? ($plan->exists ? '—' : trim($input['category_name'])),
            'is_new' => ! $plan->exists && ! $category,
            'snapshot' => $category?->getAttributes(),
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
        ])->id;
    }
}
