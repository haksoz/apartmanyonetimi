<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('categories.index', compact('apartment', 'categories'));
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
            'type' => $validated['type'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('categories.index')->with('status', 'Kategori oluşturuldu.');
    }

    public function edit(string $id, CurrentApartment $currentApartment)
    {
        $category = $this->findCategory($id, $currentApartment);

        if ($category instanceof \Illuminate\Http\RedirectResponse) {
            return $category;
        }

        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, string $id, CurrentApartment $currentApartment)
    {
        $category = $this->findCategory($id, $currentApartment);

        if ($category instanceof \Illuminate\Http\RedirectResponse) {
            return $category;
        }

        if ($category->is_system) {
            return redirect()->route('categories.index')->with('error', 'Sistem kategorileri düzenlenemez.');
        }

        $validated = $this->validateCategory($request, $category->apartment_id, $category->id);

        $category->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('categories.index')->with('status', 'Kategori güncellendi.');
    }

    public function destroy(string $id, CurrentApartment $currentApartment)
    {
        $category = $this->findCategory($id, $currentApartment);

        if ($category instanceof \Illuminate\Http\RedirectResponse) {
            return $category;
        }

        if ($category->is_system) {
            return redirect()->route('categories.index')->with('error', 'Bu kategori sistem kategorisi olduğu için silinemez.');
        }

        $category->delete();

        return redirect()->route('categories.index')->with('status', 'Kategori silindi.');
    }

    private function resolveApartment(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
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
            'type' => ['required', Rule::in([Category::TYPE_ALL, Category::TYPE_INCOME, Category::TYPE_EXPENSE])],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
