@extends('layouts.app')

@section('title', 'Apartmanlarım')

@section('content')
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('subscriber.dashboard') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Abone paneline dön</a>
            <h1 class="mt-2 text-xl font-bold text-slate-950 sm:text-2xl">Apartmanlarım</h1>
            <p class="mt-1 text-sm text-slate-500">Apartman bilgilerini ve kullanım durumunu buradan kontrol edin.</p>
        </div>
        <a href="{{ route('subscriber.apartments.create') }}" class="inline-flex justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Yeni Apartman Oluştur</a>
    </div>

    <div class="space-y-4">
        @foreach ($apartments as $apartment)
            @php
                $active = $apartment->commercial_active;
                $pending = $apartment->commercial_pending;
                $expired = $apartment->commercial_expired;
                $periodLabel = $active?->subscription?->notes === 'Tanımlı süre'
                    ? 'Tanımlı süre'
                    : ($active?->subscription?->period === 'yearly' ? 'Yıllık' : ($active ? 'Aylık' : null));
                if ((int) $apartment->unit_count > 100 && ! $active && ! $pending) {
                    $statusLabel = 'Özel Teklif';
                } elseif ($pending && ! $active) {
                    $statusLabel = 'Ödeme Bekliyor';
                } elseif ($active) {
                    $statusLabel = 'Ücretli Kullanım';
                } elseif ($expired) {
                    $statusLabel = 'Ücretli Kullanım Sona Erdi';
                } else {
                    $statusLabel = 'Ücretsiz Kullanım';
                }
                $rows = [
                    'Apartman adı' => $apartment->name,
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
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-bold text-slate-950">{{ $apartment->name }}</h2>
                        <dl class="mt-4 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            @foreach ($rows as $label => $value)
                                @if ($value !== null && $value !== '')
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                                        <dd class="mt-1 text-sm text-slate-800">{{ $value }}</dd>
                                    </div>
                                @endif
                            @endforeach
                            @if ($active)
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Paket</dt>
                                    <dd class="mt-1 text-sm text-slate-800">{{ $periodLabel }}</dd>
                                </div>
                                @if ($active->started_at)
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Dönem başlangıcı</dt>
                                        <dd class="mt-1 text-sm text-slate-800">{{ $active->started_at->format('d.m.Y') }}</dd>
                                    </div>
                                @endif
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Dönem bitişi</dt>
                                    <dd class="mt-1 text-sm text-slate-800">{{ $active->expires_at?->format('d.m.Y') ?? 'Süresiz' }}</dd>
                                </div>
                            @elseif ($expired?->expires_at)
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Son ücretli bitiş</dt>
                                    <dd class="mt-1 text-sm text-slate-800">{{ $expired->expires_at->format('d.m.Y') }}</dd>
                                </div>
                            @endif
                            @if ($pending)
                                <div class="sm:col-span-2">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Bekleyen sipariş</dt>
                                    <dd class="mt-1 text-sm text-slate-800">
                                        {{ $pending->subscription->order_number ?: 'Sipariş' }}
                                        · {{ $pending->subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }}
                                        · {{ number_format((float) $pending->subscription->price, 2, ',', '.') }} ₺
                                        · {{ $pending->subscription->user?->name }}
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2 lg:flex-col">
                        <form method="POST" action="{{ route('subscriber.apartment.update') }}">
                            @csrf
                            <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">
                            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Apartmanı Yönet</button>
                        </form>
                        <a href="{{ route('subscriber.apartments.edit', $apartment) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Bilgileri Düzenle</a>
                        @if (\App\Support\FeatureGate::allows($apartment, 'auto_dues'))
                            <form method="POST" action="{{ route('subscriber.apartments.trigger-aidat', $apartment) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-xl border border-emerald-600 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Aidat Tetikle</button>
                            </form>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@endsection
