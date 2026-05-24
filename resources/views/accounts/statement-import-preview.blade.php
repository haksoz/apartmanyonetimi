@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">İçeri Aktarma Önizlemesi</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $account->name }}
                @if($account->unit) &mdash; {{ $account->unit->unit_no }} no.lu daire @endif
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('accounts.statement', $account) }}"
               class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                İptal
            </a>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-slate-900">{{ count($transactions) }} adet hareket içeri aktarılacak</h3>
                @php
                    $debitCount  = collect($transactions)->where('debit', '>', 0)->count();
                    $creditCount = collect($transactions)->where('credit', '>', 0)->count();
                @endphp
                <p class="text-sm text-slate-500">
                    <span class="font-medium text-slate-700">{{ $debitCount }}</span> Devir Öncesi Aidat &bull;
                    <span class="font-medium text-slate-700">{{ $creditCount }}</span> Devir Öncesi Ödeme
                    <br>Onaylayınca <strong>Devir Öncesi Kasası</strong> otomatik oluşturulur (yoksa).
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="text-left py-3 px-4 font-semibold text-slate-700">Tarih</th>
                        <th class="text-left py-3 px-4 font-semibold text-slate-700">Açıklama</th>
                        <th class="text-right py-3 px-4 font-semibold text-slate-700">Borç</th>
                        <th class="text-right py-3 px-4 font-semibold text-slate-700">Alacak</th>
                        <th class="text-left py-3 px-4 font-semibold text-slate-700">Kategori</th>
                        <th class="text-left py-3 px-4 font-semibold text-slate-700">Tür</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $index => $t)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-slate-50' }} border-b border-slate-100">
                            <td class="py-3 px-4 text-slate-900">{{ \Carbon\Carbon::parse($t['date'])->format('d.m.Y') }}</td>
                            <td class="py-3 px-4 text-slate-700">{{ $t['description'] }}</td>
                            <td class="py-3 px-4 text-right text-slate-900">{{ $t['debit'] > 0 ? number_format($t['debit'], 2, ',', '.') . ' TL' : '—' }}</td>
                            <td class="py-3 px-4 text-right text-slate-900">{{ $t['credit'] > 0 ? number_format($t['credit'], 2, ',', '.') . ' TL' : '—' }}</td>
                            <td class="py-3 px-4 text-slate-500">{{ $t['debit'] > 0 ? ($t['category_name'] ?: 'Aidat') : '—' }}</td>
                            <td class="py-3 px-4">
                                @if($t['debit'] > 0)
                                    <span class="inline-block rounded-md bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700">Devir Öncesi Aidat</span>
                                @else
                                    <span class="inline-block rounded-md bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-700">Devir Öncesi Ödeme</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('accounts.statement', $account) }}"
           class="rounded-xl border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            İptal
        </a>
        <form method="POST" action="{{ route('accounts.statement.import-confirm', $account->id) }}">
            @csrf
            <button type="submit" class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700">
                Onayla ve İçe Aktar
            </button>
        </form>
    </div>
@endsection
