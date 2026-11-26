@extends('layouts.app')
@section('content')
@include('meters._nav')
<p class="mb-4 text-sm text-slate-500">{{ $apartment->name }} · Tüketim türünü ve kullanım dönemini seçip dairelerin okumalarını girin.</p>
<div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
@foreach(\App\Models\ConsumptionSetting::TYPES as $type => $label)
<a href="{{ route('meters.index', ['consumption_type' => $type]) }}" @if($filters['consumption_type'] === $type) aria-current="page" @endif class="rounded-2xl border p-4 font-semibold {{ $filters['consumption_type'] === $type ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-400' }}">{{ $label }}</a>
@endforeach
</div>
<details class="mb-5 rounded-2xl border border-slate-200 bg-white p-4">
<summary class="cursor-pointer text-sm font-semibold">{{ \App\Models\ConsumptionSetting::METHODS[$setting->method] }} · {{ $setting->measurement_unit }} <span class="font-normal text-slate-500">— Ölçüm ayarları</span></summary>
<form method="POST" action="{{ route('meters.settings.update') }}" class="mt-4 grid items-end gap-3 sm:grid-cols-3">
@csrf @method('PUT') @foreach($filters as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
<label class="text-sm">Ölçüm yöntemi<select name="method" class="mt-1 block w-full rounded-xl border border-slate-300 p-2">@foreach(\App\Models\ConsumptionSetting::METHODS as $key => $label)<option value="{{ $key }}" @selected($setting->method === $key)>{{ $label }}</option>@endforeach</select></label>
<label class="text-sm">Ölçüm birimi<input name="measurement_unit" required maxlength="30" value="{{ $setting->measurement_unit }}" class="mt-1 block w-full rounded-xl border border-slate-300 p-2"></label>
<button class="rounded-xl border border-slate-300 p-2 font-semibold">Ayarları Kaydet</button>
<p class="text-xs text-slate-500 sm:col-span-3">Bu ayarlar seçili tüketim türü için tüm dairelerde geçerlidir. Geçmiş ölçümler kendi birim ve yöntemleriyle korunur. Yöntem veya birim değişirse ilk endeks girişinde önceki okuma yeniden istenir. Ayar değiştirmeden önce girilmiş okumaları kaydedin.</p>
</form></details>
<p class="mb-4 text-sm text-slate-500">{{ \App\Models\ConsumptionSetting::METHODS[$setting->method] }} · {{ $setting->measurement_unit }}</p>
<div class="mb-4 flex flex-wrap justify-between gap-2 text-sm"><h2 class="font-semibold">{{ \App\Models\ConsumptionSetting::TYPES[$filters['consumption_type']] }}</h2><p class="text-slate-500">Boş alan kaydedilmez. Sıfır tüketim için 0 girin.</p></div>
<form method="POST" action="{{ route('meters.readings.store') }}">
@csrf @foreach($filters as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
<input type="hidden" name="method" value="{{ $setting->method }}"><input type="hidden" name="measurement_unit" value="{{ $setting->measurement_unit }}">
<div class="mb-5 grid gap-3 rounded-2xl bg-white p-4 sm:grid-cols-2">
<label class="text-sm">Başlangıç tarihi<input type="date" id="reading-start-date" name="start_date" @if($minimumStart) min="{{ $minimumStart }}" @endif required value="{{ $startDate }}" class="mt-1 block w-full rounded-xl border border-slate-300 p-2"></label>
<label class="text-sm">Bitiş tarihi<input type="date" name="end_date" required value="{{ old('end_date', now()->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border border-slate-300 p-2"></label>
<p class="text-xs text-slate-500 sm:col-span-2">Başlangıç son okuma döneminin bitişinden önce olamaz. Tarihleri ve dairelerin değerlerini girip kaydedin.</p>
</div>
<div class="max-h-[65vh] overflow-auto rounded-2xl border border-slate-200 bg-white">
<table class="w-full min-w-[480px] text-left text-sm">
<thead class="sticky top-0 z-10 bg-slate-50 text-slate-600"><tr>
<th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold">Daire</th>
@if($setting->method === 'index')
<th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold">Önceki Okuma</th>
<th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold">Yeni Okuma</th>
@endif
<th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold">{{ $setting->method === 'index' ? 'Tüketim' : 'Dönemsel Tüketim' }} ({{ $setting->measurement_unit }})</th>
</tr></thead>
<tbody class="divide-y divide-slate-100">
@forelse($units as $unit)
@php
$unitRecords = $records->get($unit->id, collect());
$prior = $unitRecords->filter(fn ($r) => $r->readingPeriod->end_date->format('Y-m-d') <= $startDate);
$previous = \App\Services\MeterReadingService::previous($prior, $setting);
$priorReadings = $unitRecords->map(fn ($r) => ['end' => $r->readingPeriod->end_date->format('Y-m-d'), 'method' => $r->method, 'unit' => $r->measurement_unit, 'value' => $r->value])->values();
@endphp
<tr class="hover:bg-slate-50" data-reading-row data-method="{{ $setting->method }}" data-unit="{{ $setting->measurement_unit }}">
<th scope="row" class="whitespace-nowrap px-3 py-2 font-medium text-slate-800">Daire {{ $unit->unit_no }}@if($unit->block)<span class="ml-2 text-xs font-normal text-slate-500">Blok {{ $unit->block }}</span>@endif</th>
@if($setting->method === 'index')
<td class="px-3 py-2">
<input type="number" name="initials[{{ $unit->id }}]" value="{{ $previous ?? old('initials.'.$unit->id) }}" @readonly($previous !== null) min="0" max="999999999999.9999" step="0.0001" placeholder="İlk okuma" aria-label="Daire {{ $unit->unit_no }} önceki okuma" class="block min-h-11 w-full min-w-28 rounded-lg border border-slate-300 px-3 py-2 tabular-nums read-only:bg-slate-100" data-previous-input data-auto="{{ $previous !== null ? '1' : '0' }}">
<script type="application/json" data-prior-readings>@json($priorReadings)</script>
</td>
@endif
<td class="px-3 py-2"><input aria-label="Daire {{ $unit->unit_no }} {{ $setting->method === 'index' ? 'yeni okuma' : 'tüketim' }}" type="number" step="0.0001" min="0" max="999999999999.9999" name="values[{{ $unit->id }}]" value="{{ old('values.'.$unit->id) }}" class="block min-h-11 w-full min-w-28 rounded-lg border border-slate-300 px-3 py-2 tabular-nums" data-reading-input></td>
@if($setting->method === 'index')<td class="whitespace-nowrap px-3 py-2 font-semibold tabular-nums" aria-live="polite"><span data-consumption>—</span></td>@endif
</tr>
@empty<tr><td colspan="{{ $setting->method === 'index' ? 4 : 2 }}" class="p-6 text-center text-slate-500">Bu apartmanda daire bulunmuyor. Mevcut Daireler ekranından daire ekleyebilirsiniz.</td></tr>@endforelse
</tbody></table>
</div>
@if($units->isNotEmpty())<button class="mt-5 w-full rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white sm:w-auto">Okumaları Kaydet</button>@endif
</form>
<script>
document.querySelectorAll('[data-reading-row]').forEach(row => {
    const input = row.querySelector('[data-reading-input]');
    if (!input) return;
    const previous = row.querySelector('[data-previous-input]');
    const refresh = () => {
        let result = '—';
        if (input.value !== '' && (row.dataset.method !== 'index' || previous.value !== '')) {
            const value = Number(input.value) - (row.dataset.method === 'index' ? Number(previous.value) : 0);
            result = value < 0 ? 'Geçersiz endeks' : value.toLocaleString('tr-TR', {maximumFractionDigits: 4});
        }
        const output = row.querySelector('[data-consumption]');
        if (output) output.textContent = result;
    };
    input.addEventListener('input', refresh);
    previous?.addEventListener('input', refresh);
    const start = document.getElementById('reading-start-date');
    if (start && previous) {
        const records = JSON.parse(row.querySelector('[data-prior-readings]').textContent);
        start.addEventListener('change', () => {
            const prior = records.find(record => record.end <= start.value);
            const automatic = prior && prior.method === 'index' && prior.unit === row.dataset.unit;
            if (automatic) previous.value = prior.value;
            else if (previous.dataset.auto === '1') previous.value = '';
            previous.readOnly = Boolean(automatic);
            previous.dataset.auto = automatic ? '1' : '0';
            refresh();
        });
    }
    refresh();
});
</script>
@endsection
