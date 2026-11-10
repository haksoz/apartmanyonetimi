@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:text-slate-600">Raporlar</a>
                <span>/</span>
                <span class="text-slate-600">Aylık Borç Tablosu</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Aylık Borç Tablosu</h1>
            <p class="mt-1 text-sm text-slate-500">Seçilen ayların borçlarını ve ödeme durumunu bugün itibarıyla görüntüleyin.</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('reports.monthly-board.export', ['type'=>'excel', 'month'=>$month, 'month_end'=>$monthEnd, 'due_type'=>$reportDueType, 'hide_account_type'=>(int) $hideAccountType, 'hide_account_names'=>(int) $hideAccountNames, 'only_period_residents'=>(int) $onlyPeriodResidents]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel
            </a>
            <a href="{{ route('reports.monthly-board.export', ['type'=>'pdf', 'month'=>$month, 'month_end'=>$monthEnd, 'due_type'=>$reportDueType, 'hide_account_type'=>(int) $hideAccountType, 'hide_account_names'=>(int) $hideAccountNames, 'only_period_residents'=>(int) $onlyPeriodResidents]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.monthly-board') }}" id="board-filter-form" class="mb-5">
{{-- Tablo ve dönem seçimi --}}
        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-3 rounded-2xl border border-slate-200 bg-white p-3 sm:flex sm:flex-wrap sm:p-4">
            <div class="col-span-2 min-w-0 sm:col-span-1">
                <label for="board-audience" class="mb-1 block text-xs text-slate-500">Tablo</label>
                <select name="due_type" id="board-audience" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 sm:w-auto">
                    <option value="aidat" @selected($reportDueType === 'aidat')>Aidat Borç Tablosu</option>
                    <option value="malik_payi" @selected($reportDueType === 'malik_payi')>Kat Maliki Borç Tablosu</option>
                </select>
            </div>
            <div id="board-period-control" class="min-w-0 transition-opacity">
                <label id="board-period-label" class="mb-1 block text-xs text-slate-500">Dönem</label>
                <input type="hidden" name="month_end" id="board-month-end" value="{{ $monthEnd }}">
                <input type="hidden" name="period_year" id="board-year" value="{{ $parsedMonth->year }}">
                <input type="hidden" name="period_month" id="board-month" value="{{ $parsedMonth->month }}">
                <button type="button" id="board-period-open" aria-haspopup="dialog" aria-labelledby="board-period-label board-period-text"
                        class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-800 hover:bg-slate-50 disabled:cursor-not-allowed disabled:bg-slate-100 sm:min-w-40">
                    <span id="board-period-text">{{ $rangeLabel }}</span>
                    <svg class="h-4 w-4 shrink-0 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v4m8-4v4M4 10h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg>
                </button>
            </div>
            <label class="col-span-2 flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 sm:col-span-1">
                <input type="hidden" name="hide_account_type" value="0">
                <input type="checkbox" name="hide_account_type" value="1" @checked($hideAccountType)
                       class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-400">
                <span>Hesap türünü gizle</span>
            </label>
            <label class="col-span-2 flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 sm:col-span-1">
                <input type="hidden" name="hide_account_names" value="0">
                <input type="checkbox" name="hide_account_names" value="1" @checked($hideAccountNames)
                       class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-400">
                <span>Hesap adlarını gizle</span>
            </label>
            @if($reportDueType === 'aidat')
                <label class="col-span-2 flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 sm:col-span-1">
                    <input type="hidden" name="only_period_residents" value="0">
                    <input type="checkbox" name="only_period_residents" value="1" @checked($onlyPeriodResidents)
                           class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-400">
                    <span>Sadece Dönem Sakinleri</span>
                </label>
            @endif
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Görüntüle</button>
        </div>
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
        <p class="mb-4 text-xs text-slate-500">Seçili dönem ve geçmiş açık borçlar içinde borç türü belirlenmemiş {{ $unknownDuesCount }} kayıt bu tablolara dahil edilmedi.</p>
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
                @php $collectionBase = $totalBorç; @endphp
                {{ $collectionBase > 0 ? number_format(($totalÖdenen / $collectionBase) * 100, 0) : 0 }}%
            </p>
        </div>
    </div>

    {{-- Pano Tablosu --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-slate-950">{{ $title }}</h2>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Daire No</th>
                        @unless($hideAccountNames)
                            <th class="px-4 py-3 text-left font-semibold">Hesap Adı</th>
                        @endunless
                        <th class="px-4 py-3 text-right font-semibold">Geçmiş Borç (₺)</th>
                        @foreach($reportMonths as $key)
                            <th class="px-4 py-3 text-right font-semibold">{{ $trMonths[(int) substr($key, 5, 2)] }} {{ substr($key, 0, 4) }}</th>
                        @endforeach
                        <th class="px-4 py-3 text-right font-semibold">TOPLAM<br>BORÇ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($accounts as $account)
                        @php $data = $accountData[$account->id]; @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-bold text-slate-800">
                                {{ $account->unit?->unit_no }}
                                @unless($hideAccountType)
                                    <span class="ml-1 text-[10px] font-normal text-slate-500">({{ $account->type_label }})</span>
                                @endunless
                            </td>
                            @unless($hideAccountNames)
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $account->name }}
                                </td>
                            @endunless
                            <td class="px-4 py-3 text-right text-slate-700">{{ $data['pastRemaining'] > 0 ? number_format($data['pastRemaining'], 2, ',', '.') . ' ₺' : '—' }}</td>
                            @foreach($reportMonths as $key)
                                @php $cell = $monthlyData[$account->id][$key]; @endphp
                                <td class="px-4 py-3 text-right align-top whitespace-nowrap tabular-nums">
                                    @if($cell['amount'] > 0)
                                        <span class="block text-slate-700">{{ number_format($cell['amount'], 2, ',', '.') }} ₺</span>
                                        <span class="mt-1 block text-[11px] leading-tight {{ $cell['remaining'] <= 0 ? 'text-emerald-600' : ($cell['paid'] > 0 ? 'text-amber-600' : 'text-red-500') }}">({{ $cell['status'] }})</span>
                                    @else — @endif
                                </td>
                            @endforeach
                            <td class="px-4 py-3 text-right align-top">
                                <span class="inline-block whitespace-nowrap rounded-lg border px-2.5 py-1 text-[15px] font-semibold tabular-nums {{ $data['remaining'] > 0 ? 'border-red-200 bg-red-50 text-red-600' : 'border-slate-200 bg-slate-50 text-slate-400' }}">{{ $data['remaining'] > 0 ? number_format($data['remaining'], 2, ',', '.') . ' ₺' : '—' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($reportMonths) + ($hideAccountNames ? 3 : 4) }}" class="px-4 py-8 text-center text-slate-400">Hesap kaydı bulunamadı.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                    <tr>
                        <td colspan="{{ $hideAccountNames ? 1 : 2 }}" class="px-4 py-3 font-bold text-slate-700">TOPLAM</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-700">{{ $pastRemainingAll > 0 ? number_format($pastRemainingAll, 2, ',', '.') . ' ₺' : '—' }}</td>
                        @foreach($reportMonths as $key)
                            @php $monthTotal = array_sum(array_map(fn ($item) => $item[$key]['amount'], $monthlyData)); @endphp
                            <td class="px-4 py-3 text-right font-bold">{{ $monthTotal > 0 ? number_format($monthTotal, 2, ',', '.').' ₺' : '—' }}</td>
                        @endforeach
                        <td class="px-4 py-3 text-right"><span class="inline-block whitespace-nowrap rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-[15px] font-bold tabular-nums text-red-600">{{ $remainingAll > 0 ? number_format($remainingAll, 2, ',', '.') . ' ₺' : '—' }}</span></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
