@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold text-slate-950">Yönetilecek Apartmanı Seçin</h1>
            <p class="mt-2 text-sm text-slate-500">Dashboard ve diğer işlemler seçtiğiniz apartman özelinde açılır.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($apartments as $apartment)
                <form method="POST" action="{{ route('current-apartment.update') }}" class="rounded-2xl bg-white p-5 shadow-sm">
                    @csrf
                    <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">
                    <div class="text-lg font-semibold text-slate-950">{{ $apartment->name }}</div>
                    <div class="mt-1 text-sm text-slate-500">{{ $apartment->address ?: 'Adres girilmedi' }}</div>
                    <div class="mt-2 text-sm text-slate-600">
                        Yönetici: <span class="font-medium">{{ $apartment->user?->name ?? 'Belirtilmemiş' }}</span>
                    </div>
                    <div class="mt-4 text-sm text-slate-500">{{ $apartment->unit_count }} daire</div>
                    <button type="submit" class="mt-5 w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Bu Apartmanla Devam Et</button>
                </form>
            @endforeach
        </div>

        <div class="mt-6 text-center">
            <a href="{{ route('apartments.create') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950">Yeni apartman oluştur</a>
        </div>
    </div>
@endsection
