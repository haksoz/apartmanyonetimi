@extends('layouts.app')
@section('content')
@include('meters._nav')
<form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4">
<label class="w-full text-sm font-medium sm:w-64">Tüketim Türü<select name="consumption_type" class="mt-1 block w-full rounded-xl border border-slate-300 p-2"><option value="">Tüm türler</option>@foreach(\App\Models\ConsumptionSetting::TYPES as $key => $label)<option value="{{ $key }}" @selected(($filters['consumption_type'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label>
<button class="rounded-xl bg-slate-800 px-4 py-2 text-white">Filtrele</button>
</form>
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
<table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr>
@foreach(['Okuma Dönemi', 'Tüketim Türü', 'Yöntem', 'Okunan Daire', 'Toplam Tüketim', ''] as $label)<th class="whitespace-nowrap p-3">{{ $label }}</th>@endforeach
</tr></thead><tbody>
@forelse($periodSummaries as $summary)
<tr class="border-t border-slate-100 hover:bg-slate-50">
<td class="whitespace-nowrap p-3"><a class="font-semibold text-emerald-700 hover:underline" href="{{ route('meters.history.detail', $summary->id) }}">{{ \Illuminate\Support\Carbon::parse($summary->start_date)->format('d.m.Y') }} – {{ \Illuminate\Support\Carbon::parse($summary->end_date)->format('d.m.Y') }}</a></td>
<td class="whitespace-nowrap p-3">{{ \App\Models\ConsumptionSetting::TYPES[$summary->consumption_type] }}</td>
<td class="whitespace-nowrap p-3">{{ \App\Models\ConsumptionSetting::METHODS[$summary->method] }}</td>
<td class="whitespace-nowrap p-3">{{ $summary->reading_count }}
@php($distribution = $distributionCounts->get($summary->id))
@if($distribution)
<span class="ml-2 inline-block rounded-full px-2 py-0.5 text-xs {{ $distribution['existing'] < $distribution['expected'] ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}" title="Beklenen borç / mevcut borç sayısı. Ödeme durumunu göstermez.">Dağıtım: {{ $distribution['expected'] }}/{{ $distribution['existing'] }}</span>
@else
<span class="ml-2 text-xs text-slate-400">Dağıtılmadı</span>
@endif
</td>
<td class="whitespace-nowrap p-3 font-semibold">{{ number_format($summary->total, 4, ',', '.') }} {{ $summary->measurement_unit }}</td>
<td class="p-3"><a class="font-semibold text-emerald-700 hover:underline" href="{{ route('meters.history.detail', $summary->id) }}" aria-label="{{ \App\Models\ConsumptionSetting::TYPES[$summary->consumption_type] }} {{ $summary->start_date }} dönem ayrıntıları">Ayrıntılar</a></td>
</tr>
@empty<tr><td colspan="6" class="p-6 text-center text-slate-500">Seçili filtrelerde ölçüm bulunamadı.</td></tr>@endforelse
</tbody></table></div>
<div class="mt-4">{{ $periodSummaries->links() }}</div>
@endsection
