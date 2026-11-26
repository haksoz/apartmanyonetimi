@extends('layouts.app')
@section('content')
<h1 class="mb-2 text-2xl font-bold text-slate-950">Tüketime Göre Borçlandırma Önizlemesi</h1>
<p class="mb-5 text-sm text-slate-500">{{ \App\Models\ConsumptionSetting::TYPES[$summary['type']] }} · {{ $summary['start_date'] }} – {{ $summary['end_date'] }}</p>
@if($input['source_type'] === 'expenses')
<p class="mb-4 text-sm text-slate-500">Gider dönemi: {{ \Carbon\Carbon::parse($input['source_period'].'-01')->locale('tr')->translatedFormat('F Y') }}@if(($input['source_period_end'] ?? $input['source_period']) !== $input['source_period']) – {{ \Carbon\Carbon::parse($input['source_period_end'].'-01')->locale('tr')->translatedFormat('F Y') }}@endif</p>
@endif
@if($changed)<p role="alert" class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">Veriler değişti. Güncel dağıtımı kontrol edip tekrar onaylayın.</p>@endif
@if(! $summary['can_confirm'])<p role="alert" class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-red-700">Eksik okumaları ve hesap sorunlarını giderin. Toplam tüketim sıfırdan büyük olmalıdır. Henüz borçlandırma oluşturulmadı.</p>@endif
<div class="mb-5 grid gap-3 sm:grid-cols-3">
<p class="rounded-xl bg-white p-4 text-sm">Dağıtılacak Tutar<br><strong>{{ number_format($summary['amount_cents'] / 100, 2, ',', '.') }} TL</strong></p>
<p class="rounded-xl bg-white p-4 text-sm">Toplam Tüketim<br><strong>{{ $summary['total_consumption'] }} {{ $summary['unit'] }}</strong></p>
<p class="rounded-xl bg-white p-4 text-sm">Dağıtılan Tutar<br><strong>{{ number_format($summary['billed_cents'] / 100, 2, ',', '.') }} TL</strong></p>
</div>
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm"><table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr>@foreach(['Daire', 'Borçlanacak Hesap', 'Tüketim', 'Oran', 'Borç Tutarı', 'Durum'] as $label)<th class="whitespace-nowrap p-3">{{ $label }}</th>@endforeach</tr></thead><tbody>
@foreach($summary['rows'] as $row)<tr class="border-t border-slate-100"><td class="p-3">{{ $row['label'] }}</td><td class="p-3">{{ $row['account_name'] ?? '—' }}</td><td class="whitespace-nowrap p-3">{{ $row['consumption'] === null ? 'Eksik okuma' : $row['consumption'].' '.$summary['unit'] }}</td><td class="p-3">{{ $summary['total_weight'] !== '0' ? number_format((float) $row['weight'] / (float) $summary['total_weight'] * 100, 4, ',', '.') : '0' }}%</td><td class="whitespace-nowrap p-3 font-semibold">{{ number_format($row['amount_cents'] / 100, 2, ',', '.') }} TL</td><td class="p-3 {{ $row['error'] ? 'text-red-700' : 'text-slate-500' }}">{{ $row['error'] ?? ($row['weight'] === '0' ? 'Sıfır tüketim; borç oluşturulmaz' : ($row['amount_cents'] === 0 ? 'Tutar 0,00 TL; borç oluşturulmaz' : 'Hazır')) }}</td></tr>@endforeach
</tbody></table></div>
<p class="mt-3 text-xs text-slate-500">@if($summary['round_up'])Her dairenin tutarı bir üst tam TL’ye yuvarlanır. Yuvarlama farkı: {{ number_format(($summary['billed_cents'] - $summary['amount_cents']) / 100, 2, ',', '.') }} TL.@else Kuruş farkları en büyük kalan yöntemiyle paylaştırılır. Toplam tutar korunur.@endif Bu önizleme okuma dönemini kullanılmış yapmaz.</p>
<form method="POST" action="{{ route('dues.consumption.confirm') }}" class="mt-6 flex flex-wrap gap-3">@csrf<input type="hidden" name="confirmation_token" value="{{ $token }}"><button name="return_to_form" value="1" class="rounded-xl border border-slate-300 px-5 py-3 font-semibold">Düzenlemeye Dön</button><button @disabled(! $summary['can_confirm']) class="rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white disabled:opacity-50">Borçlandırmayı Oluştur</button></form>
@endsection
