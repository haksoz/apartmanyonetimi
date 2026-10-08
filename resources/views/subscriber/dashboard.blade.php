@extends('layouts.app')

@section('title', 'Abone Paneli')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-950 sm:text-2xl">Abone Paneli</h1>
        <p class="mt-1 text-sm text-slate-500">Apartmanlarınızın kullanım durumu ve şu anda yapmanız gereken işlem burada.</p>
    </div>

    @if ($apartments->count() === 0)
        <div id="no-apartment-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
                        <svg class="h-8 w-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900">Apartman Ekleyin</h3>
                    <p class="mt-2 text-sm text-slate-600">Sistemi kullanmaya başlamak için lütfen bir apartman oluşturun.</p>
                    <div class="mt-6 flex justify-center gap-3">
                        <a href="{{ route('subscriber.apartments.create') }}" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Apartman Oluştur</a>
                        <button onclick="document.getElementById('no-apartment-modal').remove()" class="rounded-xl border border-slate-300 px-6 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Daha Sonra</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">Henüz apartman yok.</div>
    @else
        @include('subscriber.partials.apartment-cards', ['showDetails' => false])
    @endif
@endsection
