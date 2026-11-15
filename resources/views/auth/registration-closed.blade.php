@extends('layouts.guest')
@section('title', 'Yeni üyelik')
@section('content')
    <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm">
        <h1 class="text-xl font-bold text-slate-950">Yeni üyelikler geçici olarak kapalı</h1>
        <p class="mt-3 text-sm text-slate-600">Mevcut hesabınızla giriş yapabilirsiniz.</p>
        <a href="{{ route('login') }}" class="mt-5 inline-block font-semibold text-emerald-700">Giriş Yap</a>
    </div>
@endsection
