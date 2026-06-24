@extends('layouts.app')

@section('title', $manager->name)

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('admin.managers.index') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Aboneliklere dön</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $manager->name }}</h1>
            <p class="text-sm text-slate-500">{{ $manager->email }}</p>
        </div>
        <form method="POST" action="{{ route('admin.impersonate.start', $manager) }}">
            @csrf
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Bu Kullanıcı Olarak Giriş Yap</button>
        </form>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">Mevcut Abonelik</h2>
            @if ($manager->subscription)
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Paket</span><span class="font-medium text-slate-900">{{ $manager->subscription->package->name }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Dönem</span><span class="font-medium text-slate-900 capitalize">{{ $manager->subscription->period }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Fiyat</span><span class="font-medium text-slate-900">{{ number_format($manager->subscription->price, 2) }} ₺</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Başlangıç</span><span class="font-medium text-slate-900">{{ $manager->subscription->started_at->format('d.m.Y') }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Bitiş</span><span class="font-medium text-slate-900">{{ $manager->subscription->expires_at?->format('d.m.Y') ?? 'Süresiz' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Apartman Limiti</span><span class="font-medium text-slate-900">{{ $quota->currentCount($manager) }} / {{ $quota->maxFor($manager) ?? 'Sınırsız' }}</span></div>
                </div>
            @else
                <p class="mt-4 text-sm text-slate-500">Aktif abonelik yok.</p>
            @endif

            <form method="POST" action="{{ route('admin.managers.subscription.update', $manager) }}" class="mt-6 space-y-4 border-t border-slate-100 pt-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-sm font-medium text-slate-700">Paket</label>
                    <select name="package_id" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                        @foreach ($packages as $package)
                            <option value="{{ $package->id }}" {{ $manager->subscription?->package_id == $package->id ? 'selected' : '' }}>{{ $package->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Dönem</label>
                    <select name="period" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                        <option value="monthly" {{ $manager->subscription?->period === 'monthly' ? 'selected' : '' }}>Aylık</option>
                        <option value="yearly" {{ $manager->subscription?->period === 'yearly' ? 'selected' : '' }}>Yıllık</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Fiyat (opsiyonel)</label>
                    <input type="number" step="0.01" name="price" value="{{ old('price', $manager->subscription?->price) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-3">Özellik Override'ları (Boş bırakılırsa paket ayarları kullanılır)</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="feature_auto_dues" value="{{ old('feature_auto_dues', $manager->subscription?->feature_auto_dues ? '1' : '0') }}">
                            <input type="checkbox" name="feature_auto_dues" value="1" {{ old('feature_auto_dues', $manager->subscription?->feature_auto_dues) ? 'checked' : '' }} class="rounded border-slate-300">
                            <span class="text-sm text-slate-700">Otomatik aidat planlama</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="feature_user_portal" value="{{ old('feature_user_portal', $manager->subscription?->feature_user_portal ? '1' : '0') }}">
                            <input type="checkbox" name="feature_user_portal" value="1" {{ old('feature_user_portal', $manager->subscription?->feature_user_portal) ? 'checked' : '' }} class="rounded border-slate-300">
                            <span class="text-sm text-slate-700">Kullanıcı portalı erişimi</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="feature_reports" value="{{ old('feature_reports', $manager->subscription?->feature_reports ? '1' : '0') }}">
                            <input type="checkbox" name="feature_reports" value="1" {{ old('feature_reports', $manager->subscription?->feature_reports) ? 'checked' : '' }} class="rounded border-slate-300">
                            <span class="text-sm text-slate-700">Hesap ekstresi ve raporlar</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="feature_multi_apartment" value="{{ old('feature_multi_apartment', $manager->subscription?->feature_multi_apartment ? '1' : '0') }}">
                            <input type="checkbox" name="feature_multi_apartment" value="1" {{ old('feature_multi_apartment', $manager->subscription?->feature_multi_apartment) ? 'checked' : '' }} id="feature_multi_apartment" class="rounded border-slate-300">
                            <span class="text-sm text-slate-700">Çoklu apartman yönetimi</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Çoklu Apartman Limiti (opsiyonel)</label>
                    <input type="number" name="multi_apartment_limit_override" value="{{ old('multi_apartment_limit_override', $manager->subscription?->multi_apartment_limit_override) }}" min="0" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Boş bırakılırsa paket limiti kullanılır</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Apartman Limiti Override (opsiyonel)</label>
                    <input type="number" name="max_apartments" value="{{ old('max_apartments', $manager->quotaOverride?->max_apartments ?? $quota->maxFor($manager)) }}" min="0" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Boş bırakılırsa paket limiti kullanılır</p>
                </div>

                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Aboneliği Güncelle</button>
            </form>
        </div>

        <script>
            const packageFeatures = {!! json_encode($packageFeatures ?? []) !!};

            document.addEventListener('DOMContentLoaded', function() {
                const packageSelect = document.querySelector('select[name="package_id"]');
                const periodSelect = document.querySelector('select[name="period"]');
                const priceInput = document.querySelector('input[name="price"]');
                const featureAutoDues = document.querySelector('input[name="feature_auto_dues"][type="checkbox"]');
                const featureUserPortal = document.querySelector('input[name="feature_user_portal"][type="checkbox"]');
                const featureReports = document.querySelector('input[name="feature_reports"][type="checkbox"]');
                const featureMultiApartment = document.querySelector('input[name="feature_multi_apartment"][type="checkbox"]');
                const multiApartmentLimitOverride = document.querySelector('input[name="multi_apartment_limit_override"]');
                const maxApartmentsOverride = document.querySelector('input[name="max_apartments"]');

                function updatePackageFeatures() {
                    const packageId = parseInt(packageSelect.value);
                    const features = packageFeatures[packageId];

                    if (features) {
                        // Update checkboxes
                        featureAutoDues.checked = features.feature_auto_dues;
                        featureUserPortal.checked = features.feature_user_portal;
                        featureReports.checked = features.feature_reports;
                        featureMultiApartment.checked = features.feature_multi_apartment;

                        // Update hidden inputs to match checkbox values
                        document.querySelector('input[name="feature_auto_dues"][type="hidden"]').value = features.feature_auto_dues ? '1' : '0';
                        document.querySelector('input[name="feature_user_portal"][type="hidden"]').value = features.feature_user_portal ? '1' : '0';
                        document.querySelector('input[name="feature_reports"][type="hidden"]').value = features.feature_reports ? '1' : '0';
                        document.querySelector('input[name="feature_multi_apartment"][type="hidden"]').value = features.feature_multi_apartment ? '1' : '0';

                        // Update apartment limit override
                        maxApartmentsOverride.value = features.apartment_limit;

                        // Update price based on period
                        updatePrice(features);

                        if (features.feature_multi_apartment) {
                            multiApartmentLimitOverride.value = features.multi_apartment_limit;
                            multiApartmentLimitOverride.disabled = false;
                            multiApartmentLimitOverride.closest('div').style.opacity = '1';
                        } else {
                            multiApartmentLimitOverride.value = '';
                            multiApartmentLimitOverride.disabled = true;
                            multiApartmentLimitOverride.closest('div').style.opacity = '0.5';
                        }
                    }
                }

                function updatePrice(features) {
                    const period = periodSelect.value;
                    if (period === 'yearly') {
                        priceInput.value = features.yearly_price;
                    } else {
                        priceInput.value = features.monthly_price;
                    }
                }

                if (packageSelect) {
                    packageSelect.addEventListener('change', updatePackageFeatures);
                    // Set initial state based on current checkbox
                    if (!featureMultiApartment.checked) {
                        multiApartmentLimitOverride.disabled = true;
                        multiApartmentLimitOverride.closest('div').style.opacity = '0.5';
                    }
                }

                // Update price when period changes
                if (periodSelect) {
                    periodSelect.addEventListener('change', function() {
                        const packageId = parseInt(packageSelect.value);
                        const features = packageFeatures[packageId];
                        if (features) {
                            updatePrice(features);
                        }
                    });
                }

                // Toggle multi apartment limit field when checkbox is manually changed
                featureMultiApartment.addEventListener('change', function() {
                    if (this.checked) {
                        multiApartmentLimitOverride.disabled = false;
                        multiApartmentLimitOverride.closest('div').style.opacity = '1';
                    } else {
                        multiApartmentLimitOverride.disabled = true;
                        multiApartmentLimitOverride.closest('div').style.opacity = '0.5';
                    }
                });
            });
        </script>
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Apartmanlar</h2>
        @if ($apartments->isEmpty())
            <p class="mt-4 text-sm text-slate-500">Henüz apartman yok.</p>
        @else
            <ul class="mt-4 divide-y divide-slate-100">
                @foreach ($apartments as $apartment)
                    <li class="py-3 flex items-center justify-between">
                        <div>
                            <div class="font-medium text-slate-900">{{ $apartment->name }}</div>
                            <div class="text-xs text-slate-500">Rol: {{ $apartment->pivot->role }} | {{ $apartment->pivot->is_active ? 'Aktif' : 'Pasif' }}</div>
                        </div>
                        <a href="{{ route('apartments.show', $apartment) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Görüntüle</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
