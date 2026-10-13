@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-lg rounded-2xl bg-white p-8 text-center shadow-sm">
        <h1 class="text-xl font-bold text-slate-950">Bu rapor şu an kullanılamıyor</h1>
        <p class="mt-3 text-sm leading-relaxed text-slate-600">Ücretsiz kullanımda rapor ekranları kapalı. Bu kısıtın nasıl işleyeceği netleşene kadar raporu açamıyorsunuz.</p>
        <a href="{{ url('/dashboard') }}" class="mt-6 inline-flex rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Panele dön</a>
    </div>
@endsection
