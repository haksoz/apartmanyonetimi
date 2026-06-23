@extends('layouts.guest')

@section('title', 'Üye Ol')

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

            <div>
                <label class="text-sm font-medium text-slate-700">Paket</label>
                <div class="mt-2 space-y-2">
                    @foreach ($packages as $package)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50 {{ old('package_id') == $package->id ? 'border-emerald-300 bg-emerald-50' : '' }}">
                            <input type="radio" name="package_id" value="{{ $package->id }}" {{ old('package_id') == $package->id ? 'checked' : ($loop->first ? 'checked' : '') }} required class="mt-1 rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <div class="font-semibold text-slate-900">{{ $package->name }}</div>
                                <div class="text-xs text-slate-500">{{ $package->description }}</div>
                                <div class="mt-1 text-xs font-medium text-slate-700">{{ $package->apartment_limit }} apartman</div>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('package_id')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Ödeme Periyodu</label>
                <div class="mt-2 flex gap-3">
                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 {{ old('period') == 'monthly' ? 'border-emerald-300 bg-emerald-50' : '' }}">
                        <input type="radio" name="period" value="monthly" {{ old('period', 'monthly') == 'monthly' ? 'checked' : '' }} required class="rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm font-medium text-slate-700">Aylık</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 {{ old('period') == 'yearly' ? 'border-emerald-300 bg-emerald-50' : '' }}">
                        <input type="radio" name="period" value="yearly" {{ old('period') == 'yearly' ? 'checked' : '' }} required class="rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm font-medium text-slate-700">Yıllık</span>
                    </label>
                </div>
                @error('period')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Üye Ol ve Apartman Ekle</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Zaten hesabınız var mı?
            <a href="{{ route('login') }}" class="font-semibold text-slate-950">Giriş yapın</a>
        </p>
    </div>
@endsection
