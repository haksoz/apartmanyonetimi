@extends('layouts.app')

@section('title', 'Apartmanlarım')

@section('content')
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-950 sm:text-2xl">Apartmanlarım</h1>
            <p class="mt-1 text-sm text-slate-500">Apartmanlarınızın kullanım durumu ve şu anda yapmanız gereken işlem burada.</p>
        </div>
        <a href="{{ route('subscriber.apartments.create') }}" class="inline-flex justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Yeni Apartman Oluştur</a>
    </div>

    @include('subscriber.partials.apartment-cards', ['showDetails' => true])
@endsection
