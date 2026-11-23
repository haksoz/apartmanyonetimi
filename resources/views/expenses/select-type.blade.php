@extends('layouts.app')

@section('content')
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-950">Gider Ekle</h1>
        <p class="mt-2 text-sm text-slate-500">Devam etmek için gider türünü seçin.</p>
        <a href="{{ request()->fullUrl() }}" class="mt-4 inline-block rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white">Gider Türünü Seçin</a>
    </div>
@endsection
