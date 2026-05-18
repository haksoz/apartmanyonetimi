@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-md mb-8 flex flex-col items-center gap-2">
        <img src="{{ asset('images/logo.png') }}" alt="AidatCep" class="h-12 w-auto">
        <div class="text-xl font-bold"><span class="text-emerald-600">Aidat</span><span class="text-slate-500">Cep</span></div>
        <div class="text-xs text-slate-500">Apartman Yönetim Sistemleri</div>
    </div>

    <div class="mx-auto max-w-md rounded-2xl bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-950">Giriş Yap</h1>
        <p class="mt-2 text-sm text-slate-500">Apartman yönetim paneline erişmek için giriş yapın.</p>

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="email" class="text-sm font-medium text-slate-700">E-posta</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                @error('email')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="password" class="text-sm font-medium text-slate-700">Şifre</label>
                <input id="password" name="password" type="password" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                @error('password')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input name="remember" type="checkbox" value="1" class="rounded border-slate-300">
                Beni hatırla
            </label>

            <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Giriş Yap</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Hesabınız yok mu?
            <a href="{{ route('register') }}" class="font-semibold text-slate-950">Üye olun</a>
        </p>
    </div>
@endsection
