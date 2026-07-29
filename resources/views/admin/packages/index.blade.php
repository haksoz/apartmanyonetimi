@extends('layouts.app')

@section('title', 'Paketler')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Paketler</h1>
            <p class="text-sm text-slate-500">Üyelik paketlerini yönet.</p>
        </div>
        <a href="{{ route('admin.packages.create') }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Yeni Paket</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Paket</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Apartman Limiti</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Aylık Fiyat</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Yıllık Fiyat</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @foreach ($packages as $package)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $package->name }}</div>
                            <div class="text-xs text-slate-500">{{ $package->slug }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $package->apartment_limit }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ number_format($package->monthly_price, 2) }} ₺</td>
                        <td class="px-4 py-3 text-slate-700">{{ number_format($package->yearly_price, 2) }} ₺</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @if ($package->is_active)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Aktif</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">Pasif</span>
                                @endif
                                @if ($package->is_trial)
                                    <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">Deneme</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.packages.edit', $package) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Düzenle</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $packages->links() }}
    </div>
@endsection
