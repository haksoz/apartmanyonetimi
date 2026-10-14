@extends('layouts.guest')

@section('title', 'Yeni Şifre')

@section('content')
    <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-950">Yeni şifre</h1>
        <p class="mt-2 text-sm text-slate-500">Hesabınız için yeni bir şifre belirleyin.</p>

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label for="email" class="text-sm font-medium text-slate-700">E-posta</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autofocus class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                @error('email')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="password" class="text-sm font-medium text-slate-700">Yeni şifre</label>
                <input id="password" name="password" type="password" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                @error('password')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="text-sm font-medium text-slate-700">Yeni şifre tekrar</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
            </div>
            <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Şifreyi kaydet</button>
        </form>
    </div>
@endsection
