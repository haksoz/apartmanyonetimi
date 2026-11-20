@extends('layouts.app')

@section('title', 'Fiyat ve özellikler')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Fiyat ve özellikler</h1>
        <p class="mt-1 text-sm text-slate-500">Daire bandı, kampanya ve özellik erişimi. 101 ve üzeri daire teklif talebine gider. 151+ için kayıtlı özel fiyat, apartman kaydındaki teklif alanından girilir.</p>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">Vergi ayarı</h2>
            <p class="mt-1 text-sm text-slate-500">Fiyat bantları ve özel teklif fiyatları KDV hariç net tutardır. Kampanya indirimi net tutara uygulanır, ardından KDV eklenir.</p>
            <form method="POST" action="{{ route('admin.commercial.tax.update') }}" class="mt-4 flex max-w-md items-end gap-3">
                @csrf
                @method('PATCH')
                <label class="flex-1 text-sm font-medium text-slate-700">
                    Abonelik KDV oranı (%)
                    <input type="number" step="0.01" min="0" max="100" name="subscription_tax_rate" value="{{ old('subscription_tax_rate', $settings->subscription_tax_rate) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                </label>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Kaydet</button>
            </form>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">Fiyat bantları</h2>
            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3">Daire bandı</th>
                            <th scope="col" class="px-4 py-3">Aylık net (KDV hariç)</th>
                            <th scope="col" class="px-4 py-3">Yıllık net (KDV hariç)</th>
                            <th scope="col" class="px-4 py-3 text-center">Aktif</th>
                            <th scope="col" class="px-4 py-3 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($bands as $band)
                            <tr>
                                <th scope="row" class="px-4 py-3 text-left font-normal">
                                    <div class="font-semibold text-slate-900">{{ $band->label }}</div>
                                    <div class="text-xs text-slate-500">{{ $band->min_units }}–{{ $band->max_units ?? '∞' }} daire</div>
                                </th>
                                @if ($band->is_quote)
                                    <td colspan="2" class="px-4 py-3 text-slate-500">Teklif bandı. Fiyat, apartman kaydına yazılır.</td>
                                @else
                                    <td class="px-4 py-3">
                                        <input form="price-band-{{ $band->id }}" aria-label="{{ $band->label }} aylık net (KDV hariç)" type="number" step="0.01" min="0" name="monthly_price" value="{{ $band->monthly_price }}" class="w-full min-w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input form="price-band-{{ $band->id }}" aria-label="{{ $band->label }} yıllık net (KDV hariç)" type="number" step="0.01" min="0" name="yearly_price" value="{{ $band->yearly_price }}" class="w-full min-w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    </td>
                                @endif
                                <td class="px-4 py-3 text-center">
                                    <input form="price-band-{{ $band->id }}" aria-label="{{ $band->label }} aktif" type="checkbox" name="is_active" value="1" {{ $band->is_active ? 'checked' : '' }}>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form id="price-band-{{ $band->id }}" method="POST" action="{{ route('admin.commercial.bands.update', $band) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Kaydet</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">Kampanya</h2>
            <form method="POST" action="{{ route('admin.commercial.campaigns.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf
                <label class="text-sm font-medium text-slate-700">
                    Ad
                    <input name="name" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Bant
                    <select name="price_band_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Tüm bantlar</option>
                        @foreach ($bands as $band)
                            <option value="{{ $band->id }}">{{ $band->label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Yüzde indirim
                    <input type="number" step="0.01" min="0" max="100" name="percent_off" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Net tutar indirimi
                    <input type="number" step="0.01" min="0" name="amount_off" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Başlangıç
                    <input type="date" name="starts_at" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Bitiş
                    <input type="date" name="ends_at" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <div class="sm:col-span-2">
                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Kampanya ekle</button>
                </div>
            </form>
            @if ($campaigns->isNotEmpty())
                <ul class="mt-4 divide-y divide-slate-100 text-sm">
                    @foreach ($campaigns as $campaign)
                        <li class="py-2">{{ $campaign->name }} · {{ $campaign->band?->label ?? 'Tüm bantlar' }} · {{ $campaign->is_active ? 'Aktif' : 'Pasif' }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">Özellik erişimi</h2>
            <div class="mt-4 divide-y divide-slate-100">
                @foreach ($features as $feature)
                    <form method="POST" action="{{ route('admin.commercial.features.update', $feature) }}" class="flex items-center justify-between gap-4 py-3">
                        @csrf
                        @method('PATCH')
                        <div>
                            <div class="text-sm font-medium text-slate-900">{{ $feature->name }}</div>
                            <div class="text-xs text-slate-500">{{ $feature->key }}</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <select name="access" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="free" {{ $feature->access === 'free' ? 'selected' : '' }}>Ücretsiz</option>
                                <option value="paid" {{ $feature->access === 'paid' ? 'selected' : '' }}>Ücretli</option>
                            </select>
                            <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Kaydet</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </section>
    </div>
@endsection
