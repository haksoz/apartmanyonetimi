@extends('layouts.app')

@section('title', $package->name)

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.packages.index') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Paketlere dön</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $package->name }}</h1>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <form method="POST" action="{{ route('admin.packages.update', $package) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-slate-700">Paket Adı</label>
                    <input type="text" name="name" value="{{ old('name', $package->name) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                    @error('name')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $package->slug) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                    @error('slug')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Açıklama</label>
                    <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">{{ old('description', $package->description) }}</textarea>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Apartman Limiti</label>
                        <input type="number" name="apartment_limit" value="{{ old('apartment_limit', $package->apartment_limit) }}" min="0" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Çoklu Apartman Limiti</label>
                        <input type="number" name="multi_apartment_limit" value="{{ old('multi_apartment_limit', $package->multi_apartment_limit) }}" min="0" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                        <p class="mt-1 text-xs text-slate-500">Çoklu apartman yönetimi özelliği aktifse max apartman sayısı</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Sıra</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $package->sort_order) }}" min="0" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Aylık Fiyat</label>
                        <input type="number" step="0.01" name="monthly_price" value="{{ old('monthly_price', $package->monthly_price) }}" min="0" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Yıllık Fiyat</label>
                        <input type="number" step="0.01" name="yearly_price" value="{{ old('yearly_price', $package->yearly_price) }}" min="0" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $package->is_active) ? 'checked' : '' }} id="is_active" class="rounded border-slate-300">
                    <label for="is_active" class="text-sm font-medium text-slate-700">Aktif</label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-3">Paket Özellikleri</label>
                    <div class="space-y-2">
                        @php
                            $enabledFeatures = $package->features->where('is_enabled', true)->pluck('feature_key')->toArray();
                        @endphp
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="features[]" value="Otomatik aidat planlama" {{ in_array('Otomatik aidat planlama', old('features', $enabledFeatures)) ? 'checked' : '' }} class="rounded border-slate-300">
                            <span class="text-sm text-slate-700">Otomatik aidat planlama</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="features[]" value="Kullanıcı portalı erişimi" {{ in_array('Kullanıcı portalı erişimi', old('features', $enabledFeatures)) ? 'checked' : '' }} class="rounded border-slate-300">
                            <span class="text-sm text-slate-700">Kullanıcı portalı erişimi</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="features[]" value="Hesap ekstresi ve raporlar" {{ in_array('Hesap ekstresi ve raporlar', old('features', $enabledFeatures)) ? 'checked' : '' }} class="rounded border-slate-300">
                            <span class="text-sm text-slate-700">Hesap ekstresi ve raporlar</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="features[]" value="Çoklu apartman yönetimi" {{ in_array('Çoklu apartman yönetimi', old('features', $enabledFeatures)) ? 'checked' : '' }} id="feature_multi_apartment" class="rounded border-slate-300">
                            <span class="text-sm text-slate-700">Çoklu apartman yönetimi</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4">
                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Güncelle</button>

                    <button type="button" onclick="document.getElementById('delete-form').submit()" class="rounded-xl bg-red-50 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-100">Sil</button>
                </div>
            </form>
        </div>

        <form id="delete-form" method="POST" action="{{ route('admin.packages.destroy', $package) }}" onsubmit="return confirm('Paketi silmek istediğine emin misin?')">
            @csrf
            @method('DELETE')
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const multiApartmentCheckbox = document.getElementById('feature_multi_apartment');
                const multiApartmentLimitInput = document.querySelector('input[name="multi_apartment_limit"]');

                function toggleMultiApartmentLimit() {
                    if (multiApartmentCheckbox && multiApartmentLimitInput) {
                        const container = multiApartmentLimitInput.closest('div');
                        if (multiApartmentCheckbox.checked) {
                            container.style.opacity = '1';
                            multiApartmentLimitInput.disabled = false;
                        } else {
                            container.style.opacity = '0.5';
                            multiApartmentLimitInput.disabled = true;
                        }
                    }
                }

                if (multiApartmentCheckbox) {
                    multiApartmentCheckbox.addEventListener('change', toggleMultiApartmentLimit);
                    toggleMultiApartmentLimit();
                }
            });
        </script>

        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">Özellik Bayrakları</h2>
            <p class="text-sm text-slate-500">Her satır bir feature_key temsil eder.</p>

            <form method="POST" action="{{ route('admin.packages.features.update', $package) }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label class="block text-sm font-medium text-slate-700">Özellikler (her satır bir key)</label>
                    <textarea name="features" rows="8" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm font-mono">{{ $package->features->map(fn($f) => $f->feature_key.($f->is_enabled ? '' : ' (disabled)'))->join("\n") }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Aktif tutmak istemediğin özelliğin satırına <code>(disabled)</code> ekle.</p>
                </div>

                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Özellikleri Kaydet</button>
            </form>
        </div>
    </div>
@endsection
