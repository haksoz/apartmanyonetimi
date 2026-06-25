<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPackageController extends Controller
{
    public function index()
    {
        $packages = Package::orderBy('sort_order')->paginate(20);

        return view('admin.packages.index', compact('packages'));
    }

    public function create()
    {
        return view('admin.packages.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:packages,slug'],
            'description' => ['nullable', 'string'],
            'apartment_limit' => ['required', 'integer', 'min:0'],
            'multi_apartment_limit' => ['nullable', 'integer', 'min:0'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'yearly_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'show_on_website' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
        ]);

        $validated['slug'] = $validated['slug'] ? \Illuminate\Support\Str::slug($validated['slug']) : \Illuminate\Support\Str::slug($validated['name']);
        $validated['is_active'] = $request->input('is_active') == '1';
        $validated['show_on_website'] = $request->input('show_on_website') == '1';
        $validated['multi_apartment_limit'] = $validated['multi_apartment_limit'] ?? 0;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $features = $validated['features'] ?? [];
        unset($validated['features']);

        $package = Package::create($validated);

        // Sync features
        $allFeatures = [
            'Otomatik aidat planlama',
            'Kullanıcı portalı erişimi',
            'Hesap ekstresi ve raporlar',
            'Çoklu apartman yönetimi',
        ];

        foreach ($allFeatures as $feature) {
            $isEnabled = in_array($feature, $features);
            $package->features()->updateOrCreate(
                ['feature_key' => $feature],
                ['is_enabled' => $isEnabled]
            );
        }

        return redirect()->route('admin.packages.index')->with('status', 'Paket oluşturuldu.');
    }

    public function edit(Package $package)
    {
        $package->load('features');

        return view('admin.packages.edit', compact('package'));
    }

    public function update(Request $request, Package $package)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('packages')->ignore($package->id)],
            'description' => ['nullable', 'string'],
            'apartment_limit' => ['required', 'integer', 'min:0'],
            'multi_apartment_limit' => ['nullable', 'integer', 'min:0'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'yearly_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'show_on_website' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
        ]);

        $validated['slug'] = \Illuminate\Support\Str::slug($validated['slug']);
        $validated['is_active'] = $request->input('is_active') == '1';
        $validated['show_on_website'] = $request->input('show_on_website') == '1';
        $validated['multi_apartment_limit'] = $validated['multi_apartment_limit'] ?? 0;
        $validated['sort_order'] = $validated['sort_order'] ?? $package->sort_order;

        $features = $validated['features'] ?? [];
        unset($validated['features']);

        $package->update($validated);

        // Sync features
        $allFeatures = [
            'Otomatik aidat planlama',
            'Kullanıcı portalı erişimi',
            'Hesap ekstresi ve raporlar',
            'Çoklu apartman yönetimi',
        ];

        foreach ($allFeatures as $feature) {
            $isEnabled = in_array($feature, $features);
            $package->features()->updateOrCreate(
                ['feature_key' => $feature],
                ['is_enabled' => $isEnabled]
            );
        }

        return redirect()->route('admin.packages.index')->with('status', 'Paket güncellendi.');
    }

    public function destroy(Package $package)
    {
        if ($package->subscriptions()->exists()) {
            return back()->with('error', 'Bu pakete bağlı aktif abonelikler olduğu için silinemez.');
        }

        $package->delete();

        return redirect()->route('admin.packages.index')->with('status', 'Paket silindi.');
    }

    public function updateFeatures(Request $request, Package $package)
    {
        $lines = array_filter(array_map('trim', explode("\n", (string) $request->input('features', ''))));

        $existing = $package->features->keyBy('feature_key');
        $newKeys = [];

        foreach ($lines as $line) {
            $disabled = str_ends_with($line, '(disabled)');
            $key = trim($disabled ? str_replace('(disabled)', '', $line) : $line);

            if ($key === '') {
                continue;
            }

            $newKeys[] = $key;

            if ($existing->has($key)) {
                $existing->get($key)->update(['is_enabled' => ! $disabled]);
            } else {
                $package->features()->create([
                    'feature_key' => $key,
                    'is_enabled' => ! $disabled,
                ]);
            }
        }

        $package->features()
            ->whereNotIn('feature_key', $newKeys)
            ->delete();

        return redirect()->route('admin.packages.edit', $package)->with('status', 'Özellikler güncellendi.');
    }
}
