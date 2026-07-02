@extends('layouts.app')

@section('content')
    @if(!isset($pdfMode))
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:text-slate-600">Raporlar</a>
                <span>/</span>
                <span class="text-slate-600">Cari Ekstreler</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Cari Ekstreler</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }}</p>
        </div>
        @if($accountId)
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('reports.account-statement.export', array_merge(['type'=>'excel'], request()->query())) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel
            </a>
            <a href="{{ route('reports.account-statement.export', array_merge(['type'=>'pdf'], request()->query())) }}"
               class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
        </div>
        @endif
    </div>

    {{-- Filtreler --}}
    <form method="GET" action="{{ route('reports.account-statement') }}" class="mb-5 bg-white rounded-2xl border border-slate-200 p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="block text-xs text-slate-500 mb-1">Hesap</label>
            <select name="account_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                <option value="">— Hesap Seçin —</option>
                @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" @selected($accountId == $acc->id)>
                        {{-- --}}{{ $acc->unit ? 'D' . str_pad($acc->unit->unit_no, 2, '0', STR_PAD_LEFT) . ' — ' : '' }}{{ $acc->name }} ({{ $acc->type_label }})
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Başlangıç</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Bitiş</label>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>
        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Görüntüle</button>
    </form>
    @endif

    @if($accountId && $account)
    {{-- Özet Kartlar --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <p class="text-xs text-slate-500 mb-1">Hesap</p>
            <p class="text-base font-bold text-slate-800">
                @if($account->unit)<span class="text-slate-500 font-normal">Daire {{ $account->unit->unit_no }} — </span>@endif{{ $account->name }}
            </p>
            <p class="text-xs text-slate-400 mt-0.5">{{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d.m.Y') }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <p class="text-xs text-slate-500 mb-1">Toplam Borç</p>
            <p class="text-xl font-bold text-red-500">{{ number_format($totalDebit, 2, ',', '.') }} ₺</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <p class="text-xs text-slate-500 mb-1">Toplam Alacak</p>
            <p class="text-xl font-bold text-emerald-600">{{ number_format($totalCredit, 2, ',', '.') }} ₺</p>
        </div>
    </div>
    @php
        if (!isset($summaryText)) {
            $summaryText = $runningBalance < 0
                ? 'Hesabın toplam ' . number_format(abs($runningBalance), 2, ',', '.') . ' TL borcu vardır.'
                : ($runningBalance > 0
                    ? 'Hesabın toplam ' . number_format($runningBalance, 2, ',', '.') . ' TL alacağı vardır.'
                    : 'Hesabın borcu yoktur.');
            $summaryColor = $runningBalance < 0 ? 'bg-red-50 border-red-200 text-red-700' : ($runningBalance > 0 ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-slate-50 border-slate-200 text-slate-600');
        }
    @endphp
    <div class="rounded-2xl border {{ $summaryColor }} px-6 py-5 mb-6 text-base font-bold">
        {{ $summaryText }}
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Tarih</th>
                        <th class="px-4 py-3 text-left font-semibold">Tür</th>
                        <th class="px-4 py-3 text-left font-semibold">Açıklama</th>
                        <th class="px-4 py-3 text-right font-semibold">Borç</th>
                        <th class="px-4 py-3 text-right font-semibold">Alacak</th>
                        <th class="px-4 py-3 text-right font-semibold">Bakiye</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @if($dateFrom && isset($openingBalance))
                        <tr class="bg-slate-50">
                            <td class="px-4 py-3 text-slate-500 font-medium">{{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Açılış</span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 font-medium">Dönem Açılış Bakiyesi</td>
                            <td class="px-4 py-3 text-right text-red-500">{{ $openingBalance > 0 ? number_format($openingBalance, 2, ',', '.') . ' ₺' : '' }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600">{{ $openingBalance < 0 ? number_format(abs($openingBalance), 2, ',', '.') . ' ₺' : '' }}</td>
                            <td class="px-4 py-3 text-right font-medium {{ $openingBalance > 0 ? 'text-red-500' : ($openingBalance < 0 ? 'text-emerald-600' : 'text-slate-400') }}">
                                {{ number_format(abs($openingBalance), 2, ',', '.') }} ₺
                                @if($openingBalance != 0)
                                    <span class="text-xs font-normal">{{ $openingBalance > 0 ? 'B' : 'A' }}</span>
                                @endif
                            </td>
                        </tr>
                    @endif
                    @forelse($transactions as $tx)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-600">{{ $tx->transaction_date?->format('d.m.Y') ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $tx->type === 'debit' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $tx->type === 'debit' ? 'Borç' : 'Alacak' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $tx->description ?? '-' }}</td>
                            <td class="px-4 py-3 text-right text-red-500">{{ $tx->type === 'debit' ? number_format($tx->amount, 2, ',', '.') . ' ₺' : '' }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600">{{ $tx->type === 'credit' ? number_format($tx->amount, 2, ',', '.') . ' ₺' : '' }}</td>
                            <td class="px-4 py-3 text-right font-medium {{ $tx->running_balance >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                                {{ number_format($tx->running_balance, 2, ',', '.') }} ₺
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Bu dönemde hareket bulunamadı.</td></tr>
                    @endforelse
                    @if($transactions->isNotEmpty())
                        <tr class="bg-slate-100 font-bold text-sm border-t-2 border-slate-300">
                            <td class="px-4 py-3 text-slate-700" colspan="3">TOPLAM</td>
                            <td class="px-4 py-3 text-right text-red-600">{{ number_format($totalDebit, 2, ',', '.') }} ₺</td>
                            <td class="px-4 py-3 text-right text-emerald-600">{{ number_format($totalCredit, 2, ',', '.') }} ₺</td>
                            <td class="px-4 py-3 text-right {{ $runningBalance < 0 ? 'text-red-600' : ($runningBalance > 0 ? 'text-emerald-600' : 'text-slate-500') }}">
                                {{ number_format(abs($runningBalance), 2, ',', '.') }} ₺
                                @if($runningBalance != 0)
                                    <span class="text-xs font-normal">{{ $runningBalance < 0 ? 'B' : 'A' }}</span>
                                @endif
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
        <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <p class="text-slate-400 text-sm">Cari ekstre görüntülemek için yukarıdan bir hesap seçin.</p>
    </div>
    @endif
@endsection
