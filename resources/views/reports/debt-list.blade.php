@extends('layouts.app')

@section('content')
    @if(!isset($pdfMode))
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:text-slate-600">Raporlar</a>
                <span>/</span>
                <span class="text-slate-600">Borç Listesi</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Borç Listesi</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }}</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('reports.debt-list.export', array_merge(['type'=>'excel'], request()->query())) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel
            </a>
            <a href="{{ route('reports.debt-list.export', array_merge(['type'=>'pdf'], request()->query())) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
        </div>
    </div>

    @php
        $statusLabels = ['unpaid' => 'Ödenmemiş', 'overdue' => 'Gecikmiş', 'all' => 'Tümü'];
        $activeUnit = $filterUnit ? $units->firstWhere('id', $filterUnit) : null;
        $activeFilterCount = 0;
        if ($filterUnit) $activeFilterCount++;
        if ($filterStatus !== 'unpaid') $activeFilterCount++;
        if ($filterStartDate || $filterEndDate) $activeFilterCount++;
    @endphp

    {{-- Filtre Butonu + Rozetler --}}
    <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <button type="button" onclick="openFilterModal()"
            class="flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.238 2.022l-3.158 1.579A2.25 2.25 0 018.25 20.05v-5.83a2.25 2.25 0 00-.659-1.591L2.659 7.197A2.25 2.25 0 012 5.606V4.562c0-.54.384-1.006.917-1.096A49.32 49.32 0 0112 3z"/>
            </svg>
            Filtrele
            @if ($activeFilterCount > 0)
                <span class="ml-1 flex h-5 w-5 items-center justify-center rounded-full bg-white text-xs font-bold text-slate-950">{{ $activeFilterCount }}</span>
            @endif
        </button>
    </div>

    @if ($activeFilterCount > 0)
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-slate-500">Aktif filtreler:</span>
            @if ($filterUnit && $activeUnit)
                <a href="{{ route('reports.debt-list', request()->except('unit_id')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    Daire: {{ $activeUnit->unit_no }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            @if ($filterStatus !== 'unpaid')
                <a href="{{ route('reports.debt-list', request()->except('status')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    {{ $statusLabels[$filterStatus] ?? $filterStatus }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            @if ($filterStartDate || $filterEndDate)
                <a href="{{ route('reports.debt-list', request()->except(['start_date', 'end_date'])) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    Vade: {{ $filterStartDate ? \Carbon\Carbon::parse($filterStartDate)->format('d.m.Y') : '...' }} - {{ $filterEndDate ? \Carbon\Carbon::parse($filterEndDate)->format('d.m.Y') : '...' }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            <a href="{{ route('reports.debt-list') }}" class="ml-1 text-xs font-medium text-slate-400 hover:text-slate-600 underline">Tümünü temizle</a>
        </div>
    @endif

    {{-- Filter Modal --}}
    <div id="filter-modal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-black/50 transition-opacity" onclick="closeFilterModal()"></div>
        <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl p-6 overflow-y-auto flex flex-col">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-bold text-slate-900">Filtrele</h2>
                <button type="button" onclick="closeFilterModal()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="filter-form" method="GET" action="{{ route('reports.debt-list') }}" class="flex flex-col flex-1">
                <div class="flex-1 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Daire</label>
                        <select name="unit_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 bg-white">
                            <option value="">Tüm Daireler</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" @selected($filterUnit == $unit->id)>{{ $unit->unit_no }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Durum</label>
                        <select name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 bg-white">
                            <option value="unpaid" @selected($filterStatus === 'unpaid')>Ödenmemiş</option>
                            <option value="overdue" @selected($filterStatus === 'overdue')>Gecikmiş</option>
                            <option value="all" @selected($filterStatus === 'all')>Tümü</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Başlangıç Vade Tarihi</label>
                        <input type="date" name="start_date" value="{{ $filterStartDate }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Bitiş Vade Tarihi</label>
                        <input type="date" name="end_date" value="{{ $filterEndDate ?: \Carbon\Carbon::now()->format('Y-m-d') }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="resetFilters()" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Temizle</button>
                    <button type="button" onclick="closeFilterModal()" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">İptal</button>
                    <button type="submit" class="ml-auto rounded-xl bg-slate-950 px-6 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Uygula</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <script>
        function openFilterModal() {
            document.getElementById('filter-modal').classList.remove('hidden');
        }
        function closeFilterModal() {
            document.getElementById('filter-modal').classList.add('hidden');
        }
        function resetFilters() {
            const form = document.getElementById('filter-form');
            form.querySelectorAll('select').forEach(el => el.value = '');
            form.querySelectorAll('input[type="date"]').forEach(el => el.value = '');
            form.submit();
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeFilterModal();
        });
    </script>

    {{-- Özet --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500 mb-1">Toplam Kayıt</p>
            <p class="text-2xl font-bold text-slate-800">{{ $dues->count() }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500 mb-1">Toplam Borç</p>
            <p class="text-2xl font-bold text-red-500">{{ number_format($total, 2, ',', '.') }} ₺</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Daire</th>
                        <th class="px-4 py-3 text-left font-semibold">Hesap Adı</th>
                        <th class="px-4 py-3 text-left font-semibold">Kategori</th>
                        <th class="px-4 py-3 text-left font-semibold">Dönem</th>
                        <th class="px-4 py-3 text-right font-semibold">Toplam</th>
                        <th class="px-4 py-3 text-right font-semibold">Kalan</th>
                        <th class="px-4 py-3 text-left font-semibold">Vade</th>
                        <th class="px-4 py-3 text-center font-semibold">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($dues as $due)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-700">{{ $due->unit?->unit_no ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-800">{{ $due->account?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $due->category?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $due->period ?? '-' }}</td>
                            <td class="px-4 py-3 text-right text-slate-800">{{ number_format($due->amount, 2, ',', '.') }} ₺</td>
                            <td class="px-4 py-3 text-right font-semibold text-red-600">{{ number_format($due->remaining_amount, 2, ',', '.') }} ₺</td>
                            <td class="px-4 py-3 text-slate-600">{{ $due->due_date?->format('d.m.Y') ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($due->due_date && $due->due_date->isPast())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Gecikmiş</span>
                                @elseif($due->remaining_amount < $due->amount)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Kısmi</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Bekliyor</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">Borç kaydı bulunamadı.</td></tr>
                    @endforelse
                </tbody>
                @if($dues->count())
                <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                    <tr>
                        <td colspan="5" class="px-4 py-3 font-bold text-slate-700">TOPLAM</td>
                        <td class="px-4 py-3 text-right font-bold text-red-600">{{ number_format($total, 2, ',', '.') }} ₺</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
