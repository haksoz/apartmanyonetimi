@extends('layouts.app')

@section('title', 'Yöneticilik kabulü')

@section('content')
    <div class="mx-auto max-w-xl">
        <h1 class="text-2xl font-bold text-slate-950">Yöneticilik kabulü</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $offer->apartment->name }} sizi yönetici olarak davet etti. Kabul edince bu apartman yönetim hakkınıza yazılır.</p>

        <form method="POST" action="{{ route('management-offers.accept', $offer) }}" class="mt-6 space-y-4 rounded-2xl bg-white p-6 shadow-sm">
            @csrf
            @if ($needsResidentData)
                @include('partials.accept-resident-data')
            @endif
            @error('accept')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
            <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Kabul et</button>
        </form>

        <form method="POST" action="{{ route('management-offers.reject', $offer) }}" class="mt-3">
            @csrf
            <button type="submit" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reddet, üye olarak kal</button>
        </form>
    </div>
@endsection
