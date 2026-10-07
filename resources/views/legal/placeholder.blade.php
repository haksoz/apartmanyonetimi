@extends('layouts.landing')

@section('title', $title.' — AidatCep')

@section('content')
    <header class="border-b border-slate-100 bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <a href="{{ route('landing') }}" class="flex items-center gap-2">
                <img src="{{ asset('images/logo.png') }}" alt="AidatCep" class="h-8 w-auto">
                <span class="text-lg font-bold">
                    <span style="color:#336633">Aidat</span><span class="text-slate-400">Cep</span>
                </span>
            </a>
            <a href="{{ route('landing') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Ana sayfa</a>
        </div>
    </header>

    <main class="px-4 sm:px-6 py-16">
        <div class="max-w-3xl mx-auto rounded-2xl border border-slate-200 bg-white p-8 sm:p-10">
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-950">{{ $title }}</h1>
            <div class="mt-4 text-slate-600 leading-relaxed">
                @include($body ?? 'legal.content.pending')
            </div>
        </div>
    </main>
@endsection
