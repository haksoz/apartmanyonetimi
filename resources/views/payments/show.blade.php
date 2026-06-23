@extends('layouts.app')



@section('content')

    @php

        $isTahsilat = $payment->account && in_array($payment->account->type, [App\Models\Account::TYPE_OWNER, App\Models\Account::TYPE_TENANT]);

        $label = $isTahsilat ? 'Tahsilat' : 'Ödeme';

        $labelLower = $isTahsilat ? 'tahsilat' : 'ödeme';

        $labelAccusative = $isTahsilat ? 'Tahsilatı' : 'Ödemeyi';

    @endphp

    {{-- Header --}}

    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">

        <div>

            <h1 class="text-2xl font-bold text-slate-950">{{ $label }} Detayı <span class="text-slate-400 font-normal text-lg">— Ödeme Hareketleri</span></h1>

            <p class="mt-1 text-sm text-slate-500">

                {{ $payment->reference_number ?? $label }}

                @if ($payment->account)

                    <span class="mx-1 text-slate-300">&bull;</span>{{ $payment->account->name }}

                @endif

            </p>

        </div>

        <div class="flex flex-wrap gap-2">

            @if ($payment->unallocated_amount > 0)

                <a href="{{ $isTahsilat ? route('payments.allocations.create', $payment) : route('payments.supplier-allocations.create', $payment) }}" class="flex-1 md:flex-none rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-emerald-700">Tahsis Et</a>

            @endif

            <a href="{{ route('payments.edit', $payment) }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ $labelAccusative }} Düzenle</a>

            <form method="POST" action="{{ route('payments.destroy', $payment) }}" onsubmit="return confirm('{{ $label }} kaydı ve tüm tahsisler silinsin mi? Bu işlem geri alınamaz.')">

                @csrf

                @method('DELETE')

                <button type="submit" class="flex-1 md:flex-none rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">{{ $labelAccusative }} Sil</button>

            </form>

            <a href="{{ route('accounts.show', $payment->account) }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Hesaba Dön</a>

        </div>

    </div>



    {{-- Info Card --}}

    @php $cashTx = $payment->cashTransactions->first(); @endphp

    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">

        <div class="grid grid-cols-2 md:grid-cols-3 gap-6">

            @if ($payment->account)

            <div>

                <div class="text-xs text-slate-400 mb-1">HESAP</div>

                @php
                    $accountTypeLabel = match($payment->account->type) {
                        App\Models\Account::TYPE_OWNER    => 'Kat Maliki',
                        App\Models\Account::TYPE_TENANT   => 'Kiracı',
                        App\Models\Account::TYPE_SUPPLIER => 'Tedarikçi',
                        default => '',
                    };
                @endphp

                <div class="text-sm font-medium text-slate-900">
                    @if ($accountTypeLabel){{ $accountTypeLabel }} @endif
                    <a href="{{ route('accounts.show', $payment->account) }}" class="hover:text-emerald-600 hover:underline">{{ $payment->account->name }}</a>
                    @if ($payment->account->unit)
                         - Daire {{ str_pad($payment->account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}
                    @endif
                </div>

            </div>

            @endif

            <div>

                <div class="text-xs text-slate-400 mb-1">TUTAR</div>

                <div class="text-sm font-bold text-slate-900">{{ number_format($payment->amount, 2, ',', '.') }} TL</div>

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">BAĞLANAN</div>

                <div class="text-sm font-bold text-emerald-600">{{ number_format($payment->allocated_amount, 2, ',', '.') }} TL</div>

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">KALAN BAKİYE</div>

                <div class="text-sm font-bold {{ $payment->unallocated_amount > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</div>

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">{{ strtoupper($label) }} TARİHİ</div>

                <div class="text-sm font-medium text-slate-900">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</div>

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">DURUM</div>

                @if ($payment->unallocated_amount <= 0)
                    <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold bg-emerald-50 text-emerald-600 border border-emerald-200">Tam Tahsis</span>
                @elseif ($payment->allocated_amount > 0)
                    <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold bg-amber-50 text-amber-600 border border-amber-200">Kısmen Tahsis</span>
                @else
                    <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold bg-slate-50 text-slate-600 border border-slate-200">Tahsis Bekliyor</span>
                @endif

            </div>

            @if ($payment->reference_number)

            <div>

                <div class="text-xs text-slate-400 mb-1">REFERANS</div>

                <div class="text-sm font-medium text-slate-900">{{ $payment->reference_number }}</div>

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

                <div class="text-sm font-medium text-slate-900">{{ $payment->description ?: '-' }}</div>

            </div>

        </div>

    </div>



    {{-- Allocations Section --}}

    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">

        <h2 class="text-lg font-semibold text-slate-950 mb-4">{{ $isTahsilat ? 'Bağlı Borçlar' : 'Bağlı Giderler' }}</h2>

        @if ($payment->allocations->isEmpty())

            <div class="py-6 text-sm text-slate-500">Bu {{ $labelLower }} henüz herhangi bir borca tahsis edilmedi.</div>

        @else

            <div class="overflow-hidden rounded-xl border border-slate-200">

                <table class="min-w-full divide-y divide-slate-200 text-sm">

                    <thead class="bg-slate-50 text-left">

                        <tr>

                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Ref / No</th>

                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $isTahsilat ? 'Borç / Açıklama' : 'Gider / Açıklama' }}</th>

                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">Bağlanan Tutar</th>

                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</th>

                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500"></th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        @foreach ($payment->allocations as $allocation)

                            <tr class="hover:bg-slate-50 transition-colors">

                                @if ($allocation->due_id && $allocation->due)

                                    <td class="px-5 py-4">

                                        <a href="{{ route('dues.show', $allocation->due) }}" class="font-medium text-slate-900 hover:text-emerald-600">{{ $allocation->due->reference_number ?? '#'.$allocation->due->id }}</a>

                                    </td>

                                    <td class="px-5 py-4 text-slate-700">
                                        <span class="inline-block rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700 mr-1">Aidat</span>
                                        {{ $allocation->due->description ?: 'Aidat' }}
                                    </td>

                                    <td class="px-5 py-4 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($allocation->amount, 2, ',', '.') }} TL</td>

                                    <td class="px-5 py-4">

                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $allocation->due->computed_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($allocation->due->computed_status === 'partial' ? 'bg-amber-100 text-amber-700' : ($allocation->due->computed_status === 'overdue' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700')) }}">

                                            {{ $allocation->due->computed_status === 'paid' ? 'Ödendi' : ($allocation->due->computed_status === 'partial' ? 'Kısmen Ödendi' : ($allocation->due->computed_status === 'overdue' ? 'Gecikti' : 'Bekliyor')) }}

                                        </span>

                                    </td>

                                    <td class="px-5 py-4 text-right">
                                        <form method="POST" action="{{ route('payments.allocations.destroy', [$payment, $allocation]) }}" onsubmit="return confirm('Bu tahsis geri alınsın mı?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Geri Al</button>
                                        </form>
                                    </td>

                                @elseif ($allocation->expense_id && $allocation->expense)

                                    <td class="px-5 py-4">

                                        <a href="{{ route('expenses.show', $allocation->expense) }}" class="font-medium text-slate-900 hover:text-emerald-600">{{ $allocation->expense->reference_number ?? '#'.$allocation->expense->id }}</a>

                                    </td>

                                    <td class="px-5 py-4 text-slate-700">
                                        <span class="inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 mr-1">Gider</span>
                                        {{ $allocation->expense->description ?: ($allocation->expense->category ?? 'Gider') }}
                                    </td>

                                    <td class="px-5 py-4 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($allocation->amount, 2, ',', '.') }} TL</td>

                                    <td class="px-5 py-4">

                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $allocation->expense->is_paid ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">

                                            {{ $allocation->expense->is_paid ? 'Ödendi' : 'Açık' }}

                                        </span>

                                    </td>

                                    <td class="px-5 py-4 text-right">
                                        <form method="POST" action="{{ route('payments.allocations.destroy', [$payment, $allocation]) }}" onsubmit="return confirm('Bu tahsis geri alınsın mı?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Geri Al</button>
                                        </form>
                                    </td>

                                @endif

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        @endif

    </div>

@endsection

