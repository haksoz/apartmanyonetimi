@extends('layouts.app')

@section('content')
    @if(!isset($pdfMode))
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:text-slate-600">Raporlar</a>
                <span>/</span>
                <span class="text-slate-600">Aidat Tahsilat Raporu</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Aidat Tahsilat Raporu</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }} — {{ $year }}</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('reports.due-collection.export', ['type'=>'excel', 'year'=>$year]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel
            </a>
            <a href="{{ route('reports.due-collection.export', ['type'=>'pdf', 'year'=>$year]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
        </div>
    </div>

    {{-- Yıl Seçici --}}
    <form method="GET" action="{{ route('reports.due-collection') }}" class="mb-5 bg-white rounded-2xl border border-slate-200 p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-slate-500 mb-1">Yıl</label>
            <select name="year" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                @foreach($availableYears as $y)
                    <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrele</button>
    </form>
    @endif

    {{-- Renk Açıklama --}}
    <div class="flex flex-wrap gap-3 mb-4 text-xs">
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-emerald-500 inline-block"></span> Ödendi</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-amber-400 inline-block"></span> Kısmi</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-red-500 inline-block"></span> Gecikmeli</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-blue-500 inline-block"></span> Bekliyor</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-slate-200 inline-block"></span> Yok</span>
    </div>

    {{-- Matris Tablosu --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="text-xs w-full">
                <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-3 py-3 text-left font-semibold sticky left-0 bg-slate-50 z-10 min-w-32">Daire / Hesap</th>
                        @foreach($monthNames as $mn)
                            <th class="px-2 py-3 text-center font-semibold min-w-14">{{ $mn }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($accounts as $account)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2.5 font-medium text-slate-700 sticky left-0 bg-white">
                                <div>{{ $account->unit?->unit_no }} - {{ $account->name }}</div>
                                <div class="text-[10px] text-slate-400">{{ $account->type_label }}</div>
                            </td>
                            @foreach($months as $m)
                                @php $status = $matrix[$account->id][$m] ?? null; @endphp
                                <td class="px-1 py-2.5 text-center">
                                    @if($status === 'paid')
                                        <span class="inline-flex items-center justify-center w-full">
                                            <span class="w-8 h-6 rounded bg-emerald-100 text-emerald-700 font-semibold flex items-center justify-center" title="Ödendi">✓</span>
                                        </span>
                                    @elseif($status === 'partial')
                                        <span class="inline-flex items-center justify-center w-full">
                                            <span class="w-8 h-6 rounded bg-amber-100 text-amber-700 font-semibold flex items-center justify-center" title="Kısmi">½</span>
                                        </span>
                                    @elseif($status === 'overdue')
                                        <span class="inline-flex items-center justify-center w-full">
                                            <span class="w-8 h-6 rounded bg-red-100 text-red-700 font-semibold flex items-center justify-center" title="Gecikmeli">!</span>
                                        </span>
                                    @elseif($status === 'pending')
                                        <span class="inline-flex items-center justify-center w-full">
                                            <span class="w-8 h-6 rounded bg-blue-100 text-blue-700 font-semibold flex items-center justify-center" title="Bekliyor">–</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center justify-center w-full">
                                            <span class="w-8 h-6 rounded bg-slate-100 text-slate-400 flex items-center justify-center" title="Yok">·</span>
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="13" class="px-4 py-8 text-center text-slate-400">Daire kaydı bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
