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

    {{-- Filtreler --}}
    <form method="GET" action="{{ route('reports.debt-list') }}" class="mb-5 bg-white rounded-2xl border border-slate-200 p-4 flex flex-wrap gap-3 items-end">
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
            <label class="block text-xs text-slate-500 mb-1">Durum</label>
            <select name="status" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                <option value="unpaid" @selected($filterStatus === 'unpaid')>Ödenmemiş</option>
                <option value="overdue" @selected($filterStatus === 'overdue')>Gecikmiş</option>
                <option value="all" @selected($filterStatus === 'all')>Tümü</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Dönem</label>
            <input type="month" name="period" value="{{ $filterPeriod }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>
        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrele</button>
    </form>
    @endif

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
