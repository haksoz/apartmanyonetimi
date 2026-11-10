@extends('layouts.app')

@section('content')
    @if(!isset($pdfMode))
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:text-slate-600">Raporlar</a>
                <span>/</span>
                <span class="text-slate-600">Gelir-Gider Raporu</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Gelir-Gider Raporu</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }}</p>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ route('reports.income-expense.export', ['type'=>'excel', 'month'=>$month, 'month_end'=>$monthEnd]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel
            </a>
            <a href="{{ route('reports.income-expense.export', ['type'=>'pdf', 'month'=>$month, 'month_end'=>$monthEnd]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            @include('reports._back-button')
        </div>
    </div>

    <form method="GET" action="{{ route('reports.income-expense') }}" id="income-expense-filter-form" class="mb-5 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4">
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
    @endif

    <p class="mb-4 text-sm font-medium text-slate-600">{{ $rangeLabel }}</p>

    {{-- Özet Kartlar --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500 mb-1">Devir Bakiyesi</p>
            <p class="text-2xl font-bold {{ $openingBalance >= 0 ? 'text-emerald-600' : 'text-red-500' }}">{{ number_format($openingBalance, 2, ',', '.') }} ₺</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500 mb-1">Toplam Gelir</p>
            <p class="text-2xl font-bold text-emerald-600">{{ number_format($totalIncome, 2, ',', '.') }} ₺</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500 mb-1">Toplam Gider</p>
            <p class="text-2xl font-bold text-red-500">{{ number_format($totalExpense, 2, ',', '.') }} ₺</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500 mb-1">Dönem Sonu Bakiyesi</p>
            <p class="text-2xl font-bold {{ $closingBalance >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                {{ number_format($closingBalance, 2, ',', '.') }} ₺
            </p>
        </div>
    </div>

    {{-- Aylık Tablo --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700">Aylık Döküm</h2>
            <p class="text-xs text-slate-400 mt-1">
                <strong>Not:</strong> Gelir, tedarikçi ödemeleri hariç tüm ödemelerin toplamıdır. Tahsis durumuna bakılmaksızın hesaplanır.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Dönem</th>
                        <th class="px-4 py-3 text-right font-semibold">Gider</th>
                        <th class="px-4 py-3 text-right font-semibold">Gelir</th>
                        <th class="px-4 py-3 text-right font-semibold">Net</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $row['month'] }}</td>
                            <td class="px-4 py-3 text-right text-red-500 font-medium">{{ number_format($row['expense'], 2, ',', '.') }} ₺</td>
                            <td class="px-4 py-3 text-right text-emerald-600 font-medium">{{ number_format($row['income'], 2, ',', '.') }} ₺</td>
                            <td class="px-4 py-3 text-right font-semibold {{ $row['net'] >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                                {{ number_format($row['net'], 2, ',', '.') }} ₺
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Bu dönemde kayıt bulunamadı.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                    <tr>
                        <td class="px-4 py-3 font-bold text-slate-700">TOPLAM</td>
                        <td class="px-4 py-3 text-right font-bold text-red-500">{{ number_format($totalExpense, 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-600">{{ number_format($totalIncome, 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3 text-right font-bold {{ $totalNet >= 0 ? 'text-emerald-600' : 'text-red-500' }}">{{ number_format($totalNet, 2, ',', '.') }} ₺</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @include('reports._income-expense-history')

    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-700">Gelir Kırılımı</h2>
        <div class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
            <div><span class="text-slate-500">Aidat:</span> <strong>{{ number_format($aidatIncome, 2, ',', '.') }} ₺</strong></div>
            <div><span class="text-slate-500">Kat Maliki Payı:</span> <strong>{{ number_format($ownerShareIncome, 2, ',', '.') }} ₺</strong></div>
            <div><span class="text-slate-500">Borca bağlanmamış / diğer:</span> <strong>{{ number_format($otherIncome, 2, ',', '.') }} ₺</strong></div>
        </div>
    </div>

@endsection
