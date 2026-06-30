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
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('reports.income-expense.export', ['type'=>'excel', 'date_from'=>$dateFrom, 'date_to'=>$dateTo]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel
            </a>
            <a href="{{ route('reports.income-expense.export', ['type'=>'pdf', 'date_from'=>$dateFrom, 'date_to'=>$dateTo]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
        </div>
    </div>

    {{-- Filtreler --}}
    <form method="GET" action="{{ route('reports.income-expense') }}" class="mb-5 bg-white rounded-2xl border border-slate-200 p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-slate-500 mb-1">Başlangıç Tarihi</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Bitiş Tarihi</label>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>
        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrele</button>
    </form>
    @endif

    {{-- Özet Kartlar --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500 mb-1">Toplam Tahsilat</p>
            <p class="text-2xl font-bold text-emerald-600">{{ number_format($totalIncome, 2, ',', '.') }} ₺</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500 mb-1">Toplam Gider</p>
            <p class="text-2xl font-bold text-red-500">{{ number_format($totalExpense, 2, ',', '.') }} ₺</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500 mb-1">Net</p>
            <p class="text-2xl font-bold {{ $totalNet >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                {{ number_format($totalNet, 2, ',', '.') }} ₺
            </p>
        </div>
    </div>

    {{-- Aylık Tablo --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700">Aylık Döküm</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Dönem</th>
                        <th class="px-4 py-3 text-right font-semibold">Tahsilat</th>
                        <th class="px-4 py-3 text-right font-semibold">Gider</th>
                        <th class="px-4 py-3 text-right font-semibold">Net</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $row['month'] }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600 font-medium">{{ number_format($row['income'], 2, ',', '.') }} ₺</td>
                            <td class="px-4 py-3 text-right text-red-500 font-medium">{{ number_format($row['expense'], 2, ',', '.') }} ₺</td>
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
                        <td class="px-4 py-3 text-right font-bold text-emerald-600">{{ number_format($totalIncome, 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3 text-right font-bold text-red-500">{{ number_format($totalExpense, 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3 text-right font-bold {{ $totalNet >= 0 ? 'text-emerald-600' : 'text-red-500' }}">{{ number_format($totalNet, 2, ',', '.') }} ₺</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if(isset($expenseByCategory) && $expenseByCategory->count())
    {{-- Kategori Bazlı Giderler --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700">Gider Kategorileri</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Kategori</th>
                        <th class="px-4 py-3 text-right font-semibold">Tutar</th>
                        <th class="px-4 py-3 text-right font-semibold">Pay %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($expenseByCategory as $cat => $total)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-700">{{ $cat }}</td>
                            <td class="px-4 py-3 text-right font-medium text-slate-800">{{ number_format($total, 2, ',', '.') }} ₺</td>
                            <td class="px-4 py-3 text-right text-slate-500">
                                {{ $totalExpense > 0 ? number_format(($total / $totalExpense) * 100, 1) : '0' }}%
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endsection
