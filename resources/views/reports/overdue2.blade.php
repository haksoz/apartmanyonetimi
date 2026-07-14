@extends('layouts.app')

@section('content')
    @if(!isset($pdfMode))
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:text-slate-600">Raporlar</a>
                <span>/</span>
                <span class="text-slate-600">Gecikme Raporu 2</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Gecikme Raporu 2</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }} — {{ now()->format('d.m.Y') }}</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('reports.overdue2.export', array_merge(['type'=>'excel'], request()->query())) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel
            </a>
            <a href="{{ route('reports.overdue2.export', array_merge(['type'=>'pdf'], request()->query())) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
        </div>
    </div>

    {{-- Filtreler --}}
    <form method="GET" action="{{ route('reports.overdue2') }}" class="mb-5 bg-white rounded-2xl border border-slate-200 p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-slate-500 mb-1">Daire</label>
            <select name="unit_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                <option value="">— Tüm Daireler —</option>
                @foreach($units as $unit)
                    <option value="{{ $unit->id }}" @selected($filterUnit == $unit->id)>{{ $unit->unit_no }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Hesaplar</label>
            <select name="account_filter" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                <option value="all" @selected($filterAccount === 'all')>Tümü</option>
                <option value="residents" @selected($filterAccount === 'residents')>Daire Sakinleri</option>
                <option value="owners" @selected($filterAccount === 'owners')>Kat Malikleri</option>
                <option value="inactive" @selected($filterAccount === 'inactive')>Pasif Hesaplar</option>
            </select>
        </div>
        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrele</button>
    </form>
    @endif

    {{-- Özet Kartlar --}}
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500 mb-1">Gecikmiş Hesap</p>
            <p class="text-2xl font-bold text-slate-800">{{ $groups->count() }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500 mb-1">Toplam Gecikmiş Tutar</p>
            <p class="text-2xl font-bold text-red-600">{{ number_format($totalOverdue, 2, ',', '.') }} ₺</p>
        </div>
    </div>

    @if($filterAccount !== 'all' || $filterUnit)
        @php
            $filterLabels = ['all' => 'Tümü', 'residents' => 'Daire Sakinleri', 'owners' => 'Kat Malikleri', 'inactive' => 'Pasif Hesaplar'];
            $activeFilters = [];
            if ($filterAccount !== 'all') {
                $activeFilters[] = 'Hesaplar: ' . ($filterLabels[$filterAccount] ?? $filterAccount);
            }
            if ($filterUnit) {
                $selectedUnit = $units->firstWhere('id', $filterUnit);
                $activeFilters[] = 'Daire: ' . ($selectedUnit?->unit_no ?? $filterUnit);
            }
        @endphp
        <div class="mb-4 bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3 text-sm text-indigo-800">
            <span class="font-semibold">Filtreler:</span>
            <span class="ml-1">{{ implode(' — ', $activeFilters) }}</span>
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Daire</th>
                        <th class="px-4 py-3 text-left font-semibold">Hesap Adı</th>
                        <th class="px-4 py-3 text-left font-semibold">Detaylar</th>
                        <th class="px-4 py-3 text-right font-semibold">Toplam Kalan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($groups as $group)
                        <tr class="hover:bg-slate-50 align-top">
                            <td class="px-4 py-3 font-medium text-slate-700 whitespace-nowrap">{{ $group->unit?->unit_no ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-800 whitespace-nowrap">{{ $group->account?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                <div class="flex flex-col gap-1">
                                    @foreach($group->dues as $due)
                                        <div class="text-xs">
                                            <span class="font-medium">{{ $due->created_at_manual?->format('d.m.Y') ?? $due->created_at?->format('d.m.Y') ?? '-' }}</span>
                                            <span class="mx-1 text-slate-300">|</span>
                                            {{ number_format($due->amount, 2, ',', '.') }} ₺
                                            <span class="mx-1 text-slate-300">|</span>
                                            <span class="font-medium">Açıklama:</span> {{ $due->description ?? '-' }}
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-red-600 whitespace-nowrap">{{ number_format($group->total_remaining, 2, ',', '.') }} ₺</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Gecikmiş hesap bulunamadı.</td></tr>
                    @endforelse
                </tbody>
                @if($groups->count())
                <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                    <tr>
                        <td colspan="3" class="px-4 py-3 font-bold text-slate-700">TOPLAM GECİKMİŞ</td>
                        <td class="px-4 py-3 text-right font-bold text-red-600">{{ number_format($totalOverdue, 2, ',', '.') }} ₺</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
