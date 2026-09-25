@extends('layouts.app')

@section('content')
    @php
        $exportQuery = array_filter([
            'basis' => $basis,
            'month' => $month,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
        $modeQuery = array_filter([
            'month' => $month,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
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
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('reports.expenses.export', array_merge(['type' => 'excel'], $exportQuery)) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Excel
            </a>
            <a href="{{ route('reports.expenses.export', array_merge(['type' => 'pdf'], $exportQuery)) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                PDF
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.expenses') }}" class="mb-5 bg-white rounded-2xl border border-slate-200 p-4 flex flex-wrap gap-3 items-end">
        <input type="hidden" name="basis" value="{{ $basis }}">
        <div>
            <label class="block text-xs text-slate-500 mb-1">Ay</label>
            <input type="month" name="month" value="{{ $month }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Başlangıç Tarihi</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Bitiş Tarihi</label>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrele</button>
        <p class="w-full text-xs text-slate-400">Ay seçildiğinde o ayın gider dönemi alınır. Tarih aralığı, ay boşken gider tarihine göre kullanılır.</p>
    </form>
    <script>
        (function () {
            const form = document.querySelector('form[action="{{ route('reports.expenses') }}"]');
            if (!form) return;
            const month = form.querySelector('[name="month"]');
            const dateFrom = form.querySelector('[name="date_from"]');
            const dateTo = form.querySelector('[name="date_to"]');
            function syncDates() {
                if (!month.value) return;
                const [year, mon] = month.value.split('-').map(Number);
                const last = String(new Date(year, mon, 0).getDate()).padStart(2, '0');
                dateFrom.value = month.value + '-01';
                dateTo.value = month.value + '-' + last;
            }
            month.addEventListener('change', syncDates);
            dateFrom.addEventListener('input', function () { month.value = ''; });
            dateTo.addEventListener('input', function () { month.value = ''; });
        })();
    </script>

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('reports.expenses', array_merge($modeQuery, ['basis' => 'description'])) }}"
           class="rounded-xl px-4 py-2 text-sm font-semibold {{ $basis === 'description' ? 'bg-slate-950 text-white' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }}">
            Açıklamalı Gider Raporu
        </a>
        <a href="{{ route('reports.expenses', array_merge($modeQuery, ['basis' => 'category'])) }}"
           class="rounded-xl px-4 py-2 text-sm font-semibold {{ $basis === 'category' ? 'bg-slate-950 text-white' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }}">
            Kategorili Gider Raporu
        </a>
    </div>

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
