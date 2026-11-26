@extends('layouts.app')
@section('content')
<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
<div><h1 class="text-2xl font-bold text-slate-950">{{ \Carbon\Carbon::parse($period.'-01')->locale('tr')->isoFormat('MMMM YYYY') }} · Planlı Aidatlar</h1><p class="mt-1 text-sm text-slate-500">{{ $plan->name }} · Borç Türü: Aidat · Kategori: {{ $plan->category?->name ?? 'Genel Aidat' }}</p></div>
<a href="{{ route('due-plans.index', [...$plan->pageParameters(), 'plan_id' => $plan->id]) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Aidat Planına Dön</a>
</div>
@if($changed)<p role="alert" class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">Plan veya borç bilgileri değişti. Güncel ayrıntıları kontrol edin.</p>@endif
<div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
<p class="rounded-xl bg-white p-4 text-sm">Beklenen / Mevcut: <strong>{{ $inspection['expected_count'] }} / {{ $inspection['existing_count'] }}</strong></p>
<p class="rounded-xl bg-white p-4 text-sm">Eksik Aidat: <strong>{{ $inspection['missing_count'] }}</strong></p>
<p class="rounded-xl bg-white p-4 text-sm">Planlanan Toplam: <strong>{{ number_format($inspection['expected_cents'] / 100, 2, ',', '.') }} TL</strong></p>
<p class="rounded-xl bg-white p-4 text-sm">Mevcut Borçların Toplamı: <strong>{{ number_format($inspection['actual_cents'] / 100, 2, ',', '.') }} TL</strong></p>
</div>
@if(! $inspection['active'])<p class="mb-4 text-sm text-slate-500">Plan pasif. Eksik aidat oluşturulamaz.</p>
@elseif($inspection['skipped'])<p class="mb-4 text-sm text-slate-500">Bu dönem planda atlanmış. Aidat oluşturulamaz.</p>
@elseif($period > now()->format('Y-m'))<p class="mb-4 text-sm text-slate-500">Gelecek dönem aidatları henüz oluşturulamaz.</p>@endif
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm"><table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr>@foreach(['Daire', 'Hesap', 'Planlanan Tutar', 'Borç Tutarı', 'İşlem'] as $label)<th class="whitespace-nowrap p-3">{{ $label }}</th>@endforeach</tr></thead><tbody>
@forelse($inspection['rows'] as $row)<tr class="border-t border-slate-100"><td class="whitespace-nowrap p-3">{{ $row['label'] }}</td><td class="p-3">{{ $row['account_name'] ?? '—' }}</td><td class="whitespace-nowrap p-3">{{ $row['expected_cents'] === null ? '—' : number_format($row['expected_cents'] / 100, 2, ',', '.').' TL' }}</td><td class="p-3">
@if($row['due_id'])<a class="whitespace-nowrap font-semibold text-emerald-700 hover:underline" href="{{ route('dues.show', $row['due_id']) }}">{{ number_format($row['actual_cents'] / 100, 2, ',', '.') }} TL</a>
@elseif($row['missing'])<span class="font-semibold text-amber-700">Eksik</span>
@else<span class="text-xs text-slate-500">{{ $row['note'] }}</span>@endif
</td><td class="whitespace-nowrap p-3">
@if($row['missing'] && $inspection['can_complete'])
<form method="POST" action="{{ route('due-plans.period-complete', ['duePlan' => $plan->id, 'period' => $period]) }}">@csrf<input type="hidden" name="confirmation_token" value="{{ $token }}"><input type="hidden" name="unit_id" value="{{ $row['unit_id'] }}"><button class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" aria-label="Daire {{ $row['label'] }} eksik aidatını tamamla">Tamamla</button></form>
@else<span class="text-slate-400">—</span>@endif
</td></tr>
@empty<tr><td colspan="5" class="p-6 text-center text-slate-500">Daire bulunamadı.</td></tr>@endforelse
</tbody></table></div>
@if($inspection['can_complete'])
<form method="POST" action="{{ route('due-plans.period-complete', ['duePlan' => $plan->id, 'period' => $period]) }}" class="mt-5 flex flex-wrap items-center gap-3">@csrf<input type="hidden" name="confirmation_token" value="{{ $token }}"><p class="text-sm text-slate-500">{{ $inspection['missing_count'] }} eksik aidat · {{ number_format($inspection['missing_cents'] / 100, 2, ',', '.') }} TL. Mevcut borçlar değişmez.</p><button class="rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white">Eksik Aidatları Oluştur</button></form>
@elseif($inspection['missing_count'] === 0 && $inspection['expected_count'] > 0)<p class="mt-4 text-sm font-semibold text-emerald-700">Bu dönemin planlı aidat kayıtları tamamlandı.</p>@endif
@endsection
