@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-md rounded-2xl bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-950">Üye Ol</h1>
        <p class="mt-2 text-sm text-slate-500">Hesabınızı oluşturun, ardından ilk apartmanınızı ekleyin.</p>

        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="name" class="text-sm font-medium text-slate-700">Ad Soyad</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                @error('name')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="email" class="text-sm font-medium text-slate-700">E-posta</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                @error('email')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="password" class="text-sm font-medium text-slate-700">Şifre</label>
                <input id="password" name="password" type="password" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                @error('password')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="text-sm font-medium text-slate-700">Şifre Tekrar</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Üye Ol ve Apartman Ekle</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Zaten hesabınız var mı?
            <a href="{{ route('login') }}" class="font-semibold text-slate-950">Giriş yapın</a>
        </p>
    </div>
@endsection
