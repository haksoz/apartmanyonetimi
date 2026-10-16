@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-row items-center justify-between gap-3 min-w-0">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold text-slate-950">
                <a href="{{ route('cash.index') }}" class="hover:text-slate-600">Kasa</a>
                <span class="font-normal text-slate-400"> / </span>
                <span class="text-lg font-semibold text-slate-800">{{ $cashBox->name }}</span>
            </h1>
            <p class="mt-1 text-sm text-slate-500">{{ $cashBox->description ?: 'Gelir, gider ve bakiye hareketleri.' }}</p>
        </div>

        <div class="flex items-center justify-end gap-2 shrink-0">
            <div class="hidden lg:flex flex-wrap items-center gap-2">
                <span title="Manuel kasa hareketi muhasebe kayıtlarına yansımadığı için şimdilik devre dışı" class="cursor-not-allowed rounded-xl bg-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-500 select-none">+ Hareket Ekle</span>
                <a href="{{ route('cash-boxes.edit', $cashBox) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
            </div>

            <details class="lg:hidden relative group">
                <summary class="cursor-pointer list-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 flex items-center justify-end gap-2 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    İşlem
                </summary>
                <div class="absolute right-0 top-full mt-2 w-56 rounded-2xl bg-white p-3 shadow-lg ring-1 ring-slate-100 flex flex-col gap-2 z-20">
                    <span title="Manuel kasa hareketi muhasebe kayıtlarına yansımadığı için şimdilik devre dışı" class="block w-full cursor-not-allowed rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-400 text-left select-none">+ Hareket Ekle</span>
                    <a href="{{ route('cash-boxes.edit', $cashBox) }}" class="block w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-left hover:bg-slate-50">Düzenle</a>
                </div>
            </details>

            <button type="button" onclick="history.back()" class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-xl border border-slate-300 px-3 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:px-4 sm:text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h14" />
                </svg>
                Geri
            </button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="mb-6 grid grid-cols-3 gap-2 rounded-2xl bg-white py-4 px-3 shadow-sm md:gap-4 md:bg-transparent md:p-0 md:shadow-none">
        <div class="text-center md:rounded-2xl md:bg-white md:p-5 md:shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gelir</div>
            <div class="mt-1 text-sm font-bold text-emerald-600 tabular-nums md:mt-2 md:text-xl">{{ number_format($income, 2, ',', '.') }} TL</div>
        </div>
        <div class="text-center md:rounded-2xl md:bg-white md:p-5 md:shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gider</div>
            <div class="mt-1 text-sm font-bold text-red-600 tabular-nums md:mt-2 md:text-xl">{{ number_format($expense, 2, ',', '.') }} TL</div>
        </div>
        <div class="text-center md:rounded-2xl md:bg-white md:p-5 md:shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bakiye</div>
            <div class="mt-1 text-sm font-bold tabular-nums md:mt-2 md:text-xl {{ $balance >= 0 ? 'text-slate-900' : 'text-red-600' }}">{{ number_format($balance, 2, ',', '.') }} TL</div>
        </div>
    </div>

    {{-- Info --}}
    @if ($cashBox->bank_name || $cashBox->iban || $cashBox->account_number)
    <div class="mb-6 rounded-2xl bg-white py-6 px-3 md:p-6 shadow-sm">
        <div class="grid gap-4 md:grid-cols-3 text-sm">
            @if ($cashBox->bank_name)
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Banka</div>
                <div class="mt-1 text-slate-900">{{ $cashBox->bank_name }}</div>
            </div>
            @endif
            @if ($cashBox->account_number)
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hesap No</div>
                <div class="mt-1 text-slate-900 tabular-nums">{{ $cashBox->account_number }}</div>
            </div>
            @endif
            @if ($cashBox->iban)
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">IBAN</div>
                <div class="mt-1 text-slate-900 tabular-nums">{{ $cashBox->iban }}</div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Transactions Table --}}
    <div class="rounded-2xl bg-white py-6 px-3 md:p-6 shadow-sm">
        <h2 class="mb-4 text-base font-semibold text-slate-950">Hareketler</h2>
        @if ($transactions->isEmpty())
            <div class="py-6 text-sm text-slate-500">Bu kasaya ait henüz hareket yok.</div>
        @else
            {{-- Desktop Table --}}
            <div class="hidden md:block overflow-hidden rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Tarih</th>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</th>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Hesap / Kategori</th>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">Gelir</th>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">Gider</th>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">Bakiye</th>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($transactions as $t)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-4 text-slate-700 whitespace-nowrap">{{ $t->transaction_date->format('d.m.Y') }}</td>
                            <td class="px-5 py-4 text-slate-900">
                                {{ $t->description ?: ($t->type === 'income' ? 'Gelir' : 'Gider') }}
                                @if ($t->reference_number)
                                    <div class="text-xs text-slate-400 mt-0.5">{{ $t->reference_number }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-500 text-xs">
                                @if ($t->account) {{ $t->account->name }} @endif
                                @if ($t->account && $t->category) · @endif
                                @if ($t->category) {{ $t->category->name }} @endif
                                @if (!$t->account && !$t->category) - @endif
                            </td>
                            <td class="px-5 py-4 text-right font-semibold text-emerald-600 tabular-nums">
                                {{ $t->type === 'income' ? number_format($t->amount, 2, ',', '.') . ' TL' : '-' }}
                            </td>
                            <td class="px-5 py-4 text-right font-semibold text-red-600 tabular-nums">
                                {{ $t->type === 'expense' ? number_format($t->amount, 2, ',', '.') . ' TL' : '-' }}
                            </td>
                            <td class="px-5 py-4 text-right font-semibold tabular-nums {{ $t->running_balance >= 0 ? 'text-slate-900' : 'text-red-600' }}">
                                {{ number_format($t->running_balance, 2, ',', '.') }} TL
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ $t->detail_url }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Detay</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards --}}
            <div class="md:hidden space-y-4">
                @foreach ($transactions as $t)
                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs text-slate-500">{{ $t->transaction_date->format('d.m.Y') }}</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">
                                {{ $t->description ?: ($t->type === 'income' ? 'Gelir' : 'Gider') }}
                            </div>
                            @if ($t->reference_number)
                                <div class="text-xs text-slate-400 mt-0.5">{{ $t->reference_number }}</div>
                            @endif
                        </div>
                        <a href="{{ $t->detail_url }}" class="shrink-0 whitespace-nowrap rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Detay</a>
                    </div>
                    <div class="mt-2 text-xs text-slate-500">
                        @if ($t->account) {{ $t->account->name }} @endif
                        @if ($t->account && $t->category) · @endif
                        @if ($t->category) {{ $t->category->name }} @endif
                        @if (!$t->account && !$t->category) - @endif
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                        <div>
                            <div class="text-xs text-slate-500">Gelir</div>
                            <div class="font-semibold text-emerald-600">
                                {{ $t->type === 'income' ? number_format($t->amount, 2, ',', '.') . ' TL' : '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500">Gider</div>
                            <div class="font-semibold text-red-600">
                                {{ $t->type === 'expense' ? number_format($t->amount, 2, ',', '.') . ' TL' : '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500">Bakiye</div>
                            <div class="font-semibold {{ $t->running_balance >= 0 ? 'text-slate-900' : 'text-red-600' }}">
                                {{ number_format($t->running_balance, 2, ',', '.') }} TL
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Sayfalama --}}
    @if ($transactions->hasPages())
        <div class="mt-6">
            {{ $transactions->links() }}
        </div>
    @endif
@endsection
