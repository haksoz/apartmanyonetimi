@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">{{ $apartment->name }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $apartment->address ?: 'Adres girilmedi' }}</p>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Daire Sayısı</div><div class="mt-2 text-2xl font-bold">{{ $apartment->units->count() }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Hesap Sayısı</div><div class="mt-2 text-2xl font-bold">{{ $apartment->accounts->count() }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Durum</div><div class="mt-2 text-2xl font-bold">{{ $apartment->is_active ? 'Aktif' : 'Pasif' }}</div></div>
    </div>
@endsection
