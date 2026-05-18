@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">{{ $apartment->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->address ?: 'Adres girilmedi' }}</p>
            @if ($apartment->managerUnit)
                <p class="mt-1 text-xs text-emerald-700 font-medium">
                    Yönetici: {{ str_pad($apartment->managerUnit->unit_no, 2, '0', STR_PAD_LEFT) }} No.lu Daire
                </p>
            @else
                <p class="mt-1 text-xs text-slate-400">Yönetici dışardan yönetiyor</p>
            @endif
        </div>
        <a href="{{ route('apartments.edit', $apartment) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 whitespace-nowrap">Düzenle</a>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Daire Sayısı</div><div class="mt-2 text-2xl font-bold">{{ $apartment->units->count() }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Hesap Sayısı</div><div class="mt-2 text-2xl font-bold">{{ $apartment->accounts->count() }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Durum</div><div class="mt-2 text-2xl font-bold">{{ $apartment->is_active ? 'Aktif' : 'Pasif' }}</div></div>
    </div>
@endsection
