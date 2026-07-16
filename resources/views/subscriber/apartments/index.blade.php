@extends('layouts.app')

@section('title', 'Apartmanlarım')

@section('content')
    <div class="mb-6">
        <a href="{{ route('subscriber.dashboard') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Abone paneline dön</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Apartmanlarım</h1>
        <p class="text-sm text-slate-500">Yönetmek istediğiniz apartmanı seçin.</p>
    </div>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($apartments as $apartment)
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="text-lg font-semibold text-slate-900">{{ $apartment->name }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ $apartment->address ?: 'Adres girilmedi' }}</div>
                <div class="mt-2 text-sm text-slate-600">
                    Yönetici: <span class="font-medium">{{ $apartment->user?->name ?? 'Belirtilmemiş' }}</span>
                </div>
                <div class="mt-4 text-sm text-slate-500">{{ $apartment->unit_count }} daire</div>
                <div class="mt-5 flex gap-2">
                    <form method="POST" action="{{ route('subscriber.apartment.update') }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">
                        <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Seç</button>
                    </form>
                    <a href="{{ route('subscriber.apartments.edit', $apartment) }}" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
                    <form method="POST" action="{{ route('subscriber.apartments.trigger-aidat', $apartment) }}">
                        @csrf
                        <button type="submit" class="rounded-xl border border-emerald-600 px-4 py-3 text-sm font-semibold text-emerald-600 hover:bg-emerald-50">Aidat Tetikle</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6">
        <a href="{{ route('subscriber.apartments.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Yeni Apartman Oluştur</a>
    </div>
@endsection
