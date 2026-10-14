@extends('layouts.app')

@section('title', 'Hesabım')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Hesabım</h1>
        <p class="mt-1 text-sm text-slate-500">Ad, telefon ve parola size aittir. E-posta giriş adresinizdir.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="max-w-xl rounded-2xl bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-600" for="name">Ad Soyad</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-600">E-posta</label>
                <p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">{{ $user->email }}</p>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-600" for="phone">Telefon</label>
                <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('phone')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-slate-950 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800">Kaydet</button>
        </form>
    </div>

    <div class="mt-4 max-w-xl rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">Parola</h2>
        <p class="mt-1 text-sm text-slate-500">Mevcut parolanızı yazıp yeni bir parola belirleyin.</p>
        <form method="POST" action="{{ route('profile.password') }}" class="mt-5 space-y-5">
            @csrf
            @method('PATCH')
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-600" for="current_password">Mevcut parola</label>
                <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('current_password')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-600" for="password">Yeni parola</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('password')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-600" for="password_confirmation">Yeni parola tekrar</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
            </div>
            <button type="submit" class="rounded-xl bg-slate-950 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800">Parolayı kaydet</button>
        </form>
    </div>
@endsection
