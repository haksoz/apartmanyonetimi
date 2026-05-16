@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">{{ $cashBox->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $cashBox->description ?: 'Kasa hareketleri' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <span title="Manuel kasa hareketi muhasebe kayıtlarına yansımadığı için şimdilik devre dışı" class="cursor-not-allowed flex-1 md:flex-none rounded-xl bg-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-500 text-center select-none">+ Hareket Ekle</span>
            <a href="{{ route('cash-boxes.edit', $cashBox) }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Düzenle</a>
            <a href="{{ route('cash.index') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Kasalara Dön</a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Toplam Gelir</div>
            <div class="mt-2 text-xl font-bold text-emerald-600 tabular-nums">{{ number_format($income, 2, ',', '.') }} TL</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Toplam Gider</div>
            <div class="mt-2 text-xl font-bold text-red-600 tabular-nums">{{ number_format($expense, 2, ',', '.') }} TL</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bakiye</div>
            <div class="mt-2 text-xl font-bold tabular-nums {{ $balance >= 0 ? 'text-slate-900' : 'text-red-600' }}">{{ number_format($balance, 2, ',', '.') }} TL</div>
        </div>
    </div>

    {{-- Info --}}
    @if ($cashBox->bank_name || $cashBox->iban || $cashBox->account_number)
    <div class="mb-6 rounded-2xl bg-white p-6 shadow-sm">
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
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-base font-semibold text-slate-950">Hareketler</h2>
        @if ($transactions->isEmpty())
            <div class="py-6 text-sm text-slate-500">Bu kasaya ait henüz hareket yok.</div>
        @else
            <div class="overflow-hidden rounded-xl border border-slate-200">
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
                                @if ($t->detail_url)
                                    <a href="{{ $t->detail_url }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Detay</a>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
