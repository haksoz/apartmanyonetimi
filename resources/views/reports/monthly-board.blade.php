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
                <span class="text-slate-600">Aylık Aidat Pano Tablosu</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">{{ isset($title) ? $title : 'Aylık Aidat Pano Tablosu' }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ isset($title) ? '' : $apartment->name . ' — ' . $trMonthsH[$parsedMonth->month] . ' ' . $parsedMonth->year }}</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('reports.monthly-board.export', ['type'=>'excel', 'month'=>$month, 'type_filter'=>$typeFilter, 'status_filter'=>$statusFilter, 'show_account_type'=>$showAccountType ? 1 : 0]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel
            </a>
            <a href="{{ route('reports.monthly-board.export', ['type'=>'pdf', 'month'=>$month, 'type_filter'=>$typeFilter, 'status_filter'=>$statusFilter, 'show_account_type'=>$showAccountType ? 1 : 0]) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
        </div>
    </div>

    {{-- Ay Seçici --}}
    <form method="GET" action="{{ route('reports.monthly-board') }}" class="mb-5 bg-white rounded-2xl border border-slate-200 p-4 flex flex-wrap gap-4 items-end">
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
        <div>
            <label class="block text-xs text-slate-500 mb-1">Hesap Türü</label>
            <select name="type_filter" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                <option value="resident" @selected($typeFilter === 'resident')>Daire Sakinleri</option>
                <option value="all" @selected($typeFilter === 'all')>Tümü</option>
                <option value="owner" @selected($typeFilter === 'owner')>Sadece Kat Maliki</option>
                <option value="tenant" @selected($typeFilter === 'tenant')>Sadece Kiracı</option>
            </select>
        </div>
        <div id="status-filter-wrap">
            <label class="block text-xs text-slate-500 mb-1">Durum</label>
            <select name="status_filter" id="status_filter" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                <option value="active" @selected($statusFilter === 'active')>Aktif</option>
                <option value="inactive" @selected($statusFilter === 'inactive')>Pasif / Silinmiş</option>
                <option value="all" @selected($statusFilter === 'all')>Her İkisi</option>
            </select>
        </div>
        <div class="flex items-center gap-2 pb-2">
            <input type="checkbox" name="show_account_type" id="show_account_type" value="1" @checked($showAccountType) class="rounded border-slate-300 text-slate-950 focus:ring-slate-300">
            <label for="show_account_type" class="text-sm text-slate-700">Hesap türünü göster</label>
        </div>
        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Görüntüle</button>
    </form>
    <script>
        (function () {
            const typeSelect = document.querySelector('select[name="type_filter"]');
            const statusWrap = document.getElementById('status-filter-wrap');
            const statusSelect = document.getElementById('status_filter');
            function toggleStatus() {
                if (typeSelect.value === 'resident') {
                    statusWrap.style.display = 'none';
                    statusSelect.disabled = true;
                    statusSelect.value = 'active';
                } else {
                    statusWrap.style.display = '';
                    statusSelect.disabled = false;
                }
            }
            typeSelect.addEventListener('change', toggleStatus);
            toggleStatus();
        })();
    </script>

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

    {{-- Özet Satırı --}}
    @php
        $paidCount = 0;
        $pendingCount = 0;
        $partialCount = 0;
        foreach($accounts as $account) {
            $data = $accountData[$account->id];
            if($data['remaining'] == 0 && $data['selectedAmount'] > 0) $paidCount++;
            elseif($data['paid'] > 0) $partialCount++;
            elseif($data['selectedAmount'] > 0) $pendingCount++;
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
                {{ $totalBorç > 0 ? number_format(($totalÖdenen / $totalBorç) * 100, 0) : 0 }}%
            </p>
        </div>
    </div>

    {{-- Pano Tablosu --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">
                @if($typeFilter === 'resident')
                    {{ $apartment->name }} Daire Sakinleri — {{ $trMonthsH[$parsedMonth->month] }} {{ $parsedMonth->year }}
                @else
                    {{ $apartment->name }} — {{ $trMonthsH[$parsedMonth->month] }} {{ $parsedMonth->year }} Aidat Durumu
                @endif
            </h2>
            <span class="text-xs text-slate-400">{{ now()->format('d.m.Y') }} tarihli çıktı</span>
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
                        @php
                            $data = $accountData[$account->id];
                            $typeLabel = ['owner' => 'Kat Maliki', 'tenant' => 'Kiracı'][$account->type] ?? $account->type;
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-bold text-slate-800">{{ $account->unit?->unit_no }}</td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ $account->name }}
                                @if($showAccountType && $account->type === 'owner')
                                    <span class="ml-1 text-[10px] text-slate-400">(Kat Maliki)</span>
                                @elseif($showAccountType && $account->type === 'tenant')
                                    <span class="ml-1 text-[10px] text-slate-400">(Kiracı)</span>
                                @endif
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
