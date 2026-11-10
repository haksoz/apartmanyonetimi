@extends('layouts.app')

@section('content')
    @php
        $modeQuery = ['month' => $month, 'month_end' => $monthEnd];
        $exportQuery = array_merge(['basis' => $basis], $modeQuery);
    @endphp
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:text-slate-600">Raporlar</a>
                <span>/</span>
                <span class="text-slate-600">Gider Raporu</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Gider Raporu</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }} — panoya asılmak veya dosyalanmak üzere</p>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ route('reports.expenses.export', array_merge(['type' => 'excel'], $exportQuery)) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg aria-hidden="true" class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel
            </a>
            <a href="{{ route('reports.expenses.export', array_merge(['type' => 'pdf'], $exportQuery)) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            @include('reports._back-button')
        </div>
    </div>

    <form method="GET" action="{{ route('reports.expenses') }}" id="expense-filter-form" class="mb-5 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <div>
            <label for="expense-report-basis" class="mb-1 block text-xs text-slate-500">Rapor Türü</label>
            <select name="basis" id="expense-report-basis" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-800">
                <option value="description" @selected($basis === 'description')>Açıklamalı Gider Raporu</option>
                <option value="category" @selected($basis === 'category')>Kategorili Gider Raporu</option>
            </select>
        </div>
        <div>
            <label id="board-period-label" class="mb-1 block text-xs text-slate-500">Dönem</label>
            <input type="hidden" name="period_year" id="board-year" value="{{ $parsedMonth->year }}">
            <input type="hidden" name="period_month" id="board-month" value="{{ $parsedMonth->month }}">
            <input type="hidden" name="month_end" id="board-month-end" value="{{ $monthEnd }}">
            <button type="button" id="board-period-open" aria-haspopup="dialog" aria-labelledby="board-period-label board-period-text"
                    class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-800 hover:bg-slate-50 sm:min-w-40">
                <span id="board-period-text">{{ $pickerLabel }}</span>
                <svg class="h-4 w-4 shrink-0 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v4m8-4v4M4 10h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg>
            </button>
        </div>
        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Görüntüle</button>
    </form>
    @include('reports._board-period-picker')

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 text-center">
            <h2 class="text-lg font-bold tracking-wide text-slate-900">{{ $basis === 'category' ? 'KATEGORİLİ GİDER RAPORU' : 'AÇIKLAMALI GİDER RAPORU' }}</h2>
            <p class="mt-1 text-sm font-medium text-slate-700">{{ $apartment->name }}</p>
            @if ($apartment->address)
                <p class="text-xs text-slate-500">{{ $apartment->address }}</p>
            @endif
            <p class="mt-1 text-sm text-slate-600">{{ $rangeLabel }}</p>
        </div>
        <div class="overflow-x-auto">
            @if ($basis === 'category')
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Kategori</th>
                            <th class="px-4 py-3 text-right font-semibold">Tutar</th>
                            <th class="px-4 py-3 text-right font-semibold">Pay %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($categories as $item)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $item['category'] }}</td>
                                <td class="px-4 py-3 text-right font-medium text-slate-800">{{ number_format($item['amount'], 2, ',', '.') }} ₺</td>
                                <td class="px-4 py-3 text-right text-slate-500">{{ $total > 0 ? number_format(($item['amount'] / $total) * 100, 1) : '0' }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-slate-400">Bu dönemde gider bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                        <tr>
                            <td class="px-4 py-3 font-bold text-slate-800">TOPLAM</td>
                            <td class="px-4 py-3 text-right font-bold text-red-600">{{ number_format($total, 2, ',', '.') }} ₺</td>
                            <td class="px-4 py-3 text-right font-bold text-slate-600">100%</td>
                        </tr>
                    </tfoot>
                </table>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Tarih</th>
                            <th class="px-4 py-3 text-left font-semibold">Açıklama</th>
                            <th class="px-4 py-3 text-right font-semibold">Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $item)
                            <tr>
                                <td class="px-4 py-3 text-slate-700">{{ $item['date'] }}</td>
                                <td class="px-4 py-3 text-slate-800">{{ $item['description'] }}</td>
                                <td class="px-4 py-3 text-right font-medium text-slate-800">{{ number_format($item['amount'], 2, ',', '.') }} ₺</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-slate-400">Bu dönemde gider bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-right font-bold text-slate-800">TOPLAM</td>
                            <td class="px-4 py-3 text-right font-bold text-red-600">{{ number_format($total, 2, ',', '.') }} ₺</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>
    </div>
@endsection
