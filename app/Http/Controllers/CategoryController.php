<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Category;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\DuePlan;
use App\Models\ExpensePlan;
use App\Models\AssessmentPlan;
use App\Models\Apartment;
use App\Models\Expense;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        $categories = Category::query()
            ->where('apartment_id', $apartment->id)
            ->orderedForDisplay()
            ->get();

        return view('categories.index', compact('apartment', 'categories'));
    }

    public function reorder(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        $validated = $request->validate([
            'category_ids' => ['required', 'array', 'list', 'min:1'],
            'category_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $ids = array_map('intval', $validated['category_ids']);
        DB::transaction(function () use ($apartment, $ids) {
            $existing = Category::where('apartment_id', $apartment->id)->orderBy('id')->lockForUpdate()->pluck('id')->all();
            $submitted = $ids;
            sort($submitted);
            if ($existing !== $submitted) {
                throw ValidationException::withMessages([
                    'category_ids' => 'Kategori listesi değişmiş veya geçersiz kategori seçilmiş. Sayfayı yenileyip tekrar deneyin.',
                ]);
            }
            foreach ($ids as $position => $id) {
                Category::where('apartment_id', $apartment->id)->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return redirect()->route('categories.index')->with('status', 'Kategori sıralaması kaydedildi.');
    }

    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        return view('categories.create', compact('apartment'));
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        $validated = $this->validateCategory($request, $apartment->id);

        Category::create([
            'apartment_id' => $apartment->id,
            'name' => $validated['name'],
            'type' => Category::TYPE_ALL,
            'is_active' => $request->boolean('is_active', true),
            ...$this->permissions($request),
        ]);

        return redirect()->route('categories.index')->with('status', 'Kategori oluşturuldu.');
    }

    public function edit(string $id, CurrentApartment $currentApartment)
    {
        $category = $this->findCategory($id, $currentApartment);

        if ($category instanceof \Illuminate\Http\RedirectResponse) {
            return $category;
        }

        $isDefaultAidatCategory = $category->isDefaultAidatCategory();

        return view('categories.edit', compact('category', 'isDefaultAidatCategory'));
    }

    public function update(Request $request, string $id, CurrentApartment $currentApartment)
    {
        $category = $this->findCategory($id, $currentApartment);

        if ($category instanceof \Illuminate\Http\RedirectResponse) {
            return $category;
        }

        if ($category->isDefaultAidatCategory()) {
            return redirect()->route('categories.index')->with('error', 'Genel Aidat kategorisinin adı, aktifliği ve kullanım yetkileri değiştirilemez.');
        }

        $validated = $this->validateCategory($request, $category->apartment_id, $category->id);

        $category->update([
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active'),
            ...$this->permissions($request),
        ]);

        return redirect()->route('categories.index')->with('status', 'Kategori güncellendi.');
    }

    public function destroy(string $id, CurrentApartment $currentApartment)
    {
        $category = $this->findCategory($id, $currentApartment);

        if ($category instanceof \Illuminate\Http\RedirectResponse) {
            return $category;
        }

        if ($category->isDefaultAidatCategory()) {
            return redirect()->route('categories.index')->with('error', 'Genel Aidat kategorisi silinemez.');
        }

        if ($reason = $this->relatedRecordReason($category)) {
            return redirect()->route('categories.index')->with('error', $reason);
        }

        $category->delete();

        return redirect()->route('categories.index')->with('status', 'Kategori silindi.');
    }

    private function resolveApartment(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('subscriber.dashboard');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        return $apartment;
    }

    private function findCategory(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        return Category::query()
            ->where('apartment_id', $apartment->id)
            ->findOrFail($id);
    }

    private function relatedRecordReason(Category $category): ?string
    {
        $links = [
            'gider' => Expense::where('category_id', $category->id)->exists(),
            'kasa hareketi' => CashTransaction::where('category_id', $category->id)->exists(),
            'borç' => Due::where('category_id', $category->id)->exists(),
            'borçlandırma' => DueBatch::where('category_id', $category->id)->exists(),
            'aidat planı' => DuePlan::where('category_id', $category->id)->exists(),
            'gider planı' => ExpensePlan::where('category_id', $category->id)->exists(),
            'ek borçlandırma planı' => AssessmentPlan::where('category_id', $category->id)->exists(),
            'varsayılan aidat kategorisi' => Apartment::where('default_aidat_category_id', $category->id)->exists(),
            'hesap' => Account::where('default_category_id', $category->id)->exists(),
        ];

        $used = array_keys(array_filter($links));
        if ($used === []) {
            return null;
        }

        return 'Bu kategori '.implode(', ', $used).' kayıtlarında kullanıldığı için silinemez.';
    }

    private function validateCategory(Request $request, int $apartmentId, ?int $categoryId = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')
                    ->where('apartment_id', $apartmentId)
                    ->ignore($categoryId),
            ],
            'is_active' => ['nullable', 'boolean'],
            ...collect(array_keys(Category::USAGE_PERMISSIONS))->mapWithKeys(fn ($key) => [$key => ['nullable', 'boolean']])->all(),
        ]);
    }

    private function permissions(Request $request): array
    {
        return collect(array_keys(Category::USAGE_PERMISSIONS))
            ->mapWithKeys(fn ($key) => [$key => $request->boolean($key)])
            ->all();
    }
}
