@extends('layouts.app')

@section('title', $apartment->name)

@section('content')
    @php
        $active = $apartment->commercial_active;
        $pending = $apartment->commercial_pending;
        $expired = $apartment->commercial_expired;
        $quoteScale = (int) $apartment->unit_count > 100;
        $place = collect([$apartment->district, $apartment->province])->filter()->implode(' / ');
        $periodLabel = $active?->subscription?->notes === 'Tanımlı süre'
            ? 'Tanımlı süre'
            : ($active?->subscription?->period === 'yearly' ? 'Yıllık' : ($active ? 'Aylık' : null));
        if ($active) {
            $statusLabel = 'AidatCep Ücretli Kullanım';
            $statusClass = 'bg-emerald-50 text-emerald-800';
        } elseif ($quoteScale) {
            $statusLabel = 'Özel Teklif';
            $statusClass = 'bg-slate-100 text-slate-700';
        } elseif ($expired) {
            $statusLabel = 'Ücretli Kullanım Sona Erdi';
            $statusClass = 'bg-red-50 text-red-700';
        } else {
            $statusLabel = 'AidatCep Ücretsiz Kullanım';
            $statusClass = 'bg-slate-100 text-slate-700';
        }
        $rows = [
            'Apartman kodu' => $apartment->code,
            'Daire sayısı' => $apartment->unit_count,
            'Adres' => $apartment->address,
            'İl' => $apartment->province,
            'İlçe' => $apartment->district,
            'Kayıt tarihi' => $apartment->created_at?->format('d.m.Y'),
            'Kurulum tarihi' => $apartment->setup_completed_at?->format('d.m.Y'),
            'Yönetici' => $apartment->user?->name,
            'Yönetici dairesi' => $apartment->managerUnit?->unit_no,
            'Kayıt durumu' => $apartment->is_active ? 'Aktif' : 'Pasif',
            'Kullanım' => $statusLabel,
        ];
    @endphp

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-slate-950 sm:text-2xl">{{ $apartment->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->unit_count }} daire{{ $place !== '' ? ' · '.$place : '' }}</p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2">
            <a href="{{ route('subscriber.apartments.edit', $apartment) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Bilgileri Düzenle</a>
            @if (\App\Support\FeatureGate::allows($apartment, 'auto_dues'))
                <form method="POST" action="{{ route('subscriber.apartments.trigger-aidat', $apartment) }}">
                    @csrf
                    <button type="submit" class="rounded-xl border border-emerald-600 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Aidat Tetikle</button>
                </form>
            @endif
            <button type="button" onclick="if (window.history.length > 1) { history.back(); } else { window.location.href = '{{ route('subscriber.apartments.index') }}'; }" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h14" />
                </svg>
                Geri
            </button>
        </div>
    </div>

    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-slate-900">Apartman bilgileri</h2>
            <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
        </div>
        <dl class="divide-y divide-slate-200 text-sm">
            @foreach ($rows as $label => $value)
                @if ($value !== null && $value !== '')
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $value }}</dd>
                    </div>
                @endif
            @endforeach
            @if ($active)
                <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-slate-500">Paket</dt>
                    <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $periodLabel }}</dd>
                </div>
                @if ($active->started_at)
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-slate-500">Dönem başlangıcı</dt>
                        <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $active->started_at->format('d.m.Y') }}</dd>
                    </div>
                @endif
                <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-slate-500">Dönem bitişi</dt>
                    <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $active->expires_at?->format('d.m.Y') ?? 'Süresiz' }}</dd>
                </div>
            @elseif ($expired?->expires_at)
                <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-slate-500">Son ücretli bitiş</dt>
                    <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $expired->expires_at->format('d.m.Y') }}</dd>
                </div>
            @endif
            @if ($pending)
                <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-slate-500">Bekleyen sipariş</dt>
                    <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">
                        {{ $pending->subscription->order_number ?: 'Sipariş' }}
                        · {{ $pending->subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }}
                        · {{ number_format((float) $pending->subscription->price, 2, ',', '.') }} ₺
                        · {{ $pending->subscription->user?->name }}
                    </dd>
                </div>
            @endif
        </dl>
    </article>
@endsection
