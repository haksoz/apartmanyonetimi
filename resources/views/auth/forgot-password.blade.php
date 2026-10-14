@extends('layouts.guest')

@section('title', 'Şifremi Unuttum')

@section('content')
    <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-950">Şifremi unuttum</h1>
        <p class="mt-2 text-sm text-slate-500">Kayıtlı e-posta adresinize şifre sıfırlama bağlantısı gönderilir.</p>

        @if (session('status'))
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="email" class="text-sm font-medium text-slate-700">E-posta</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                @error('email')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Bağlantı gönder</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            <a href="{{ route('login') }}" class="font-semibold text-slate-950">Girişe dön</a>
        </p>
    </div>
@endsection
