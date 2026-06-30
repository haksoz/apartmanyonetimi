@extends('layouts.app')

@section('content')
    @if(!isset($pdfMode))
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:text-slate-600">Raporlar</a>
                <span>/</span>
                <span class="text-slate-600">Aylık Aidat Pano Tablosu</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Aylık Aidat Pano Tablosu</h1>
            @php $trMonthsH = [1=>'Ocak',2=>'Şubat',3=>'Mart',4=>'Nisan',5=>'Mayıs',6=>'Haziran',7=>'Temmuz',8=>'Ağustos',9=>'Eylül',10=>'Ekim',11=>'Kasım',12=>'Aralık']; @endphp
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }} — {{ $trMonthsH[$parsedMonth->month] }} {{ $parsedMonth->year }}</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('reports.monthly-board.export', ['type'=>'excel', 'month'=>$month]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel
            </a>
            <a href="{{ route('reports.monthly-board.export', ['type'=>'pdf', 'month'=>$month]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
        </div>
    </div>

    {{-- Ay Seçici --}}
    <form method="GET" action="{{ route('reports.monthly-board') }}" class="mb-5 bg-white rounded-2xl border border-slate-200 p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-slate-500 mb-1">Ay</label>
            <select name="month" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                @php
                    $trMonths = [1=>'Ocak',2=>'Şubat',3=>'Mart',4=>'Nisan',5=>'Mayıs',6=>'Haziran',7=>'Temmuz',8=>'Ağustos',9=>'Eylül',10=>'Ekim',11=>'Kasım',12=>'Aralık'];
                @endphp
                @foreach($monthOptions as $mo)
                    @php $d = \Carbon\Carbon::createFromFormat('Y-m', $mo); @endphp
                    <option value="{{ $mo }}" @selected($mo === $month)>
                        {{ $trMonths[$d->month] }} {{ $d->year }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Görüntüle</button>
    </form>
    @endif

    @php
        $totalBorç = 0;
        $totalÖdenen = 0;
        $paidCount = 0;
        $pendingCount = 0;
        $partialCount = 0;
    @endphp

    {{-- Özet Satırı --}}
    @php
        foreach($units as $unit) {
            $unitDues = $dues[$unit->id] ?? collect();
            $total    = $unitDues->sum('amount');
            $rem      = $unitDues->sum('remaining_amount');
            $paid     = $total - $rem;
            $totalBorç   += $total;
            $totalÖdenen += $paid;
            if($rem == 0 && $total > 0) $paidCount++;
            elseif($paid > 0) $partialCount++;
            elseif($total > 0) $pendingCount++;
        }
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <p class="text-xs text-slate-500 mb-1">Toplam Daire</p>
            <p class="text-2xl font-bold text-slate-800">{{ $units->count() }}</p>
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
                {{ $totalBorç > 0 ? number_format(($totalÖdenen / $totalBorç) * 100, 0) : 0 }}%
            </p>
        </div>
    </div>

    {{-- Pano Tablosu --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">
                {{ $apartment->name }} — {{ $trMonthsH[$parsedMonth->month] }} {{ $parsedMonth->year }} Aidat Durumu
            </h2>
            <span class="text-xs text-slate-400">{{ now()->format('d.m.Y') }} tarihli çıktı</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Daire No</th>
                        <th class="px-4 py-3 text-left font-semibold">Hesap Adı</th>
                        <th class="px-4 py-3 text-right font-semibold">Borç (₺)</th>
                        <th class="px-4 py-3 text-right font-semibold">Ödenen (₺)</th>
                        <th class="px-4 py-3 text-right font-semibold">Kalan (₺)</th>
                        <th class="px-4 py-3 text-center font-semibold">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($units as $unit)
                        @php
                            $unitDues = $dues[$unit->id] ?? collect();
                            $total    = (float) $unitDues->sum('amount');
                            $rem      = (float) $unitDues->sum('remaining_amount');
                            $paid     = $total - $rem;
                            $account  = $unit->accounts->first();
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-bold text-slate-800">{{ $unit->unit_no }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $account?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ $total > 0 ? number_format($total, 2, ',', '.') . ' ₺' : '—' }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600">{{ $paid > 0 ? number_format($paid, 2, ',', '.') . ' ₺' : '—' }}</td>
                            <td class="px-4 py-3 text-right {{ $rem > 0 ? 'text-red-500 font-semibold' : 'text-slate-400' }}">
                                {{ $rem > 0 ? number_format($rem, 2, ',', '.') . ' ₺' : '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($total == 0)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500">Kayıt Yok</span>
                                @elseif($rem == 0)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">✓ Ödendi</span>
                                @elseif($paid > 0)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Kısmi</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Bekliyor</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Daire kaydı bulunamadı.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                    <tr>
                        <td colspan="2" class="px-4 py-3 font-bold text-slate-700">TOPLAM</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-700">{{ number_format($totalBorç, 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-600">{{ number_format($totalÖdenen, 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3 text-right font-bold text-red-500">{{ number_format($totalBorç - $totalÖdenen, 2, ',', '.') }} ₺</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
