@extends('layouts.app')

@section('title', 'Yeni Paket')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.packages.index') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Paketlere dön</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Yeni Paket</h1>
    </div>

    <div class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6">
        <form method="POST" action="{{ route('admin.packages.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700">Paket Adı</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                @error('name')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Slug</label>
                <input type="text" name="slug" value="{{ old('slug') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Boş bırakılırsa otomatik oluşturulur.</p>
                @error('slug')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Açıklama</label>
                <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">{{ old('description') }}</textarea>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Apartman Limiti</label>
                    <input type="number" name="apartment_limit" value="{{ old('apartment_limit', 1) }}" min="0" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Çoklu Apartman Limiti</label>
                    <input type="number" name="multi_apartment_limit" value="{{ old('multi_apartment_limit', 0) }}" min="0" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Çoklu apartman yönetimi özelliği aktifse max apartman sayısı</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Sıra</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Aylık Fiyat</label>
                    <input type="number" step="0.01" name="monthly_price" value="{{ old('monthly_price', 0) }}" min="0" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Yıllık Fiyat</label>
                    <input type="number" step="0.01" name="yearly_price" value="{{ old('yearly_price', 0) }}" min="0" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} id="is_active" class="rounded border-slate-300">
                    <label for="is_active" class="text-sm font-medium text-slate-700">Aktif</label>
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="show_on_website" value="0">
                    <input type="checkbox" name="show_on_website" value="1" {{ old('show_on_website', true) ? 'checked' : '' }} id="show_on_website" class="rounded border-slate-300">
                    <label for="show_on_website" class="text-sm font-medium text-slate-700">Webde göster</label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-3">Paket Özellikleri</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="features[]" value="Otomatik aidat planlama" {{ in_array('Otomatik aidat planlama', old('features', [])) ? 'checked' : '' }} class="rounded border-slate-300">
                        <span class="text-sm text-slate-700">Otomatik aidat planlama</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="features[]" value="Kullanıcı portalı erişimi" {{ in_array('Kullanıcı portalı erişimi', old('features', [])) ? 'checked' : '' }} class="rounded border-slate-300">
                        <span class="text-sm text-slate-700">Kullanıcı portalı erişimi</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="features[]" value="Hesap ekstresi ve raporlar" {{ in_array('Hesap ekstresi ve raporlar', old('features', [])) ? 'checked' : '' }} class="rounded border-slate-300">
                        <span class="text-sm text-slate-700">Hesap ekstresi ve raporlar</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="features[]" value="Çoklu apartman yönetimi" {{ in_array('Çoklu apartman yönetimi', old('features', [])) ? 'checked' : '' }} id="feature_multi_apartment" class="rounded border-slate-300">
                        <span class="text-sm text-slate-700">Çoklu apartman yönetimi</span>
                    </label>
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Kaydet</button>
            </div>
        </form>
    </div>

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
@endsection
