@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Apartman</h1>
            <p class="mt-1 text-sm text-slate-500">Yönetilen apartman ve daire hesapları.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('apartments.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Yeni Apartman</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr><th class="px-5 py-3">Apartman</th><th class="px-5 py-3">Daire</th><th class="px-5 py-3">Hesap</th><th class="px-5 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($apartments as $apartment)
                    <tr>
                        <td class="px-5 py-4 font-medium text-slate-950">{{ $apartment->name }}</td>
                        <td class="px-5 py-4">{{ $apartment->units_count }}</td>
                        <td class="px-5 py-4">{{ $apartment->accounts_count }}</td>
                        <td class="px-5 py-4 text-right"><a href="{{ route('apartments.show', $apartment) }}" class="font-semibold text-slate-700 hover:text-slate-950">Detay</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">Henüz apartman yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
