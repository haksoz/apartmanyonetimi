@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Hesap Ekstresi</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $account->name }}
                @if($account->unit) &mdash; {{ $account->unit->unit_no }} no.lu daire @endif
            </p>
        </div>
        <div class="flex gap-2">
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

    {{-- Özet Kartları --}}
    <div class="grid gap-4 md:grid-cols-3 mb-6">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-medium text-slate-500 mb-1">
                {{ $dateFrom ? 'Dönem Açılış Bakiyesi' : 'Tüm Zamanlar' }}
            </div>
            <div class="text-xl font-bold {{ $openingBalance > 0 ? 'text-red-600' : ($openingBalance < 0 ? 'text-emerald-600' : 'text-slate-900') }}">
                {{ number_format(abs($openingBalance), 2, ',', '.') }} TL
                @if($openingBalance != 0)
                    <span class="text-xs font-normal">{{ $openingBalance > 0 ? '(Borç)' : '(Alacak)' }}</span>
                @endif
            </div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-medium text-slate-500 mb-1">Dönem Hareketi</div>
            <div class="text-xl font-bold text-slate-900">{{ $transactions->count() }} kayıt</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-medium text-slate-500 mb-1">
                {{ $dateFrom ? 'Dönem Kapanış Bakiyesi' : 'Güncel Bakiye' }}
            </div>
            <div class="text-xl font-bold {{ $closingBalance > 0 ? 'text-red-600' : ($closingBalance < 0 ? 'text-emerald-600' : 'text-slate-900') }}">
                {{ number_format(abs($closingBalance), 2, ',', '.') }} TL
                @if($closingBalance != 0)
                    <span class="text-xs font-normal">{{ $closingBalance > 0 ? '(Borç)' : '(Alacak)' }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Hareketler Tablosu --}}
    <div class="rounded-2xl bg-white shadow-sm overflow-hidden">
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
            {{-- Açılış bakiyesi satırı --}}
            @if($dateFrom)
                <div class="px-6 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between text-sm">
                    <span class="font-medium text-slate-600">Dönem Açılış Bakiyesi ({{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }} öncesi)</span>
                    <span class="font-bold {{ $openingBalance > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                        {{ number_format(abs($openingBalance), 2, ',', '.') }} TL
                        {{ $openingBalance > 0 ? '(Borç)' : ($openingBalance < 0 ? '(Alacak)' : '') }}
                    </span>
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-medium">Tarih</th>
                            <th class="px-5 py-3 font-medium">Açıklama</th>
                            <th class="px-5 py-3 text-right font-medium">Borç</th>
                            <th class="px-5 py-3 text-right font-medium">Alacak</th>
                            <th class="px-5 py-3 text-right font-medium">Bakiye</th>
                            <th class="px-5 py-3 text-right font-medium">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($transactions as $t)
                            @php
                                $debit  = $t->type === 'debit'  ? $t->amount : 0;
                                $credit = $t->type === 'credit' ? $t->amount : 0;
                            @endphp
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-5 py-3.5 text-slate-600 whitespace-nowrap">
                                    {{ $t->transaction_date->format('d.m.Y') }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    {{ $t->description ?: ucfirst($t->type) }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-semibold text-red-600 tabular-nums whitespace-nowrap">
                                    {{ $debit  ? number_format($debit,  2, ',', '.') . ' TL' : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-semibold text-emerald-600 tabular-nums whitespace-nowrap">
                                    {{ $credit ? number_format($credit, 2, ',', '.') . ' TL' : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-bold tabular-nums whitespace-nowrap {{ $t->running_balance > 0 ? 'text-red-600' : ($t->running_balance < 0 ? 'text-emerald-600' : 'text-slate-600') }}">
                                    {{ number_format(abs($t->running_balance), 2, ',', '.') }} TL
                                    @if($t->running_balance != 0)
                                        <span class="text-xs font-normal">{{ $t->running_balance > 0 ? 'B' : 'A' }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-1">
                                    @if(($t->transactionable_type ?? '') === \App\Models\Payment::class && $t->transactionable_id)
                                        @if($t->allocations->isNotEmpty())
                                            <button type="button" data-toggle-alloc="alloc-{{ $t->id }}"
                                                    class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                                Tahsisler
                                            </button>
                                        @endif
                                        <a href="{{ route('payments.show', $t->transactionable_id) }}"
                                           class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                            Detay
                                        </a>
                                    @elseif(($t->transactionable_type ?? '') === \App\Models\Due::class && $t->transactionable_id)
                                        <a href="{{ route('dues.show', $t->transactionable_id) }}"
                                           class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                            Detay
                                        </a>
                                    @elseif(($t->transactionable_type ?? '') === \App\Models\Expense::class && $t->transactionable_id)
                                        <a href="{{ route('expenses.show', $t->transactionable_id) }}"
                                           class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                            Detay
                                        </a>
                                    @endif
                                </td>
                            </tr>

                            @if($t->allocations->isNotEmpty())
                                @foreach($t->allocations as $a)
                                    <tr class="bg-slate-50 text-xs alloc-{{ $t->id }} hidden" data-parent="alloc-{{ $t->id }}">
                                        <td class="px-5 py-2"></td>
                                        <td class="px-5 py-2 text-slate-500">
                                            Tahsis &rarr; Aidat
                                            <a href="{{ route('dues.show', $a->due) }}" class="font-medium text-slate-700 hover:text-emerald-600">#{{ $a->due->id }}</a>
                                            &mdash; {{ $a->due->due_date->format('d.m.Y') }}
                                        </td>
                                        <td class="px-5 py-2 text-right text-emerald-600 font-medium tabular-nums">{{ number_format($a->amount, 2, ',', '.') }} TL</td>
                                        <td colspan="3" class="px-5 py-2 text-right">
                                            <a href="{{ route('dues.show', $a->due) }}" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100">Aidat Detay</a>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Kapanış bakiyesi --}}
            <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-sm">
                <span class="font-medium text-slate-600">
                    {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d.m.Y').' Kapanış Bakiyesi' : 'Güncel Bakiye' }}
                </span>
                <span class="font-bold text-base {{ $closingBalance > 0 ? 'text-red-600' : ($closingBalance < 0 ? 'text-emerald-600' : 'text-slate-900') }}">
                    {{ number_format(abs($closingBalance), 2, ',', '.') }} TL
                    @if($closingBalance != 0)
                        {{ $closingBalance > 0 ? '(Borç)' : '(Alacak)' }}
                    @endif
                </span>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-toggle-alloc]');
            if (!btn) return;
            const key = btn.getAttribute('data-toggle-alloc');
            document.querySelectorAll('[data-parent="' + key + '"]').forEach(r => r.classList.toggle('hidden'));
            const open = Array.from(document.querySelectorAll('[data-parent="' + key + '"]')).some(r => !r.classList.contains('hidden'));
            btn.textContent = open ? 'Gizle' : 'Tahsisler';
        });
    </script>
@endsection
