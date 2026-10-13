@extends('layouts.app')

@section('title', 'Silinmiş apartmanlar')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.dashboard') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Admin paneline dön</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Silinmiş apartmanlar</h1>
        <p class="mt-1 text-sm text-slate-500">Yöneticinin sildiği apartmanlar pasiftir. Kayıtlar durur; yönetici bu apartmanları kendi ekranında görmez.</p>
    </div>

    @if ($apartments->isEmpty())
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">Silinmiş apartman yok.</div>
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Apartman</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Yönetici</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Daire</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Hesap</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Pasife alınma</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($apartments as $apartment)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $apartment->name }}</div>
                                <div class="text-xs text-slate-500">{{ $apartment->address ?: 'Adres girilmedi' }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $apartment->user?->name ?? 'Belirtilmemiş' }}</td>
                            <td class="px-4 py-3">{{ $apartment->units_count }}</td>
                            <td class="px-4 py-3">{{ $apartment->accounts_count }}</td>
                            <td class="px-4 py-3">{{ $apartment->updated_at?->format('d.m.Y H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('apartments.show', $apartment) }}" class="font-semibold text-emerald-600 hover:text-emerald-700">Görüntüle</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
