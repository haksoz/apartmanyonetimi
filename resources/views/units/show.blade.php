@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">{{ str_pad($unit->unit_no, 2, '0', STR_PAD_LEFT) }} No.lu Daire</h1>
            <p class="mt-1 text-sm text-slate-500">
                @if($unit->floor || $unit->block)
                    {{ $unit->floor }}{{ $unit->floor && $unit->block ? ' / ' : '' }}{{ $unit->block }}
                @endif
                @if($unit->square_meters)
                    | {{ number_format($unit->square_meters, 2, ',', '.') }} m²
                @endif
                @if($unit->share_coefficient)
                    | Pay: {{ number_format($unit->share_coefficient, 4, ',', '.') }}
                @endif
            </p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('units.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Listeye Dön</a>
            <a href="{{ route('units.edit', $unit) }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Düzenle</a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Current Owner --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-800">Mevcut Kat Maliki</h2>
            @if($unit->ownerAccount)
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Ad Soyad</span>
                        <a href="{{ route('accounts.show', $unit->ownerAccount) }}" class="font-medium text-emerald-600 hover:underline">{{ $unit->ownerAccount->name }}</a>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Telefon</span>
                        <span class="font-medium text-slate-700">{{ $unit->ownerAccount->phone ?: '-' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">E-posta</span>
                        <span class="font-medium text-slate-700">{{ $unit->ownerAccount->email ?: '-' }}</span>
                    </div>
                </div>
            @else
                <p class="text-slate-500">Henüz kat maliki atanmamış.</p>
            @endif
        </div>

        {{-- Current Tenant --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-800">Mevcut Kiracı</h2>
            @if($unit->occupantAccount)
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Ad Soyad</span>
                        <a href="{{ route('accounts.show', $unit->occupantAccount) }}" class="font-medium text-emerald-600 hover:underline">{{ $unit->occupantAccount->name }}</a>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Telefon</span>
                        <span class="font-medium text-slate-700">{{ $unit->occupantAccount->phone ?: '-' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">E-posta</span>
                        <span class="font-medium text-slate-700">{{ $unit->occupantAccount->email ?: '-' }}</span>
                    </div>
                </div>
            @else
                <p class="text-slate-500">Henüz kiracı atanmamış.</p>
            @endif
        </div>
    </div>

    {{-- Owner History --}}
    <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-slate-800">Kat Maliki Geçmişi</h2>
        @if($unit->ownerHistories->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Kat Maliki</th>
                            <th class="px-4 py-3">Başlangıç</th>
                            <th class="px-4 py-3">Bitiş</th>
                            <th class="px-4 py-3">Notlar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($unit->ownerHistories as $history)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-700">
                                    <a href="{{ route('accounts.show', $history->account) }}" class="text-emerald-600 hover:underline">{{ $history->account->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $history->start_date->format('d.m.Y') }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $history->end_date ? $history->end_date->format('d.m.Y') : 'Devam ediyor' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $history->notes ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-slate-500">Henüz kat maliki geçmişi kaydedilmemiş.</p>
        @endif
    </div>

    {{-- Tenant History --}}
    <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-slate-800">Kiracı Geçmişi</h2>
        @if($unit->tenantAssignments->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Kiracı</th>
                            <th class="px-4 py-3">Giriş Tarihi</th>
                            <th class="px-4 py-3">Çıkış Tarihi</th>
                            <th class="px-4 py-3">Durum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($unit->tenantAssignments as $assignment)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-700">
                                    <a href="{{ route('accounts.show', $assignment->account) }}" class="text-emerald-600 hover:underline">{{ $assignment->account->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $assignment->move_in_date->format('d.m.Y') }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $assignment->move_out_date ? $assignment->move_out_date->format('d.m.Y') : '-' }}</td>
                                <td class="px-4 py-3">
                                    @if($assignment->move_out_date)
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">Çıkış Yapmış</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-700">Aktif</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-slate-500">Henüz kiracı geçmişi kaydedilmemiş.</p>
        @endif
    </div>
@endsection
