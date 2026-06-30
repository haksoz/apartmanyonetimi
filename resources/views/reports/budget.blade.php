@extends('layouts.app')

@section('content')
    @if(!isset($pdfMode))
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:text-slate-600">Raporlar</a>
                <span>/</span>
                <span class="text-slate-600">Bütçe Raporu</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Bütçe Raporu</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }} — {{ $year }}</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('reports.budget.export', ['type'=>'excel', 'year'=>$year]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel
            </a>
            <a href="{{ route('reports.budget.export', ['type'=>'pdf', 'year'=>$year]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
        </div>
    </div>

    {{-- Yıl Seçici --}}
    <form method="GET" action="{{ route('reports.budget') }}" class="mb-5 bg-white rounded-2xl border border-slate-200 p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-slate-500 mb-1">Yıl</label>
            <select name="year" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                @foreach($availableYears as $y)
                    <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Görüntüle</button>
    </form>
    @endif

    {{-- Bilgi Notu --}}
    <div class="mb-5 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-xs text-amber-700 flex items-start gap-2">
        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Bu rapor şu an yalnızca gerçekleşen giderleri göstermektedir. Bütçe tanımlaması özelliği ilerleyen sürümlerde eklenecektir.</span>
    </div>

    {{-- Özet --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500 mb-1">Toplam Gerçekleşen Gider</p>
            <p class="text-2xl font-bold text-red-500">{{ number_format($totalActual, 2, ',', '.') }} ₺</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500 mb-1">Kategori Sayısı</p>
            <p class="text-2xl font-bold text-slate-800">{{ $rows->count() }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Kategori</th>
                        <th class="px-4 py-3 text-right font-semibold">Gerçekleşen (₺)</th>
                        <th class="px-4 py-3 text-right font-semibold">Pay %</th>
                        <th class="px-4 py-3 text-left font-semibold w-48">Dağılım</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        @php $pct = $totalActual > 0 ? ($row['actual'] / $totalActual) * 100 : 0; @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $row['category'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ number_format($row['actual'], 2, ',', '.') }} ₺</td>
                            <td class="px-4 py-3 text-right text-slate-500">{{ number_format($pct, 1) }}%</td>
                            <td class="px-4 py-3">
                                <div class="w-full bg-slate-100 rounded-full h-2">
                                    <div class="bg-amber-400 h-2 rounded-full" style="width: {{ min($pct, 100) }}%"></div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Bu yıl için gider kaydı bulunamadı.</td></tr>
                    @endforelse
                </tbody>
                @if($rows->count())
                <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                    <tr>
                        <td class="px-4 py-3 font-bold text-slate-700">TOPLAM</td>
                        <td class="px-4 py-3 text-right font-bold text-red-500">{{ number_format($totalActual, 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-600">100%</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
