@extends('layouts.app')

@section('content')
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Dashboard</h1>
            <p class="mt-1 text-sm text-slate-500">
                @if ($apartment)
                    {{ $apartment->name }} apartmanı gelir, gider ve kasa durumuna hızlı bakış.
                @else
                    Apartman gelir, gider ve kasa durumuna hızlı bakış.
                @endif
            </p>
        </div>
        <a href="{{ route('apartments.create') }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Yeni Apartman</a>
    </div>

    @if (! $apartment)
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
            <h2 class="text-lg font-semibold text-slate-950">İlk apartmanınızı oluşturun</h2>
            <p class="mt-2 text-sm text-slate-500">Daire sayısını girince sistem hesapları otomatik oluşturacak.</p>
            <a href="{{ route('apartments.create') }}" class="mt-5 inline-flex rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Apartman Oluştur</a>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Seçili Apartman</div><div class="mt-2 text-2xl font-bold">{{ $stats['apartments'] }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Hesap</div><div class="mt-2 text-2xl font-bold">{{ $stats['accounts'] }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Aidat Tahakkuku</div><div class="mt-2 text-2xl font-bold">{{ number_format($stats['dues_total'], 2, ',', '.') }} TL</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Gider</div><div class="mt-2 text-2xl font-bold">{{ number_format($stats['expenses_total'], 2, ',', '.') }} TL</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Kasa</div><div class="mt-2 text-2xl font-bold">{{ number_format($stats['cash_balance'], 2, ',', '.') }} TL</div></div>
    </div>
@endsection
