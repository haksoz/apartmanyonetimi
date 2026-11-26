@extends('layouts.app')
@section('content')
<h1 class="mb-2 text-2xl font-bold text-slate-950">Eksik Borçları Tamamlama Önizlemesi</h1>
<p class="mb-5 text-sm text-slate-500">Borçlandırma #{{ $batch->id }} · İlk dağıtımdaki hesap ve tutarlar kullanılır. Mevcut ve ödenmiş borçlar korunur.</p>
@if($changed)<p role="alert" class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">Borç durumu değişti. Güncel önizlemeyi kontrol edin.</p>@endif
@if(! $summary['can_repair'])<p role="alert" class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $summary['cancelled'] ? 'Bu borçlandırma iptal edilmiş; tamamlanamaz.' : 'Tamamlanabilecek eksik borç yok veya hesap bilgilerinin kontrol edilmesi gerekiyor.' }}</p>@endif
<div class="mb-5 rounded-xl bg-white p-4 text-sm">Tamamlanacak Borç: <strong>{{ $summary['missing_count'] }}</strong> · Toplam: <strong>{{ number_format($summary['repair_cents'] / 100, 2, ',', '.') }} TL</strong></div>
<div class="overflow-x-auto rounded-2xl bg-white"><table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr><th class="p-3">Daire</th><th class="p-3">İlk Hesap</th><th class="p-3">Borç Tutarı</th><th class="p-3">Durum</th></tr></thead><tbody>
@foreach($summary['rows'] as $row)@if($row['missing'])<tr class="border-t border-slate-100"><td class="p-3">{{ $row['label'] }}</td><td class="p-3">{{ $row['account_name'] }}</td><td class="whitespace-nowrap p-3">{{ number_format($row['expected_cents'] / 100, 2, ',', '.') }} TL</td><td class="p-3 {{ $row['error'] ? 'text-red-700' : 'text-slate-500' }}">{{ $row['error'] ?? 'İlk borcun tarih ve kategorisiyle yeniden oluşturulacak' }}</td></tr>@endif @endforeach
</tbody></table></div>
<form method="POST" action="{{ route('dues.consumption.repair-confirm') }}" class="mt-5 flex flex-wrap gap-3">@csrf<input type="hidden" name="confirmation_token" value="{{ $token }}"><a href="{{ route('meters.history.detail', $batch->consumption_period_id) }}" class="rounded-xl border border-slate-300 px-4 py-3 font-semibold">Geri</a><button @disabled(! $summary['can_repair']) class="rounded-xl bg-emerald-600 px-4 py-3 font-semibold text-white disabled:opacity-50">Eksik Borçları Oluştur</button></form>
@endsection
