@extends('layouts.guest')

@section('title', 'Erişim izni yok')

@section('content')
    <section class="my-8 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm sm:my-16 sm:p-8" aria-labelledby="error-title">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10V7a4 4 0 018 0v3M7 10h10a2 2 0 012 2v7a2 2 0 01-2 2H7a2 2 0 01-2-2v-7a2 2 0 012-2zm5 5v2"/>
            </svg>
        </div>
        <p class="mt-5 text-sm font-semibold text-slate-400">403</p>
        <h1 id="error-title" class="mt-2 text-2xl font-bold text-slate-950">Bu sayfaya erişim izniniz yok</h1>
        <p class="mt-3 text-sm leading-6 text-slate-500">Mevcut hesabınızla bu sayfayı görüntüleyemezsiniz.</p>
        <a href="{{ route('landing') }}" class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">Ana sayfaya dön</a>
    </section>
@endsection
