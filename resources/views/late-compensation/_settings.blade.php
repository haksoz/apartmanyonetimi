@php
    $latestPolicy = $lateCompensationPolicies->first();
    $today = now('Europe/Istanbul')->toDateString();
    $currentPolicy = $lateCompensationPolicies->first(fn ($policy) => $policy->effective_from->toDateString() <= $today);
@endphp
<section class="space-y-3 rounded-2xl bg-white p-4 shadow-sm sm:p-5">
    <h2 class="font-semibold text-slate-900">Gecikme Tazminatı</h2>
    <p class="text-sm text-slate-600">Bugünkü durum {{ $currentPolicy?->is_active ? 'aktif' : 'pasif' }}.</p>
    <p class="text-sm text-slate-600">Uygulama başlangıcı için bugün veya ileri bir tarih seçilebilir. Geçmiş günler için gecikme tazminatı hesaplanmaz.</p>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="late_compensation[is_active]" value="0">
        <input type="checkbox" name="late_compensation[is_active]" value="1" @checked(old('late_compensation.is_active', $latestPolicy?->is_active))>
        Gecikme Tazminatı aktif
    </label>
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="block text-sm">
            Aylık oran (%)
            <input type="number" name="late_compensation[monthly_rate]" min="0" max="9999.999999" step="0.000001"
                value="{{ old('late_compensation.monthly_rate', $latestPolicy?->monthly_rate) }}" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2">
            @error('late_compensation.monthly_rate')<span class="text-red-600">{{ $message }}</span>@enderror
        </label>
        @if ($latestPolicy)
            <label class="block text-sm">
                Yeni değişikliğin geçerlilik tarihi
                <input type="date" name="late_compensation[effective_from]" min="{{ max($today, $apartment->late_compensation_starts_on?->toDateString() ?? $today) }}"
                    value="{{ old('late_compensation.effective_from') }}" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2">
                @error('late_compensation.effective_from')<span class="text-red-600">{{ $message }}</span>@enderror
            </label>
        @else
            <label class="block text-sm">
                Uygulama başlangıç tarihi
                <input type="date" name="late_compensation[starts_on]" min="{{ $today }}"
                    value="{{ old('late_compensation.starts_on') }}" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2">
                @error('late_compensation.starts_on')<span class="text-red-600">{{ $message }}</span>@enderror
            </label>
        @endif
    </div>
    @unless ($lateCompensationPolicies->contains('is_active', true))
        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="late_compensation[include_open]" value="1" @checked(old('late_compensation.include_open'))>
                Mevcut açık borçlara da uygula
            </label>
            <p class="text-sm text-slate-600">
                Bu seçenek yalnızca ilk etkinleştirmede kullanılabilir. Önizlemede uygun görünen açık borçlar, ayarları kaydettiğinizde kapsama alınır.
                Hesaplama, seçtiğiniz başlangıç tarihi ile vadenin ertesi gününden hangisi daha geçse o gün başlar. Başlangıcı bugün seçerseniz vadesi geçmiş uygun borçlarda hesaplama bugünden başlar.
            </p>
            <button type="submit" name="preview_late_compensation" value="1" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                Açık Borçları Önizle
            </button>
            @error('late_compensation.include_open')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @if ($lateCompensationPreview !== null)
                <p class="text-sm font-medium">Başlangıç tarihi {{ $lateCompensationPreview['start'] }}</p>
                @forelse ($lateCompensationPreview['rows'] as $row)
                    <p class="text-sm">
                        {{ $row['label'] }} · {{ $row['reason'] ?? (number_format((float) $row['principal'], 2, ',', '.').' TL başlangıç ana parası') }}
                    </p>
                @empty
                    <p class="text-sm">Önizlenecek açık borç bulunamadı.</p>
                @endforelse
            @endif
        </div>
    @endunless
    @if ($latestPolicy)
        <p class="text-sm text-slate-600">Uygulama başlangıcı {{ $apartment->late_compensation_starts_on?->format('d.m.Y') }}.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="mb-2 text-left font-semibold">Politika geçmişi</caption>
                <thead><tr><th scope="col">Geçerlilik tarihi</th><th scope="col">Durum</th><th scope="col">Aylık oran (%)</th></tr></thead>
                <tbody>
                    @foreach ($lateCompensationPolicies as $policy)
                        <tr class="border-t border-slate-200">
                            <td class="py-2">{{ $policy->effective_from->format('d.m.Y') }}</td>
                            <td>{{ $policy->is_active ? 'Aktif' : 'Pasif' }}</td>
                            <td>{{ str_replace('.', ',', $policy->monthly_rate) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    <div class="flex justify-end">
        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
            Kaydet
        </button>
    </div>
</section>
