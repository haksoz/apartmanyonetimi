@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500 mb-1">Hesaplar / {{ $account->type_label }}</p>
            <h1 class="text-2xl font-bold text-slate-950">Tüm Hareketler</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $account->name }}
                @if($account->unit) &mdash; Daire No: {{ $account->unit->unit_no }} @endif
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('accounts.statement.export', ['id' => $account->id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
               class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                Excel'e Aktar
            </a>
            <a href="{{ route('accounts.show', $account) }}"
               class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Hesaba Dön
            </a>
        </div>
    </div>

    {{-- Tarih Filtresi --}}
    <form method="GET" action="{{ route('accounts.statement', $account) }}"
          class="rounded-2xl bg-white p-5 shadow-sm mb-6">
        <div class="flex flex-col md:flex-row gap-4 items-end">
            <div class="flex-1">
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Başlangıç Tarihi</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}"
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-950 focus:outline-none">
            </div>
            <div class="flex-1">
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Bitiş Tarihi</label>
                <input type="date" name="date_to" value="{{ $dateTo }}"
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-950 focus:outline-none">
            </div>
            <button type="submit"
                    class="rounded-xl bg-slate-950 px-6 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 whitespace-nowrap">
                Filtrele
            </button>
            @if($dateFrom || $dateTo)
                <a href="{{ route('accounts.statement', $account) }}"
                   class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 whitespace-nowrap">
                    Temizle
                </a>
            @endif
        </div>
    </form>


    {{-- Hareketler Tablosu --}}
    <div class="rounded-2xl bg-white shadow-sm overflow-hidden" data-account-type="{{ $account->type }}">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-950">
                Hareketler
                @if($dateFrom || $dateTo)
                    <span class="ml-2 text-sm font-normal text-slate-500">
                        {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') : '—' }}
                        &mdash;
                        {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d.m.Y') : 'Bugün' }}
                    </span>
                @endif
            </h2>
            <span class="text-sm text-slate-500">{{ $transactions->count() }} kayıt</span>
        </div>

        @if($transactions->isEmpty())
            <div class="px-6 py-12 text-center text-sm text-slate-500">
                Bu tarih aralığında hareket bulunamadı.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-medium">Tarih</th>
                            <th class="px-5 py-3 font-medium">Açıklama</th>
                            <th class="px-5 py-3 text-right font-medium">Borç</th>
                            <th class="px-5 py-3 text-right font-medium">Alacak</th>
                            <th class="px-5 py-3 text-right font-medium">Bakiye</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @if($dateFrom)
                            <tr class="bg-slate-50 font-medium">
                                <td class="px-5 py-3.5 text-slate-500">{{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }}</td>
                                <td class="px-5 py-3.5 text-slate-600">Dönem Açılış Bakiyesi</td>
                                <td class="px-5 py-3.5 text-right {{ $openingBalance > 0 ? 'text-red-600' : 'text-slate-400' }}">
                                    {{ $openingBalance > 0 ? number_format($openingBalance, 2, ',', '.') . ' TL' : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right {{ $openingBalance < 0 ? 'text-emerald-600' : 'text-slate-400' }}">
                                    {{ $openingBalance < 0 ? number_format(abs($openingBalance), 2, ',', '.') . ' TL' : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right {{ $openingBalance > 0 ? 'text-red-600' : ($openingBalance < 0 ? 'text-emerald-600' : 'text-slate-600') }}">
                                    {{ number_format(abs($openingBalance), 2, ',', '.') }} TL
                                    @if($openingBalance != 0)
                                        <span class="text-xs font-normal">{{ $openingBalance > 0 ? 'B' : 'A' }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right text-slate-400"></td>
                            </tr>
                        @endif
                        @foreach($transactions as $t)
                            @php
                                $debit  = $t->type === 'debit'  ? $t->amount : 0;
                                $credit = $t->type === 'credit' ? $t->amount : 0;
                                $detailUrl = null;
                                if(($t->transactionable_type ?? '') === \App\Models\Payment::class && $t->transactionable_id)
                                    $detailUrl = route('payments.show', $t->transactionable_id);
                                elseif(($t->transactionable_type ?? '') === \App\Models\Due::class && $t->transactionable_id)
                                    $detailUrl = route('dues.show', $t->transactionable_id);
                                elseif(($t->transactionable_type ?? '') === \App\Models\Expense::class && $t->transactionable_id)
                                    $detailUrl = route('expenses.show', $t->transactionable_id);
                            @endphp
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-5 py-3.5 text-slate-600 whitespace-nowrap {{ $detailUrl ? 'cursor-pointer' : '' }}" @if($detailUrl) onclick="window.location.href='{{ $detailUrl }}'" @endif>
                                    {{ $t->transaction_date?->format('d.m.Y') }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-700 {{ $detailUrl ? 'cursor-pointer' : '' }}" @if($detailUrl) onclick="window.location.href='{{ $detailUrl }}'" @endif>
                                    {{ $t->description ?: ucfirst($t->type) }}
                                    @if($t->is_imported)
                                        <span class="ml-1 inline-block rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">İçe Aktarıldı</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right font-semibold text-red-600 tabular-nums whitespace-nowrap {{ $detailUrl ? 'cursor-pointer' : '' }}" @if($detailUrl) onclick="window.location.href='{{ $detailUrl }}'" @endif>
                                    {{ $debit  ? number_format($debit,  2, ',', '.') . ' TL' : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-semibold text-emerald-600 tabular-nums whitespace-nowrap {{ $detailUrl ? 'cursor-pointer' : '' }}" @if($detailUrl) onclick="window.location.href='{{ $detailUrl }}'" @endif>
                                    {{ $credit ? number_format($credit, 2, ',', '.') . ' TL' : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-bold tabular-nums whitespace-nowrap {{ $detailUrl ? 'cursor-pointer' : '' }} {{ $t->running_balance > 0 ? 'text-red-600' : ($t->running_balance < 0 ? 'text-emerald-600' : 'text-slate-600') }}" @if($detailUrl) onclick="window.location.href='{{ $detailUrl }}'" @endif>
                                    {{ number_format(abs($t->running_balance), 2, ',', '.') }} TL
                                    @if($t->running_balance != 0)
                                        <span class="text-xs font-normal">{{ $t->running_balance > 0 ? 'B' : 'A' }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                    @if(($t->transactionable_type ?? '') === \App\Models\Payment::class && $t->allocations->isNotEmpty())
                                        <button type="button" data-toggle-alloc="alloc-{{ $t->id }}"
                                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                            {{ $account->type === \App\Models\Account::TYPE_SUPPLIER ? 'Kapattığı Giderler' : 'Bağlı Aidatlar' }}
                                        </button>
                                    @endif
                                </td>
                            </tr>

                            @if($t->allocations->isNotEmpty())
                                @foreach($t->allocations as $a)
                                    <tr class="bg-slate-50 text-xs alloc-{{ $t->id }} hidden" data-parent="alloc-{{ $t->id }}">
                                        <td class="px-5 py-2"></td>
                                        @if($a->due)
                                            <td class="px-5 py-2 text-slate-500">
                                                {{ $account->type === \App\Models\Account::TYPE_SUPPLIER ? 'Kapatılan Gider' : 'Kapatılan Borç' }}
                                                <a href="{{ route('dues.show', $a->due) }}" class="font-medium text-slate-700 hover:text-emerald-600">{{ $a->due->description ?: 'Aidat' }}</a>
                                            </td>
                                            <td class="px-5 py-2 text-right">—</td>
                                            <td class="px-5 py-2 text-right">—</td>
                                            <td class="px-5 py-2 text-right text-emerald-600 font-medium tabular-nums">{{ number_format($a->amount, 2, ',', '.') }} TL</td>
                                            <td class="px-5 py-2 text-right">
                                                <a href="{{ route('dues.show', $a->due) }}" class="rounded-lg border border-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">Aidat Detay</a>
                                            </td>
                                        @elseif($a->expense)
                                            <td class="px-5 py-2 text-slate-500">
                                                {{ $account->type === \App\Models\Account::TYPE_SUPPLIER ? 'Kapatılan Gider' : 'Kapatılan Borç' }}
                                                <a href="{{ route('expenses.show', $a->expense) }}" class="font-medium text-slate-700 hover:text-emerald-600">{{ $a->expense->description ?: ($a->expense->category ?: 'Gider') }}</a>
                                            </td>
                                            <td class="px-5 py-2 text-right">—</td>
                                            <td class="px-5 py-2 text-right">—</td>
                                            <td class="px-5 py-2 text-right text-emerald-600 font-medium tabular-nums">{{ number_format($a->amount, 2, ',', '.') }} TL</td>
                                            <td class="px-5 py-2 text-right">
                                                <a href="{{ route('expenses.show', $a->expense) }}" class="rounded-lg border border-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">Gider Detay</a>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Kapanış bakiyesi özeti --}}
            <div class="mt-4 p-4 rounded-2xl {{ $closingBalance > 0 ? 'bg-red-50 border border-red-200' : ($closingBalance < 0 ? 'bg-emerald-50 border border-emerald-200' : 'bg-slate-50 border border-slate-200') }}">
                <p class="text-center text-base font-medium {{ $closingBalance > 0 ? 'text-red-700' : ($closingBalance < 0 ? 'text-emerald-700' : 'text-slate-700') }}">
                    Hesabın Toplam
                    <span class="font-bold text-lg mx-1">{{ number_format(abs($closingBalance), 2, ',', '.') }} TL</span>
                    {{ $closingBalance > 0 ? 'borcu vardır' : ($closingBalance < 0 ? 'alacağı vardır' : 'bakiyesi sıfırdır') }}
                </p>
            </div>
        @endif
    </div>


    <script>
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-toggle-alloc]');
            if (!btn) return;
            e.stopPropagation();
            const key = btn.getAttribute('data-toggle-alloc');
            document.querySelectorAll('[data-parent="' + key + '"]').forEach(r => r.classList.toggle('hidden'));
            const open = Array.from(document.querySelectorAll('[data-parent="' + key + '"]')).some(r => !r.classList.contains('hidden'));
            const isSupplier = btn.closest('table')?.closest('[data-account-type]')?.getAttribute('data-account-type') === 'supplier';
            btn.textContent = open ? 'Gizle' : (isSupplier ? 'Kapattığı Giderler' : 'Bağlı Aidatlar');
        });

    </script>
@endsection
