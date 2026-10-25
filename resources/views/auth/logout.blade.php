@extends('layouts.guest')

@section('title', 'Çıkış Yap')

@section('content')
    <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-950">Çıkış Yap</h1>
        <p class="mt-2 text-sm text-slate-500">Oturumunuzu kapatmak için aşağıdaki düğmeye basın.</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Çıkış Yap</button>
        </form>
    </div>
@endsection
