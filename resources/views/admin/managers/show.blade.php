@extends('layouts.app')

@section('title', $manager->name)

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.managers.index') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Aboneliklere dön</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $manager->name }}</h1>
        <p class="text-sm text-slate-500">{{ $manager->email }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">Mevcut Abonelik</h2>
            @if ($manager->subscription)
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Paket</span><span class="font-medium text-slate-900">{{ $manager->subscription->package->name }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Dönem</span><span class="font-medium text-slate-900 capitalize">{{ $manager->subscription->period }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Fiyat</span><span class="font-medium text-slate-900">{{ number_format($manager->subscription->price, 2) }} ₺</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Başlangıç</span><span class="font-medium text-slate-900">{{ $manager->subscription->started_at->format('d.m.Y') }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Bitiş</span><span class="font-medium text-slate-900">{{ $manager->subscription->expires_at?->format('d.m.Y') ?? 'Süresiz' }}</span></div>
                </div>
            @else
                <p class="mt-4 text-sm text-slate-500">Aktif abonelik yok.</p>
            @endif

            <form method="POST" action="{{ route('admin.managers.subscription.update', $manager) }}" class="mt-6 space-y-4 border-t border-slate-100 pt-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-sm font-medium text-slate-700">Paket</label>
                    <select name="package_id" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                        @foreach ($packages as $package)
                            <option value="{{ $package->id }}" {{ $manager->subscription?->package_id == $package->id ? 'selected' : '' }}>{{ $package->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Dönem</label>
                    <select name="period" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                        <option value="monthly" {{ $manager->subscription?->period === 'monthly' ? 'selected' : '' }}>Aylık</option>
                        <option value="yearly" {{ $manager->subscription?->period === 'yearly' ? 'selected' : '' }}>Yıllık</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Fiyat (opsiyonel)</label>
                    <input type="number" step="0.01" name="price" value="{{ old('price', $manager->subscription?->price) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                </div>
                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Aboneliği Güncelle</button>
            </form>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">Apartman Kotası</h2>
            <p class="mt-2 text-sm text-slate-500">
                Mevcut: <strong>{{ $quota->currentCount($manager) }}</strong> / Limit: <strong>{{ $quota->maxFor($manager) ?? 'Sınırsız' }}</strong>
            </p>

            <form method="POST" action="{{ route('admin.managers.quota.update', $manager) }}" class="mt-4 space-y-4 border-t border-slate-100 pt-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-sm font-medium text-slate-700">Maksimum Apartman (override)</label>
                    <input type="number" name="max_apartments" value="{{ old('max_apartments', $manager->quotaOverride?->max_apartments ?? $quota->maxFor($manager)) }}" min="0" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                </div>
                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Kotayı Güncelle</button>
            </form>

            <div class="mt-6 border-t border-slate-100 pt-4">
                <form method="POST" action="{{ route('admin.impersonate.start', $manager) }}">
                    @csrf
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Bu Kullanıcı Olarak Giriş Yap</button>
                </form>
                <p class="mt-2 text-xs text-slate-500">Bu işlem, admin oturumunu koruyarak kullanıcının paneline tam yetkiyle geçiş yapmanızı sağlar.</p>
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Apartmanlar</h2>
        @if ($apartments->isEmpty())
            <p class="mt-4 text-sm text-slate-500">Henüz apartman yok.</p>
        @else
            <ul class="mt-4 divide-y divide-slate-100">
                @foreach ($apartments as $apartment)
                    <li class="py-3 flex items-center justify-between">
                        <div>
                            <div class="font-medium text-slate-900">{{ $apartment->name }}</div>
                            <div class="text-xs text-slate-500">Rol: {{ $apartment->pivot->role }} | {{ $apartment->pivot->is_active ? 'Aktif' : 'Pasif' }}</div>
                        </div>
                        <a href="{{ route('apartments.show', $apartment) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Görüntüle</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
