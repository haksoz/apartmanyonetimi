@extends('layouts.app')

@section('content')
    @php
        $trMonthsH = [1=>'Ocak',2=>'Şubat',3=>'Mart',4=>'Nisan',5=>'Mayıs',6=>'Haziran',7=>'Temmuz',8=>'Ağustos',9=>'Eylül',10=>'Ekim',11=>'Kasım',12=>'Aralık'];
    @endphp
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:text-slate-600">Raporlar</a>
                <span>/</span>
                <span class="text-slate-600">Aylık Borç Tablosu</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Aylık Borç Tablosu</h1>
            <p class="mt-1 text-sm text-slate-500">Aidat ve Kat Maliki borç tablolarını görüntüleyin</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('reports.monthly-board.export', ['type'=>'excel', 'month'=>$month, 'due_type'=>$reportDueType]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel
            </a>
            <a href="{{ route('reports.monthly-board.export', ['type'=>'pdf', 'month'=>$month, 'due_type'=>$reportDueType]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
        </div>
    </div>

    {{-- Tablo ve dönem seçimi --}}
    <form method="GET" action="{{ route('reports.monthly-board') }}" class="mb-5 grid grid-cols-[minmax(0,1fr)_auto] items-end gap-3 rounded-2xl border border-slate-200 bg-white p-3 sm:flex sm:flex-wrap sm:p-4">
        <div class="col-span-2 min-w-0 sm:col-span-1">
            <label for="board-audience" class="mb-1 block text-xs text-slate-500">Tablo</label>
            <select name="due_type" id="board-audience" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 sm:w-auto">
                <option value="aidat" @selected($reportDueType === 'aidat')>Aidat Borç Tablosu</option>
                <option value="malik_payi" @selected($reportDueType === 'malik_payi')>Kat Maliki Borç Tablosu</option>
            </select>
        </div>
        <div class="min-w-0">
            <label id="board-period-label" class="mb-1 block text-xs text-slate-500">Dönem</label>
            <input type="hidden" name="period_year" id="board-year" value="{{ $parsedMonth->year }}">
            <input type="hidden" name="period_month" id="board-month" value="{{ $parsedMonth->month }}">
            <button type="button" id="board-period-open" aria-haspopup="dialog" aria-labelledby="board-period-label board-period-text" class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-800 hover:bg-slate-50 sm:min-w-40">
                <span id="board-period-text">{{ $trMonths[$parsedMonth->month] }} {{ $parsedMonth->year }}</span>
                <svg class="h-4 w-4 shrink-0 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v4m8-4v4M4 10h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg>
            </button>
        </div>
        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Görüntüle</button>
    </form>
    @include('reports._board-period-picker')

    @php
        $totalBorç = 0;
        $totalÖdenen = 0;
        $pastRemainingAll = 0;
        $selectedAmountAll = 0;
        $remainingAll = 0;
        foreach($accounts as $account) {
            $data = $accountData[$account->id];
            $totalBorç   += $data['selectedAmount'];
            $totalÖdenen += $data['paid'];
            $pastRemainingAll += $data['pastRemaining'];
            $selectedAmountAll += $data['selectedAmount'];
            $remainingAll += $data['remaining'];
        }
    @endphp

    @if ($unknownDuesCount > 0)
        <p class="mb-4 text-xs text-slate-500">Seçili ay ve geçmiş açık borçlar içinde borç türü belirlenmemiş {{ $unknownDuesCount }} kayıt bu tablolara dahil edilmedi.</p>
    @endif

    {{-- Özet Satırı --}}
    @php
        $paidCount = 0;
        $pendingCount = 0;
        $partialCount = 0;
        foreach($accounts as $account) {
            $data = $accountData[$account->id];
            if($data['remaining'] == 0 && $data['selectedAmount'] + $data['pastRemaining'] > 0) $paidCount++;
            elseif($data['paid'] > 0) $partialCount++;
            elseif($data['remaining'] > 0) $pendingCount++;
        }
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <p class="text-xs text-slate-500 mb-1">Toplam Hesap</p>
            <p class="text-2xl font-bold text-slate-800">{{ $accounts->count() }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <p class="text-xs text-slate-500 mb-1">Ödedi</p>
            <p class="text-2xl font-bold text-emerald-600">{{ $paidCount }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <p class="text-xs text-slate-500 mb-1">Bekliyor / Kısmi</p>
            <p class="text-2xl font-bold text-amber-500">{{ $pendingCount + $partialCount }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <p class="text-xs text-slate-500 mb-1">Tahsilat Oranı</p>
            <p class="text-2xl font-bold text-slate-800">
                {{ $totalBorç + $pastRemainingAll > 0 ? number_format(($totalÖdenen / ($totalBorç + $pastRemainingAll)) * 100, 0) : 0 }}%
            </p>
        </div>
    </div>

    {{-- Pano Tablosu --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $subtitle }}</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Daire No</th>
                        <th class="px-4 py-3 text-left font-semibold">Hesap Adı</th>
                        <th class="px-4 py-3 text-right font-semibold">Geçmiş Borç (₺)</th>
                        <th class="px-4 py-3 text-right font-semibold">{{ $trMonthsH[$parsedMonth->month] }} Borç (₺)</th>
                        <th class="px-4 py-3 text-right font-semibold">Ödenen (₺)</th>
                        <th class="px-4 py-3 text-right font-semibold">Kalan (₺)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($accounts as $account)
                        @php $data = $accountData[$account->id]; @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-bold text-slate-800">{{ $account->unit?->unit_no }}</td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ $account->name }}
                                <span class="ml-1 text-[10px] text-slate-400">({{ $account->type_label }})</span>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ $data['pastRemaining'] > 0 ? number_format($data['pastRemaining'], 2, ',', '.') . ' ₺' : '—' }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ $data['selectedAmount'] > 0 ? number_format($data['selectedAmount'], 2, ',', '.') . ' ₺' : '—' }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600">{{ $data['paid'] > 0 ? number_format($data['paid'], 2, ',', '.') . ' ₺' : '—' }}</td>
                            <td class="px-4 py-3 text-right {{ $data['remaining'] > 0 ? 'text-red-500 font-semibold' : 'text-slate-400' }}">
                                {{ $data['remaining'] > 0 ? number_format($data['remaining'], 2, ',', '.') . ' ₺' : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Hesap kaydı bulunamadı.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                    <tr>
                        <td colspan="2" class="px-4 py-3 font-bold text-slate-700">TOPLAM</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-700">{{ $pastRemainingAll > 0 ? number_format($pastRemainingAll, 2, ',', '.') . ' ₺' : '—' }}</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-700">{{ $selectedAmountAll > 0 ? number_format($selectedAmountAll, 2, ',', '.') . ' ₺' : '—' }}</td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-600">{{ $totalÖdenen > 0 ? number_format($totalÖdenen, 2, ',', '.') . ' ₺' : '—' }}</td>
                        <td class="px-4 py-3 text-right font-bold text-red-500">{{ $remainingAll > 0 ? number_format($remainingAll, 2, ',', '.') . ' ₺' : '—' }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
