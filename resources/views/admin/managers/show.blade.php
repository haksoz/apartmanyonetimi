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
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Mevcut Abonelik</h2>
                @if ($manager->subscription && $manager->subscription->is_trial && !$manager->subscription->isExpired())
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Deneme Süreci</span>
                @elseif ($manager->subscription && $manager->subscription->isExpired())
                    <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Süresi Dolmuş</span>
                @elseif ($manager->subscription)
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Aktif</span>
                @endif
            </div>
            @if ($manager->subscription)
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Paket</span><span class="font-medium text-slate-900">{{ $manager->subscription->package->name }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Dönem</span><span class="font-medium text-slate-900 capitalize">{{ $manager->subscription->period }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Fiyat</span><span class="font-medium text-slate-900">{{ number_format($manager->subscription->price, 2) }} ₺</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Ödenen Toplam</span><span class="font-medium text-slate-900">{{ number_format($manager->subscription->totalPaid(), 2) }} ₺</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Başlangıç</span><span class="font-medium text-slate-900">{{ $manager->subscription->started_at->format('d.m.Y') }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Bitiş</span><span class="font-medium text-slate-900">{{ $manager->subscription->expires_at?->format('d.m.Y') ?? 'Süresiz' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Apartman Limiti</span><span class="font-medium text-slate-900">{{ $quota->currentCount($manager) }} / {{ $quota->maxFor($manager) ?? 'Sınırsız' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Abonelik Türü</span><span class="font-medium text-slate-900">{{ $manager->subscription->is_trial ? 'Deneme' : 'Ücretli' }}</span></div>
                </div>
                @if ($manager->subscription->is_trial && $manager->subscription->expires_at && !$manager->subscription->isExpired())
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        Deneme süreci <strong>{{ $manager->subscription->expires_at->format('d.m.Y') }}</strong> tarihinde sona eriyor
                        ({{ $manager->subscription->expires_at->diffForHumans() }}).
                    </div>
                @elseif ($manager->subscription->is_trial && $manager->subscription->isExpired())
                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        Deneme süreci <strong>{{ $manager->subscription->expires_at->format('d.m.Y') }}</strong> tarihinde sona erdi.
                    </div>
                @endif

                @if (! $manager->subscription->is_trial)
                    <form method="POST" action="{{ route('admin.managers.subscription.cancel', $manager) }}" class="mt-4" onsubmit="return confirm('Abonelik iptal edilecek. Emin misiniz?')">
                        @csrf
                        <div class="flex gap-2">
                            <input type="text" name="cancellation_notes" placeholder="İptal nedeni (opsiyonel)" class="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                            <button type="submit" class="rounded-lg bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-600 hover:bg-red-100">İptal Et</button>
                        </div>
                    </form>
                @endif
            @else
                <p class="mt-4 text-sm text-slate-500">Aktif abonelik yok.</p>
            @endif

            @if ($manager->subscription && $manager->subscription->is_trial)
                <div class="mt-5 border-t border-slate-100 pt-5">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">Deneme Süresini Uzat</h3>

                    @if (session('status'))
                        <div class="mb-3 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
                    @endif
                    @error('trial')
                        <div class="mb-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">{{ $message }}</div>
                    @enderror

                    {{-- Hızlı butonlar --}}
                    <div class="flex gap-2 flex-wrap">
                        @foreach ([30, 60, 90] as $days)
                            <form method="POST" action="{{ route('admin.managers.trial.extend', $manager) }}">
                                @csrf
                                <input type="hidden" name="days" value="{{ $days }}">
                                <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                    +{{ $days }} gün
                                </button>
                            </form>
                        @endforeach
                    </div>

                    {{-- Tarih seçici --}}
                    <form method="POST" action="{{ route('admin.managers.trial.extend', $manager) }}" class="mt-3 flex gap-2 items-end">
                        @csrf
                        <div class="flex-1">
                            <label class="block text-xs text-slate-500 mb-1">Bu tarihe kadar uzat</label>
                            <input type="date" name="expires_at"
                                min="{{ now()->addDay()->format('Y-m-d') }}"
                                value="{{ $manager->subscription->expires_at?->format('Y-m-d') }}"
                                class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                        </div>
                        <button type="submit" class="rounded-lg bg-amber-500 px-4 py-1.5 text-sm font-semibold text-white hover:bg-amber-600">
                            Uygula
                        </button>
                    </form>
                </div>
            @endif

            @if ($manager->subscription)
                <form method="POST" action="{{ route('admin.managers.subscription.update', $manager) }}" class="mt-6 space-y-4 border-t border-slate-100 pt-4">
                    @csrf
                    @method('PATCH')
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">Mevcut Aboneliği Güncelle</h3>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Özellikler</label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="feature_auto_dues" value="1" {{ old('feature_auto_dues', $manager->subscription->feature_auto_dues) ? 'checked' : '' }} class="rounded border-slate-300">
                                <span class="text-sm text-slate-700">Otomatik aidat planlama</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="feature_user_portal" value="1" {{ old('feature_user_portal', $manager->subscription->feature_user_portal) ? 'checked' : '' }} class="rounded border-slate-300">
                                <span class="text-sm text-slate-700">Kullanıcı portalı erişimi</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="feature_reports" value="1" {{ old('feature_reports', $manager->subscription->feature_reports) ? 'checked' : '' }} class="rounded border-slate-300">
                                <span class="text-sm text-slate-700">Hesap ekstresi ve raporlar</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="feature_multi_apartment" value="1" {{ old('feature_multi_apartment', $manager->subscription->feature_multi_apartment) ? 'checked' : '' }} class="rounded border-slate-300">
                                <span class="text-sm text-slate-700">Çoklu apartman yönetimi</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Çoklu Apartman Limiti (opsiyonel)</label>
                        <input type="number" name="multi_apartment_limit_override" value="{{ old('multi_apartment_limit_override', $manager->subscription->multi_apartment_limit_override) }}" min="0" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                        <p class="mt-1 text-xs text-slate-500">Boş bırakılırsa paket limiti kullanılır</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Apartman Limiti Override (opsiyonel)</label>
                        <input type="number" name="max_apartments" value="{{ old('max_apartments', $manager->quotaOverride?->max_apartments ?? $quota->maxFor($manager)) }}" min="0" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                        <p class="mt-1 text-xs text-slate-500">Boş bırakılırsa paket limiti kullanılır</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Notlar</label>
                        <textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">{{ old('notes', $manager->subscription->notes) }}</textarea>
                    </div>

                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Güncelle</button>
                </form>
            @endif
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">Yeni Paket Tanımla / Sipariş Düş</h2>

            <form method="POST" action="{{ route('admin.managers.subscription.order', $manager) }}" class="mt-4 space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-700">Paket</label>
                    <select name="order[package_id]" id="order_package_id" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                        @foreach ($packages as $package)
                            <option value="{{ $package->id }}">{{ $package->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Dönem</label>
                    <select name="order[period]" id="order_period" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                        <option value="monthly" selected>Aylık</option>
                        <option value="yearly">Yıllık</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Fiyat</label>
                    <input type="number" step="0.01" name="order[price]" id="order_price" value="" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Varsayılan paket fiyatı otomatik gelir; elle düzenlenebilir.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Paket Özellikleri</label>
                    <div id="order_package_info" class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                        Paket seçildiğinde özellikler burada görüntülenecek.
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_paid" value="0">
                    <input type="checkbox" name="is_paid" value="1" id="is_paid" class="rounded border-slate-300">
                    <label for="is_paid" class="text-sm font-medium text-slate-700">Ödeme Alındı</label>
                </div>

                <div id="payment_fields" class="hidden space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-500">Ödeme Tarihi</label>
                            <input type="date" name="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500">Ödeme Yöntemi</label>
                            <select name="payment_method" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                                <option value="havale">Havale/EFT</option>
                                <option value="kredi_karti">Kredi Kartı</option>
                                <option value="nakit">Nakit</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-500">Referans Kodu</label>
                            <input type="text" name="reference_code" value="{{ old('reference_code') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Notlar</label>
                    <textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Sipariş Oluştur</button>
            </form>
        </div>

        <script>
            const packageFeatures = {!! json_encode($packageFeatures ?? []) !!};

            document.addEventListener('DOMContentLoaded', function() {
                const packageSelect = document.querySelector('select[name="order[package_id]"]');
                const periodSelect = document.querySelector('select[name="order[period]"]');
                const priceInput = document.querySelector('input[name="order[price]"]');
                const infoBox = document.getElementById('order_package_info');
                const isPaidCheckbox = document.getElementById('is_paid');
                const paymentFields = document.getElementById('payment_fields');

                function formatPrice(value) {
                    return Number(value || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function renderInfo(features) {
                    if (! features || ! infoBox) {
                        return;
                    }

                    const period = periodSelect ? periodSelect.value : 'monthly';
                    const basePrice = period === 'yearly' ? features.yearly_price : features.monthly_price;

                    infoBox.innerHTML = `
                        <div class="grid gap-2">
                            <div><span class="font-medium">Apartman limiti:</span> ${features.apartment_limit === 0 ? 'Sınırsız' : features.apartment_limit}</div>
                            <div><span class="font-medium">Çoklu apartman:</span> ${features.feature_multi_apartment ? 'Evet' : 'Hayır'}</div>
                            ${features.feature_multi_apartment ? `<div><span class="font-medium">Çoklu apartman limiti:</span> ${features.multi_apartment_limit === 0 ? 'Sınırsız' : features.multi_apartment_limit}</div>` : ''}
                            <div><span class="font-medium">Otomatik aidat planlama:</span> ${features.feature_auto_dues ? 'Evet' : 'Hayır'}</div>
                            <div><span class="font-medium">Kullanıcı portalı:</span> ${features.feature_user_portal ? 'Evet' : 'Hayır'}</div>
                            <div><span class="font-medium">Raporlar:</span> ${features.feature_reports ? 'Evet' : 'Hayır'}</div>
                            <div class="pt-2 border-t border-slate-200"><span class="font-medium">Varsayılan fiyat (${period === 'yearly' ? 'Yıllık' : 'Aylık'}):</span> ${formatPrice(basePrice)} ₺</div>
                        </div>
                    `;
                }

                function updatePrice(features) {
                    if (! priceInput || ! periodSelect) {
                        return;
                    }

                    const period = periodSelect.value;
                    const price = period === 'yearly' ? features.yearly_price : features.monthly_price;
                    priceInput.value = price;
                }

                function updatePackage() {
                    if (! packageSelect) {
                        return;
                    }

                    const packageId = parseInt(packageSelect.value);
                    const features = packageFeatures[packageId];

                    if (features) {
                        updatePrice(features);
                        renderInfo(features);
                    }
                }

                if (packageSelect) {
                    packageSelect.addEventListener('change', updatePackage);
                    updatePackage();
                }

                if (periodSelect) {
                    periodSelect.addEventListener('change', function() {
                        const packageId = parseInt(packageSelect.value);
                        const features = packageFeatures[packageId];
                        if (features) {
                            updatePrice(features);
                            renderInfo(features);
                        }
                    });
                }

                if (isPaidCheckbox && paymentFields) {
                    isPaidCheckbox.addEventListener('change', function() {
                        paymentFields.classList.toggle('hidden', ! this.checked);
                    });
                }

                document.querySelectorAll('.open-approve-modal').forEach(button => {
                    button.addEventListener('click', function() {
                        const modal = document.getElementById(this.dataset.modalTarget);
                        if (modal) {
                            modal.classList.remove('hidden');
                            modal.classList.add('flex');
                        }
                    });
                });

                document.querySelectorAll('.close-approve-modal').forEach(button => {
                    button.addEventListener('click', function() {
                        const modal = this.closest('.approve-modal');
                        if (modal) {
                            modal.classList.add('hidden');
                            modal.classList.remove('flex');
                        }
                    });
                });

                document.querySelectorAll('.approve-modal').forEach(modal => {
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            modal.classList.add('hidden');
                            modal.classList.remove('flex');
                        }
                    });
                });

                document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
                    radio.addEventListener('change', function() {
                        const modalId = this.dataset.modal;
                        const refInput = document.getElementById('ref-code-' + modalId);
                        const refLabel = modalId ? document.querySelector('#approve-modal-' + modalId + ' .ref-label') : null;
                        if (! refInput || ! refLabel) {
                            return;
                        }

                        if (this.value === 'nakit') {
                            refLabel.textContent = 'Tahsilat Numarası';
                            refInput.value = 'NKT-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '-' + Math.random().toString(36).substring(2, 6).toUpperCase();
                            refInput.readOnly = true;
                        } else {
                            refLabel.textContent = 'Dekont / Referans Numarası';
                            refInput.value = '';
                            refInput.readOnly = false;
                        }
                    });
                });

            document.querySelectorAll('.open-reject-modal').forEach(button => {
                button.addEventListener('click', function() {
                    const modal = document.getElementById(this.dataset.modalTarget);
                    if (modal) {
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                    }
                });
            });

            document.querySelectorAll('.close-reject-modal').forEach(button => {
                button.addEventListener('click', function() {
                    const modal = this.closest('.reject-modal');
                    if (modal) {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    }
                });
            });

            document.querySelectorAll('.reject-modal').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    }
                });
            });

            document.querySelectorAll('.open-detail-modal').forEach(button => {
                button.addEventListener('click', function() {
                    const modal = document.getElementById(this.dataset.modalTarget);
                    if (modal) {
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                    }
                });
            });

            document.querySelectorAll('.close-detail-modal').forEach(button => {
                button.addEventListener('click', function() {
                    const modal = this.closest('.detail-modal');
                    if (modal) {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    }
                });
            });

            document.querySelectorAll('.detail-modal').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    }
                });
            });
            });
        </script>
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Sipariş Geçmişi</h2>
        @if ($manager->subscriptions->isEmpty())
            <p class="mt-4 text-sm text-slate-500">Henüz sipariş kaydı yok.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Paket</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Dönem</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Başlangıç</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Bitiş</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Tür</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($manager->subscriptions as $subscription)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $subscription->package->name }}</div>
                                    <div class="text-xs text-slate-500">Fiyat: {{ number_format($subscription->price, 2) }} ₺</div>
                                </td>
                                <td class="px-4 py-3 capitalize">{{ $subscription->period }}</td>
                                <td class="px-4 py-3">{{ $subscription->started_at?->format('d.m.Y') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $subscription->expires_at?->format('d.m.Y') ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($subscription->is_trial)
                                        <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">Deneme</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Ücretli</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($subscription->isPending())
                                        <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800">Ödeme Bekliyor</span>
                                    @elseif ($subscription->is_active)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Aktif</span>
                                    @elseif ($subscription->isCancelled())
                                        <span class="inline-flex rounded-full bg-red-50 px-2 py-1 text-xs font-medium text-red-700">İptal</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">Pasif</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" class="open-detail-modal text-sm font-semibold text-slate-600 hover:text-slate-800" data-modal-target="detail-modal-{{ $subscription->id }}">Detay</button>
                                    @if ($subscription->isPending())
                                        <button type="button" class="open-approve-modal ml-2 text-sm font-semibold text-emerald-600 hover:text-emerald-700" data-modal-target="approve-modal-{{ $subscription->id }}">Onayla</button>
                                        <button type="button" class="open-reject-modal ml-2 text-sm font-semibold text-red-600 hover:text-red-700" data-modal-target="reject-modal-{{ $subscription->id }}">Reddet</button>
                                    @elseif (! $subscription->is_active && ! $subscription->isCancelled() && ! $subscription->is_trial)
                                        <form method="POST" action="{{ route('admin.managers.subscription.reactivate', [$manager, $subscription]) }}" class="inline ml-2" onsubmit="return confirm('Bu abonelik geri yüklenecek. Emin misiniz?')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Geri Yükle</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @foreach ($manager->subscriptions as $subscription)
            @if ($subscription->isPending())
                <div id="approve-modal-{{ $subscription->id }}" class="approve-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
                    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <h3 class="text-lg font-semibold text-slate-900">Siparişi Onayla</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ $subscription->package->name }} - {{ number_format($subscription->price, 2) }} ₺</p>

                        <form method="POST" action="{{ route('admin.managers.subscription.approve', [$manager, $subscription]) }}" class="mt-4 space-y-4">
                            @csrf
                            @method('PATCH')

                            <div>
                                <label class="block text-sm font-medium text-slate-700">Ödeme Yöntemi</label>
                                <div class="mt-2 flex gap-4">
                                    <label class="flex items-center gap-2">
                                        <input type="radio" name="payment_method" value="havale" checked class="border-slate-300 text-emerald-600 focus:ring-emerald-500" data-modal="{{ $subscription->id }}">
                                        <span class="text-sm text-slate-700">Havale</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="radio" name="payment_method" value="nakit" class="border-slate-300 text-emerald-600 focus:ring-emerald-500" data-modal="{{ $subscription->id }}">
                                        <span class="text-sm text-slate-700">Nakit</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label for="ref-code-{{ $subscription->id }}" class="ref-label block text-sm font-medium text-slate-700">Dekont / Referans Numarası</label>
                                <input type="text" name="reference_code" id="ref-code-{{ $subscription->id }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">Notlar</label>
                                <textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">{{ $subscription->notes }}</textarea>
                            </div>

                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" class="close-approve-modal rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Vazgeç</button>
                                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Onayla ve Aktif Et</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="reject-modal-{{ $subscription->id }}" class="reject-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
                    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <h3 class="text-lg font-semibold text-slate-900">Siparişi Reddet</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ $subscription->package->name }} - {{ number_format($subscription->price, 2) }} ₺</p>

                        <form method="POST" action="{{ route('admin.managers.subscription.reject', [$manager, $subscription]) }}" class="mt-4 space-y-4">
                            @csrf
                            @method('PATCH')

                            <div>
                                <label for="reject-notes-{{ $subscription->id }}" class="block text-sm font-medium text-slate-700">Reddetme Nedeni (Opsiyonel)</label>
                                <textarea name="rejection_notes" id="reject-notes-{{ $subscription->id }}" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm"></textarea>
                            </div>

                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" class="close-reject-modal rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Vazgeç</button>
                                <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Reddet</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endforeach

        @foreach ($manager->subscriptions as $subscription)
            <div id="detail-modal-{{ $subscription->id }}" class="detail-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold text-slate-900">Sipariş Detayı</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ $subscription->package->name }} - {{ number_format($subscription->price, 2) }} ₺</p>

                    <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-slate-500">Paket</span>
                            <p class="font-medium text-slate-900">{{ $subscription->package->name }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500">Dönem</span>
                            <p class="font-medium text-slate-900 capitalize">{{ $subscription->period }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500">Fiyat</span>
                            <p class="font-medium text-slate-900">{{ number_format($subscription->price, 2) }} ₺</p>
                        </div>
                        <div>
                            <span class="text-slate-500">Ödenen Toplam</span>
                            <p class="font-medium text-slate-900">{{ number_format($subscription->totalPaid(), 2) }} ₺</p>
                        </div>
                        <div>
                            <span class="text-slate-500">Başlangıç</span>
                            <p class="font-medium text-slate-900">{{ $subscription->started_at?->format('d.m.Y') ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500">Bitiş</span>
                            <p class="font-medium text-slate-900">{{ $subscription->expires_at?->format('d.m.Y') ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500">İptal/Bitiş Tarihi</span>
                            <p class="font-medium text-slate-900">{{ $subscription->ended_at?->format('d.m.Y') ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500">Tür</span>
                            <p class="font-medium text-slate-900">{{ $subscription->is_trial ? 'Deneme' : 'Ücretli' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500">Durum</span>
                            <p class="font-medium text-slate-900">
                                @if ($subscription->isPending())
                                    Ödeme Bekliyor
                                @elseif ($subscription->is_active)
                                    Aktif
                                @elseif ($subscription->isCancelled())
                                    İptal
                                @else
                                    Pasif
                                @endif
                            </p>
                        </div>
                        <div class="col-span-2">
                            <span class="text-slate-500">Notlar</span>
                            <p class="font-medium text-slate-900">{{ $subscription->notes ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h4 class="text-sm font-semibold text-slate-700">Özellikler</h4>
                        <ul class="mt-1 space-y-1 text-sm text-slate-700">
                            <li>Otomatik aidat planlama: {{ $subscription->feature_auto_dues ? 'Evet' : 'Hayır' }}</li>
                            <li>Kullanıcı portalı: {{ $subscription->feature_user_portal ? 'Evet' : 'Hayır' }}</li>
                            <li>Hesap ekstresi ve raporlar: {{ $subscription->feature_reports ? 'Evet' : 'Hayır' }}</li>
                            <li>Çoklu apartman yönetimi: {{ $subscription->feature_multi_apartment ? 'Evet' : 'Hayır' }}</li>
                            @if ($subscription->feature_multi_apartment && $subscription->multi_apartment_limit_override)
                                <li>Çoklu apartman limiti: {{ $subscription->multi_apartment_limit_override }}</li>
                            @endif
                        </ul>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="button" class="close-detail-modal rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Kapat</button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Ödeme Kayıtları</h2>
        @php
            $allPayments = $manager->subscriptions->flatMap->payments->sortByDesc('payment_date');
        @endphp
        @if ($allPayments->isEmpty())
            <p class="mt-4 text-sm text-slate-500">Henüz ödeme kaydı yok.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Paket</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Tutar</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Tarih</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Yöntem</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Referans</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($allPayments as $payment)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">{{ $payment->subscription->package->name }}</td>
                                <td class="px-4 py-3 font-medium">{{ number_format($payment->amount, 2) }} ₺</td>
                                <td class="px-4 py-3">{{ $payment->payment_date->format('d.m.Y') }}</td>
                                <td class="px-4 py-3 capitalize">{{ $payment->payment_method }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $payment->reference_code ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
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
