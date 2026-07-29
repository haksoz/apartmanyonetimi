@extends('layouts.app')

@section('title', 'Tanımlamalar')

@section('content')
<div class="max-w-4xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Tanımlamalar</h1>
        <p class="mt-1 text-slate-500">Sistem genel ayarlarını yönetin.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-2xl bg-white border border-slate-200 p-6">
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Ücretsiz denemelerde kullanılacak paket türü</label>
                <select name="trial_package_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">-- Paket Seçin --</option>
                    @foreach($trialPackages as $package)
                        <option value="{{ $package->id }}" {{ $setting->trial_package_id == $package->id ? 'selected' : '' }}>
                            {{ $package->name }} ({{ $package->monthly_price > 0 ? number_format($package->monthly_price, 0, ',', '.') . ' TL/ay' : 'Ücretsiz' }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Yeni kayıt olan kullanıcıların ücretsiz deneme süresinde kullanacağı paket. Sadece deneme paketleri listelenir.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Ücretsiz Deneme Süresi (Ay)</label>
                <input type="number" name="trial_duration_months" value="{{ $setting->trial_duration_months ?? 2 }}" min="1" max="12" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <p class="mt-1 text-xs text-slate-500">Ücretsiz deneme süresinin kaç ay süreceğini belirtin.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Deneme Sonrası Paket</label>
                <select name="fallback_package_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">-- Paket Seçin --</option>
                    @foreach($packages as $package)
                        <option value="{{ $package->id }}" {{ $setting->fallback_package_id == $package->id ? 'selected' : '' }}>
                            {{ $package->name }} ({{ $package->monthly_price > 0 ? number_format($package->monthly_price, 0, ',', '.') . ' TL/ay' : 'Ücretsiz' }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Ücretsiz deneme süresi bitiminde kullanıcıya atanacak paket.</p>
            </div>

            <div class="pt-4 border-t border-slate-200">
                <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors">
                    Kaydet
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
