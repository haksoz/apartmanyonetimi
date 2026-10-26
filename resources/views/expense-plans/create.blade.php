@extends('layouts.app')

@section('content')
    <div class="mb-6 flex min-w-0 items-start justify-between gap-3 sm:items-center">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-slate-950">
                <span class="inline-block"><a href="{{ route('financial-plans.index') }}" class="hover:text-slate-600">Finansal Planlama</a><span class="font-normal text-slate-400"> / </span></span>
                <span class="block text-lg font-semibold text-slate-800 sm:inline">Gider Planı Oluştur</span>
            </h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }} için düzenli gider planı oluşturun.</p>
        </div>
        <a href="{{ route('financial-plans.index') }}" aria-label="Finansal planlamaya geri dön" class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:px-4">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m7 7-7-7 7-7" /></svg>
            <span class="hidden sm:inline">Geri</span>
        </a>
    </div>

    <form method="POST" action="{{ route('expense-plans.store') }}" class="space-y-4">
        @csrf
        @include('expense-plans._form')
    </form>

    @include('expense-plans._supplier-modal')

@endsection
