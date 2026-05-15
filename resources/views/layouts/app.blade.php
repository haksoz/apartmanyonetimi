<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'KapitalOnline Apartman Yönetim Sistemi') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="min-h-screen">
        <aside class="fixed inset-y-0 left-0 hidden w-72 border-r border-slate-200 bg-white p-6 lg:block">
            <div class="mb-8">
                <div class="text-lg font-bold text-slate-950">KapitalOnline</div>
                <div class="text-sm text-slate-500">Apartman Yönetim Sistemi</div>
            </div>
            @auth
                <div class="mb-6 rounded-xl bg-slate-50 p-4 text-sm">
                    <div class="font-semibold text-slate-950">{{ auth()->user()->name }}</div>
                    <div class="text-slate-500">{{ auth()->user()->isAdmin() ? 'Süper Yönetici' : 'Apartman Yöneticisi' }}</div>
                </div>
                @if ($availableApartments->isNotEmpty())
                    <div class="mb-6 rounded-xl border border-slate-200 p-4 text-sm">
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Seçili Apartman</div>
                        @if ($availableApartments->count() > 1)
                            <form method="POST" action="{{ route('current-apartment.update') }}" class="space-y-3">
                                @csrf
                                <select name="apartment_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                                    @foreach ($availableApartments as $availableApartment)
                                        <option value="{{ $availableApartment->id }}" @selected($currentApartment?->id === $availableApartment->id)>{{ $availableApartment->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="w-full rounded-xl bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Apartman Değiştir</button>
                            </form>
                        @else
                            <div class="font-semibold text-slate-950">{{ $currentApartment?->name }}</div>
                            <div class="mt-1 text-xs text-slate-500">Dashboard bu apartmana göre çalışıyor.</div>
                        @endif
                    </div>
                @endif
                <nav class="space-y-2 text-sm font-medium">
                    <a href="{{ route('dashboard') }}" class="block rounded-xl px-4 py-3 hover:bg-slate-100">Dashboard</a>
                    <a href="{{ route('accounts.index') }}" class="block rounded-xl px-4 py-3 hover:bg-slate-100">Hesaplar</a>
                    <a href="{{ route('expenses.index') }}" class="block rounded-xl px-4 py-3 hover:bg-slate-100">Giderler</a>
                    <a href="{{ route('dues.index') }}" class="block rounded-xl px-4 py-3 hover:bg-slate-100">Aidatlar</a>
                    <a href="{{ route('payments.index') }}" class="block rounded-xl px-4 py-3 hover:bg-slate-100">Tahsilatlar</a>
                    <a href="{{ route('cash.index') }}" class="block rounded-xl px-4 py-3 hover:bg-slate-100">Kasa</a>
                    <a href="{{ route('ledger.index') }}" class="block rounded-xl px-4 py-3 hover:bg-slate-100">Muhasebe Hareketleri</a>
                    <a href="{{ route('apartments.index') }}" class="block rounded-xl px-4 py-3 hover:bg-slate-100">Apartman</a>
                    <a href="{{ route('units.index') }}" class="block rounded-xl px-4 py-3 hover:bg-slate-100">Daireler</a>
                </nav>
                <form method="POST" action="{{ route('logout') }}" class="mt-6">
                    @csrf
                    <button type="submit" class="w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-slate-500 hover:bg-slate-100">Çıkış Yap</button>
                </form>
            @endauth
        </aside>
        <main class="lg:pl-72">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                @if (session('status'))
                    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
