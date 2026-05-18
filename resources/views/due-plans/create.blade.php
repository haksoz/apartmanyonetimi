@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Yeni Aidat Planı</h1>
        <p class="mt-1 text-sm text-slate-500">Yıllık aidat planı oluşturun.</p>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm max-w-2xl">
        <form method="POST" action="{{ route('due-plans.store') }}" class="space-y-5">
            @csrf
            @include('due-plans._form', ['plan' => null])
            <div class="flex gap-3 pt-2">
                <button type="submit" class="rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Kaydet</button>
                <a href="{{ route('due-plans.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">İptal</a>
            </div>
        </form>
    </div>
@endsection
