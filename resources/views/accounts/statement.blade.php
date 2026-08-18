@extends('layouts.app')

@section('content')
    {{-- Breadcrumb + Hesap Sekmeleri --}}
    <div class="mb-6 flex flex-row items-center justify-between gap-2 md:gap-4 min-w-0">
        <div class="flex items-center gap-2 min-w-0 overflow-x-auto">
            <a href="{{ route('accounts.index') }}" class="shrink-0 min-h-[3.5rem] sm:min-h-0 inline-flex items-center justify-center rounded-2xl sm:rounded-xl border border-slate-300 px-5 text-xs sm:text-sm sm:px-4 sm:py-2.5 font-semibold text-slate-700 bg-slate-50 hover:bg-slate-100">
                Hesaplar
            </a>
            <span class="text-slate-400">/</span>
            <a href="{{ route('accounts.show', $account) }}" class="shrink-0 min-h-[3.5rem] sm:min-h-0 inline-flex items-center justify-center rounded-2xl sm:rounded-xl border border-slate-300 px-5 text-xs sm:text-sm sm:px-4 sm:py-2.5 font-semibold text-slate-700 bg-white hover:bg-slate-50">
                @if ($account->unit)
                    Daire {{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}
                @else
                    {{ $account->type_label }}
                @endif
            </a>
        </div>

        <div class="flex items-center justify-end gap-2 shrink-0">
            @include('accounts._tabs', ['account' => $account, 'active' => 'statement', 'withOverview' => false])
        </div>
    </div>

    {{-- Başlık + Tarih Filtresi --}}
    <div class="rounded-2xl bg-white shadow-sm p-4 md:p-6 mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Tüm Hareketler</h1>
        <p class="mt-1 text-sm text-slate-500">
            {{ $account->name }}
            @if($account->unit) &mdash; Daire No: {{ $account->unit->unit_no }} @endif
        </p>

        <form method="GET" action="{{ route('accounts.statement', $account) }}" class="mt-5 border-t border-slate-100 pt-5">
            <div class="flex flex-row flex-wrap items-end gap-4">
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
                <div class="w-full md:w-auto flex gap-2">
                    <button type="submit"
                            class="flex-1 md:flex-none rounded-xl bg-slate-950 px-6 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 whitespace-nowrap">
                        Filtrele
                    </button>
                    @if($dateFrom || $dateTo)
                        <a href="{{ route('accounts.statement', $account) }}"
                           class="flex-1 md:flex-none text-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 whitespace-nowrap">
                            Temizle
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>


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
                            <th class="px-2.5 py-3 font-medium">
                                <span class="sm:hidden">Tarih / Açıklama</span>
                                <span class="hidden sm:inline">Tarih</span>
                            </th>
                            <th class="px-2.5 py-3 font-medium hidden sm:table-cell">Açıklama</th>
                            <th class="px-2.5 py-3 text-left sm:text-right font-medium">
                                <span class="sm:hidden">Tutar</span>
                                <span class="hidden sm:inline">Borç</span>
                            </th>
                            <th class="px-2.5 py-3 text-right font-medium hidden sm:table-cell">Alacak</th>
                            <th class="px-2.5 py-3 text-right font-medium hidden sm:table-cell">Bakiye</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @if($dateFrom)
                            <tr class="bg-slate-50 font-medium">
                                <td class="px-2.5 py-3.5 text-slate-500">
                                    <div class="sm:hidden">
                                        <div class="whitespace-nowrap">{{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }}</div>
                                        <div class="mt-1 text-slate-600">Dönem Açılış Bakiyesi</div>
                                    </div>
                                    <div class="hidden sm:block whitespace-nowrap">{{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }}</div>
                                </td>
                                <td class="px-2.5 py-3.5 text-slate-600 hidden sm:table-cell">Dönem Açılış Bakiyesi</td>
                                <td class="px-2.5 py-3.5 text-left sm:text-right">
                                    <div class="sm:hidden text-xs space-y-1">
                                        <div class="{{ $openingBalance > 0 ? 'text-red-600' : 'text-slate-400' }}">
                                            <span class="text-slate-500">Borç:</span>
                                            {{ $openingBalance > 0 ? number_format($openingBalance, 2, ',', '.') . ' TL' : '—' }}
                                        </div>
                                        <div class="{{ $openingBalance < 0 ? 'text-emerald-600' : 'text-slate-400' }}">
                                            <span class="text-slate-500">Alacak:</span>
                                            {{ $openingBalance < 0 ? number_format(abs($openingBalance), 2, ',', '.') . ' TL' : '—' }}
                                        </div>

                                    </div>
                                    <div class="hidden sm:block {{ $openingBalance > 0 ? 'text-red-600' : 'text-slate-400' }}">
                                        {{ $openingBalance > 0 ? number_format($openingBalance, 2, ',', '.') . ' TL' : '—' }}
                                    </div>
                                </td>
                                <td class="px-2.5 py-3.5 text-right hidden sm:table-cell {{ $openingBalance < 0 ? 'text-emerald-600' : 'text-slate-400' }}">
                                    {{ $openingBalance < 0 ? number_format(abs($openingBalance), 2, ',', '.') . ' TL' : '—' }}
                                </td>
                                <td class="px-2.5 py-3.5 text-right hidden sm:table-cell {{ $openingBalance > 0 ? 'text-red-600' : ($openingBalance < 0 ? 'text-emerald-600' : 'text-slate-600') }}">
                                    {{ number_format(abs($openingBalance), 2, ',', '.') }} TL
                                    @if($openingBalance != 0)
                                        <span class="text-xs font-normal">{{ $openingBalance > 0 ? 'B' : 'A' }}</span>
                                    @endif
                                </td>
                                <td class="px-2.5 py-3.5 text-right text-slate-400"></td>
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
                                <td class="px-2.5 py-3.5 text-slate-600 {{ $detailUrl ? 'cursor-pointer' : '' }}" @if($detailUrl) onclick="window.location.href='{{ $detailUrl }}'" @endif>
                                    <div class="sm:hidden">
                                        <div class="whitespace-nowrap text-slate-600">{{ $t->transaction_date?->format('d.m.Y') }}</div>
                                        <div class="mt-1 text-slate-700">
                                            {{ $t->description ?: ucfirst($t->type) }}
                                            @if($t->is_imported)
                                                <span class="ml-1 inline-block rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">İçe Aktarıldı</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="hidden sm:block whitespace-nowrap text-slate-600">{{ $t->transaction_date?->format('d.m.Y') }}</div>
                                </td>
                                <td class="px-2.5 py-3.5 text-slate-700 hidden sm:table-cell {{ $detailUrl ? 'cursor-pointer' : '' }}" @if($detailUrl) onclick="window.location.href='{{ $detailUrl }}'" @endif>
                                    {{ $t->description ?: ucfirst($t->type) }}
                                    @if($t->is_imported)
                                        <span class="ml-1 inline-block rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">İçe Aktarıldı</span>
                                    @endif
                                </td>
                                <td class="px-2.5 py-3.5 text-left sm:text-right tabular-nums {{ $detailUrl ? 'cursor-pointer' : '' }}" @if($detailUrl) onclick="window.location.href='{{ $detailUrl }}'" @endif>
                                    <div class="sm:hidden text-xs space-y-1">
                                        <div class="font-semibold {{ $debit ? 'text-red-600' : 'text-slate-400' }}">
                                            <span class="text-slate-500">Borç:</span>
                                            {{ $debit  ? number_format($debit,  2, ',', '.') . ' TL' : '—' }}
                                        </div>
                                        <div class="font-semibold {{ $credit ? 'text-emerald-600' : 'text-slate-400' }}">
                                            <span class="text-slate-500">Alacak:</span>
                                            {{ $credit ? number_format($credit, 2, ',', '.') . ' TL' : '—' }}
                                        </div>

                                    </div>
                                    <div class="hidden sm:block font-semibold text-red-600">
                                        {{ $debit  ? number_format($debit,  2, ',', '.') . ' TL' : '—' }}
                                    </div>
                                </td>
                                <td class="px-2.5 py-3.5 text-right font-semibold text-emerald-600 tabular-nums whitespace-nowrap hidden sm:table-cell {{ $detailUrl ? 'cursor-pointer' : '' }}" @if($detailUrl) onclick="window.location.href='{{ $detailUrl }}'" @endif>
                                    {{ $credit ? number_format($credit, 2, ',', '.') . ' TL' : '—' }}
                                </td>
                                <td class="px-2.5 py-3.5 text-right font-bold tabular-nums whitespace-nowrap hidden sm:table-cell {{ $detailUrl ? 'cursor-pointer' : '' }} {{ $t->running_balance > 0 ? 'text-red-600' : ($t->running_balance < 0 ? 'text-emerald-600' : 'text-slate-600') }}" @if($detailUrl) onclick="window.location.href='{{ $detailUrl }}'" @endif>
                                    {{ number_format(abs($t->running_balance), 2, ',', '.') }} TL
                                    @if($t->running_balance != 0)
                                        <span class="text-xs font-normal">{{ $t->running_balance > 0 ? 'B' : 'A' }}</span>
                                    @endif
                                </td>
                                <td class="px-1 py-3.5 text-right whitespace-nowrap sm:px-5">
                                    @if(($t->transactionable_type ?? '') === \App\Models\Payment::class && $t->allocations->isNotEmpty())
                                        <button type="button" data-toggle-alloc="alloc-{{ $t->id }}"
                                                class="rounded-lg border border-slate-200 p-1 text-slate-600 hover:bg-slate-50 inline-flex items-center sm:px-2 sm:py-1.5">
                                            <span class="sm:hidden icon-collapsed">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                            </span>
                                            <span class="hidden sm:hidden icon-expanded">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>
                                            </span>
                                            <span class="hidden sm:inline text-xs font-semibold text-collapsed">
                                                {{ $account->type === \App\Models\Account::TYPE_SUPPLIER ? 'Bağlı Giderler' : 'Bağlı Borçlar' }}
                                            </span>
                                            <span class="hidden text-xs font-semibold text-expanded">
                                                Gizle
                                            </span>
                                        </button>
                                    @endif
                                </td>
                            </tr>

                            @if($t->allocations->isNotEmpty())
                                @foreach($t->allocations as $a)
                                    <tr class="bg-slate-50 text-xs alloc-{{ $t->id }} hidden" data-parent="alloc-{{ $t->id }}">
                                        <td class="px-2.5 py-2 text-slate-500 sm:hidden">
                                            {{ $account->type === \App\Models\Account::TYPE_SUPPLIER ? 'Bağlı Gider' : 'Bağlı Borç' }}
                                            @if($a->due)
                                                <a href="{{ route('dues.show', $a->due) }}" class="font-medium text-slate-700 hover:text-emerald-600">{{ $a->due->description ?: 'Aidat' }}</a>
                                            @elseif($a->expense)
                                                <a href="{{ route('expenses.show', $a->expense) }}" class="font-medium text-slate-700 hover:text-emerald-600">{{ $a->expense->description ?: ($a->expense->category ?: 'Gider') }}</a>
                                            @endif
                                        </td>
                                        @if($a->due)
                                            <td class="px-2.5 py-2 text-slate-500 hidden sm:table-cell">
                                                {{ $account->type === \App\Models\Account::TYPE_SUPPLIER ? 'Bağlı Gider' : 'Bağlı Borç' }}
                                                <a href="{{ route('dues.show', $a->due) }}" class="font-medium text-slate-700 hover:text-emerald-600">{{ $a->due->description ?: 'Aidat' }}</a>
                                            </td>
                                            <td class="px-2.5 py-2 text-left sm:text-right">
                                                <div class="sm:hidden text-emerald-600 font-medium tabular-nums">{{ number_format($a->amount, 2, ',', '.') }} TL</div>
                                                <div class="hidden sm:block text-right">—</div>
                                            </td>
                                            <td class="px-2.5 py-2 text-right hidden sm:table-cell">—</td>
                                            <td class="px-2.5 py-2 text-right hidden sm:table-cell text-emerald-600 font-medium tabular-nums">{{ number_format($a->amount, 2, ',', '.') }} TL</td>
                                            <td class="px-2.5 py-2 text-right">
                                                <a href="{{ route('dues.show', $a->due) }}" class="rounded-lg border border-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 inline-flex items-center gap-1"><span class="sm:hidden inline-flex items-center"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></span><span class="hidden sm:inline">Detay</span></a>
                                            </td>
                                        @elseif($a->expense)
                                            <td class="px-2.5 py-2 text-slate-500 hidden sm:table-cell">
                                                {{ $account->type === \App\Models\Account::TYPE_SUPPLIER ? 'Bağlı Gider' : 'Bağlı Borç' }}
                                                <a href="{{ route('expenses.show', $a->expense) }}" class="font-medium text-slate-700 hover:text-emerald-600">{{ $a->expense->description ?: ($a->expense->category ?: 'Gider') }}</a>
                                            </td>
                                            <td class="px-2.5 py-2 text-left sm:text-right">
                                                <div class="sm:hidden text-emerald-600 font-medium tabular-nums">{{ number_format($a->amount, 2, ',', '.') }} TL</div>
                                                <div class="hidden sm:block text-right">—</div>
                                            </td>
                                            <td class="px-2.5 py-2 text-right hidden sm:table-cell">—</td>
                                            <td class="px-2.5 py-2 text-right hidden sm:table-cell text-emerald-600 font-medium tabular-nums">{{ number_format($a->amount, 2, ',', '.') }} TL</td>
                                            <td class="px-2.5 py-2 text-right">
                                                <a href="{{ route('expenses.show', $a->expense) }}" class="rounded-lg border border-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 inline-flex items-center gap-1"><span class="sm:hidden inline-flex items-center"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></span><span class="hidden sm:inline">Detay</span></a>
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

    <div class="mt-4 flex justify-end">
        <a href="{{ route('accounts.statement.export', ['id' => $account->id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
           class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
            Excel'e Aktar
        </a>
    </div>


    <script>
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-toggle-alloc]');
            if (!btn) return;
            e.stopPropagation();
            const key = btn.getAttribute('data-toggle-alloc');
            document.querySelectorAll('[data-parent="' + key + '"]').forEach(r => r.classList.toggle('hidden'));
            const open = Array.from(document.querySelectorAll('[data-parent="' + key + '"]')).some(r => !r.classList.contains('hidden'));
            btn.querySelector('.icon-collapsed').classList.toggle('hidden', open);
            btn.querySelector('.icon-expanded').classList.toggle('hidden', !open);
            btn.querySelector('.text-collapsed').classList.toggle('sm:inline', !open);
            btn.querySelector('.text-expanded').classList.toggle('sm:inline', open);
        });

    </script>
@endsection
