@extends('layouts.app')

@section('title', 'Müşteri Ekle')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.customers.index') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">← Müşterilere dön</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Müşteri ekle</h1>
        <p class="mt-1 text-sm text-slate-500">Hesap aktif olarak oluşturulur. Müşteri giriş yapıp apartmanını kurabilir.</p>
    </div>
    <form method="POST" action="{{ route('admin.customers.store') }}" class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
        @csrf
        <div>
            <label for="customer-name" class="block text-sm font-medium text-slate-700">Ad Soyad *</label>
            <input id="customer-name" name="name" maxlength="60" type="text" value="{{ old('name') }}" required autocomplete="name" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="customer-email" class="block text-sm font-medium text-slate-700">E-posta *</label>
            <input id="customer-email" name="email" maxlength="100" type="email" value="{{ old('email') }}" required autocomplete="email" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="customer-phone" class="block text-sm font-medium text-slate-700">Telefon *</label>
            <input id="customer-phone" required name="phone" type="tel" value="{{ old('phone') }}" inputmode="numeric" pattern="[1-9][0-9]{9}" maxlength="10" autocomplete="tel-national" placeholder="5551234567" aria-describedby="customer-phone-format" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
            <p id="customer-phone-format" class="mt-1 text-xs text-slate-500">Başında 0 olmadan 10 hane.</p>
            @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="customer-address" class="block text-sm font-medium text-slate-700">İletişim adresi *</label>
            <textarea id="customer-address" name="address" required maxlength="400" rows="3" autocomplete="street-address" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">{{ old('address') }}</textarea>
            @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="customer-quota" class="block text-sm font-medium text-slate-700">Apartman oluşturma limiti *</label>
            <input id="customer-quota" name="max_apartments" type="number" value="{{ old('max_apartments', 1) }}" min="1" max="1000" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
            @error('max_apartments')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="customer-password" class="block text-sm font-medium text-slate-700">Parola *</label>
                <input id="customer-password" name="password" type="password" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="customer-password-confirmation" class="block text-sm font-medium text-slate-700">Parola tekrar *</label>
                <input id="customer-password-confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-4 pt-2">
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Müşteriyi oluştur</button>
            <a href="{{ route('admin.customers.index') }}" class="text-sm font-semibold text-slate-600">Vazgeç</a>
        </div>
    </form>
@endsection
