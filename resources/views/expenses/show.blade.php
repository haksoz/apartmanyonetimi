@extends('layouts.app')



@section('content')

    @php

        $expensePayment = $expense->paymentAllocations->first()?->payment;

        $paymentTx = $expensePayment

            ? \App\Models\AccountTransaction::where('transactionable_type', \App\Models\Payment::class)

                ->where('transactionable_id', $expensePayment->id)

                ->where('type', 'debit')

                ->first()

            : $expense->transactions->firstWhere('type', 'debit');

        $cashTx = $expense->cashTransactions->first();

        $months = ['January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart', 'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',

                   'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül', 'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'];

        $periodText = $expense->period_month ? $expense->period_month->format('F Y') : null;

        if ($periodText) { foreach ($months as $en => $tr) { $periodText = str_replace($en, $tr, $periodText); } }

    @endphp



    {{-- Header --}}

    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">

        <div>

            <h1 class="text-2xl font-bold text-slate-950">Gider Detayı <span class="text-slate-400 font-normal text-lg">— Giderler</span></h1>

            <p class="mt-1 text-sm text-slate-500">

                {{ $expense->reference_number ?? 'Gider' }}

                @if ($expense->account)

                    <span class="mx-1 text-slate-300">&bull;</span><a href="{{ route('accounts.show', $expense->account) }}" class="hover:text-emerald-600">{{ $expense->account->name }}</a>

                @endif

            </p>

        </div>

        <div class="flex flex-wrap gap-2">

            @unless ($expense->is_paid)

                <a href="{{ route('expenses.payment.create', $expense) }}" class="flex-1 md:flex-none rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-emerald-700">Ödeme Ekle</a>

            @endunless

            <a href="{{ route('expenses.edit', $expense) }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Düzenle</a>

            @if ($expense->is_paid)

                <button type="button" onclick="alert('Bu gider ödenmiş olduğu için silinemez. Önce ödemeyi iptal edin.')" class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Sil</button>

            @else

                <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('Gider kaydı silinsin mi?')">

                    @csrf

                    @method('DELETE')

                    <button type="submit" class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Sil</button>

                </form>

            @endif

            <a href="{{ route('expenses.index') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Giderlere Dön</a>

        </div>

    </div>



    {{-- Info Card --}}

    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">

        <div class="grid grid-cols-2 md:grid-cols-3 gap-6">

            @if ($expense->account)

            <div>

                <div class="text-xs text-slate-400 mb-1">HESAP / TEDARİKÇİ</div>

                <div class="text-sm font-medium text-slate-900">
                    <a href="{{ route('accounts.show', $expense->account) }}" class="hover:text-emerald-600 hover:underline">{{ $expense->account->name }}</a>
                </div>

            </div>

            @endif

            <div>

                <div class="text-xs text-slate-400 mb-1">TUTAR</div>

                <div class="text-sm font-bold text-slate-900">{{ number_format($expense->amount, 2, ',', '.') }} TL</div>

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">ÖDENEN</div>

                <div class="text-sm font-bold text-emerald-600">{{ number_format($expense->paid_amount ?? 0, 2, ',', '.') }} TL</div>

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">KALAN</div>

                @php $remaining = $expense->remaining_amount ?? $expense->amount; @endphp

                <div class="text-sm font-bold {{ $remaining > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ number_format($remaining, 2, ',', '.') }} TL</div>

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">GİDER TARİHİ</div>

                <div class="text-sm font-medium text-slate-900">{{ $expense->expense_date->format('d.m.Y') }}</div>

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">SON ÖDEME TARİHİ</div>

                <div class="text-sm font-medium text-slate-900">{{ $expense->due_date?->format('d.m.Y') ?? '-' }}</div>

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">DURUM</div>

                @if ($expense->is_paid)
                    <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold bg-emerald-50 text-emerald-600 border border-emerald-200">Ödendi</span>
                @elseif (($expense->paid_amount ?? 0) > 0)
                    <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold bg-amber-50 text-amber-600 border border-amber-200">Kısmen Ödendi</span>
                @else
                    <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold bg-slate-50 text-slate-600 border border-slate-200">Bekliyor</span>
                @endif

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">KATEGORİ</div>

                <div class="text-sm font-medium text-slate-900">{{ $expense->categoryRelation?->name ?? $expense->category ?? '—' }}</div>

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">DÖNEM</div>

                <div class="text-sm font-medium text-slate-900">{{ $periodText ?? '-' }}</div>

            </div>

            @if ($expense->reference_number)

            <div>

                <div class="text-xs text-slate-400 mb-1">REFERANS</div>

                <div class="text-sm font-medium text-slate-900">{{ $expense->reference_number }}</div>

            </div>

            @endif

            @if ($cashTx)

            <div>

                <div class="text-xs text-slate-400 mb-1">KASA HAREKETİ</div>

                <div class="text-sm font-medium text-slate-900">
                    <a href="{{ route('cash.show', $cashTx) }}" class="text-blue-700 hover:text-blue-800 hover:underline">
                        {{ $cashTx->reference_number }} — {{ $cashTx->cashBox?->name }}
                    </a>
                </div>

            </div>

            @endif

            <div>

                <div class="text-xs text-slate-400 mb-1">AÇIKLAMA</div>

                <div class="text-sm font-medium text-slate-900">{{ $expense->description ?: '-' }}</div>

            </div>

        </div>

    </div>



    {{-- Payment Allocations Card --}}

    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">

        <div class="flex items-center justify-between mb-4">

            <h2 class="text-base font-semibold text-slate-950">Gideri Kapatan Ödemeler</h2>

            @if ($expense->is_paid)

                <form method="POST" action="{{ route('expenses.payment.destroy', $expense) }}" onsubmit="return confirm('Gider ödemesi silinsin mi? Gider tekrar ödenmemiş durumuna döner.')">

                    @csrf

                    @method('DELETE')

                    <button type="submit" class="rounded-xl border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Tümünü İptal Et</button>

                </form>

            @endif

        </div>

        @if ($expense->paymentAllocations->isEmpty())

            <div class="py-6 text-sm text-slate-500">Bu gidere henüz herhangi bir ödeme tahsis edilmedi.</div>

        @else

            <div class="overflow-hidden rounded-xl border border-slate-200">

                <table class="min-w-full divide-y divide-slate-200 text-sm">

                    <thead class="bg-slate-50 text-left text-slate-500">

                        <tr>

                            <th class="px-5 py-3">Ref No</th>

                            <th class="px-5 py-3">Açıklama</th>

                            <th class="px-5 py-3 text-right">Ödeme Tutarı</th>

                            <th class="px-5 py-3 text-right">Bağlanan</th>

                            <th class="px-5 py-3">Ödeme Tarihi</th>

                            <th class="px-5 py-3"></th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        @foreach ($expense->paymentAllocations as $allocation)

                            <tr>

                                <td class="px-5 py-4">

                                    <a href="{{ route('payments.show', $allocation->payment) }}" class="font-medium text-slate-900 hover:text-emerald-600">{{ $allocation->payment->reference_number ?? '#'.$allocation->payment->id }}</a>

                                </td>

                                <td class="px-5 py-4 text-slate-700">{{ $allocation->payment->description ?: 'Ödeme' }}</td>

                                <td class="px-5 py-4 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($allocation->payment->amount, 2, ',', '.') }} TL</td>

                                <td class="px-5 py-4 text-right font-semibold text-emerald-600 tabular-nums">{{ number_format($allocation->amount, 2, ',', '.') }} TL</td>

                                <td class="px-5 py-4 text-slate-700">{{ $allocation->payment->payment_date?->format('d.m.Y') ?? '-' }}</td>

                                <td class="px-5 py-4 text-right">

                                    <a href="{{ route('payments.show', $allocation->payment) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detay</a>

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        @endif

        @if ($cashTx)

            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-5 py-3">

                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Kasa Hareketi</div>

                <a href="{{ route('cash.show', $cashTx) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-800">

                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">

                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>

                    </svg>

                    {{ $cashTx->reference_number }} — {{ $cashTx->cashBox?->name }} — {{ number_format($cashTx->amount, 2, ',', '.') }} TL

                </a>

            </div>

        @endif

    </div>



@endsection

