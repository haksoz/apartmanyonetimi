@extends('layouts.app')
@section('content')
@include('meters._nav')
<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
<div><h2 class="text-xl font-bold text-slate-950">{{ \App\Models\ConsumptionSetting::TYPES[$period->consumption_type] }}</h2>
<p class="mt-1 text-sm text-slate-500">{{ $period->start_date->format('d.m.Y') }} – {{ $period->end_date->format('d.m.Y') }} · {{ \App\Models\ConsumptionSetting::METHODS[$period->method] }}</p></div>
<a href="{{ route('meters.history') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Tüketim Geçmişine Dön</a>
</div>
<div class="mb-5 flex flex-wrap gap-3"><p class="rounded-xl bg-emerald-50 p-4 text-sm">Toplam Tüketim: <strong>{{ number_format($total, 4, ',', '.') }} {{ $period->measurement_unit }}</strong></p><p class="rounded-xl bg-white p-4 text-sm">Okunan Daire: <strong>{{ $count }}</strong></p></div>
@foreach($batches as $batch)
@php($state = $tracking[$batch->id])
<section class="mb-5 rounded-2xl border border-slate-200 bg-white p-4">
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
<h3 class="font-semibold">Borçlandırma #{{ $batch->id }} · <span class="{{ $state['cancelled'] ? 'text-slate-500' : ($state['missing_count'] ? 'text-amber-700' : 'text-emerald-700') }}">{{ $state['status'] }}</span></h3>
<a class="text-sm font-semibold text-emerald-700" href="{{ route('dues.index', ['batch_id' => $batch->id]) }}">Borçlandırmayı Görüntüle</a>
</div>
<div class="mb-4 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
<p>Beklenen / Mevcut: <strong>{{ $state['expected_count'] }} / {{ $state['existing_count'] }}</strong></p>
<p>Eksik: <strong>{{ $state['missing_count'] }}</strong></p>
<p>İlk Toplam: <strong>{{ number_format($state['original_cents'] / 100, 2, ',', '.') }} TL</strong></p>
<p>Silinmemiş Borçların Toplamı: <strong>{{ number_format($state['actual_cents'] / 100, 2, ',', '.') }} TL</strong></p>
</div>
@if(! $state['cancelled'])
<div class="mt-4 flex flex-wrap items-center gap-4">
@if($state['missing_count'])<form method="POST" action="{{ route('dues.consumption.repair-preview', $batch->id) }}">@csrf<button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Eksik Borçları Tamamla</button></form>@endif
<form method="POST" action="{{ route('dues.consumption.cancel', $batch->id) }}" onsubmit="return confirm('Bu döneme bağlı tüm ödenmemiş borçlar iptal edilsin mi?')">@csrf<button class="text-sm font-semibold text-red-700">Borçlandırmayı İptal Et</button></form>
</div>
@endif
</section>
@endforeach
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm"><table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr>
<th class="p-3">Daire</th>
@if($period->method === 'index')<th class="whitespace-nowrap p-3">Önceki Okuma</th><th class="whitespace-nowrap p-3">Yeni Okuma</th>@endif
<th class="p-3">Tüketim</th>
<th class="whitespace-nowrap p-3">Borç Tutarı</th>
<th class="p-3">İşlem</th>
</tr></thead><tbody>
@forelse($readings as $reading)
<tr class="border-t border-slate-100"><td class="whitespace-nowrap p-3">{{ $reading->unit->block }} {{ $reading->unit->unit_no }}</td>
@if($period->method === 'index')<td class="p-3">{{ $reading->previous_value }}</td><td class="p-3">{{ $reading->value }}</td>@endif
<td class="whitespace-nowrap p-3 font-semibold">{{ $reading->consumption }} {{ $reading->measurement_unit }}</td>
@php($billing = $billingRows->get($reading->unit_id))
<td class="p-3">
@if($billing && $billing['due_id'])
<a class="whitespace-nowrap font-semibold text-emerald-700 hover:underline" href="{{ route('dues.show', $billing['due_id']) }}">{{ number_format($billing['actual_cents'] / 100, 2, ',', '.') }} TL</a>
@elseif($billing && $billing['missing'])
<span class="whitespace-nowrap font-semibold text-amber-700">{{ number_format($billing['expected_cents'] / 100, 2, ',', '.') }} TL · Eksik</span>

@elseif($billing)
<span class="whitespace-nowrap text-slate-500">0,00 TL</span>
@else
<span class="text-slate-400">—</span>
@endif
@if($billing && $billing['error'])<p class="mt-1 text-xs text-red-700">{{ $billing['error'] }}</p>@endif
</td><td class="whitespace-nowrap p-3">
@if($billing && $billing['missing'])
<form method="POST" action="{{ route('dues.consumption.repair-preview', $activeBatch->id) }}">
@csrf<input type="hidden" name="unit_id" value="{{ $reading->unit_id }}">
<button @disabled($billing['error'] !== null) class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 disabled:opacity-50" aria-label="Daire {{ $reading->unit->unit_no }} eksik borcunu tamamla">Tamamla</button>
</form>
@else<span class="text-slate-400">—</span>@endif
</td></tr>
@empty<tr><td colspan="{{ $period->method === 'index' ? 6 : 4 }}" class="p-6 text-center text-slate-500">Bu dönemde ölçüm bulunamadı.</td></tr>@endforelse
</tbody></table></div><div class="mt-4">{{ $readings->links() }}</div>
@endsection
