@extends('layouts.app')

@section('content')
    @php
        $paymentTx = $expense->transactions->firstWhere('type', 'debit');
        $cashTx = $expense->cashTransactions->first();
        $allocations = $expense->paymentAllocations()->with('payment')->get();
        $hasAllocations = $allocations->isNotEmpty();
        $totalAllocated = $allocations->sum('amount');
        $months = ['January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart', 'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',
                   'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül', 'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'];
        $periodText = $expense->period_month ? $expense->period_month->format('F Y') : null;
        if ($periodText) { foreach ($months as $en => $tr) { $periodText = str_replace($en, $tr, $periodText); } }
    @endphp

    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">
                Gider Detayı
                @if ($expense->is_imported)
                    <span class="ml-2 inline-block rounded-md bg-blue-100 px-2 py-1 text-sm font-medium text-blue-700">Devir Öncesi</span>
                @endif
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $expense->reference_number ?? 'Gider' }}
                @if ($expense->account)
                    <span class="mx-1 text-slate-300">&bull;</span>{{ $expense->account->name }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @unless ($expense->is_paid)
                <a href="{{ route('expenses.payment.create', $expense) }}" class="flex-1 md:flex-none rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-emerald-700">Ödeme Ekle</a>
            @endunless
            <a href="{{ route('expenses.edit', $expense) }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Düzenle</a>
            @if ($expense->is_paid || $hasAllocations)
                @php
                    $deleteWarning = $hasAllocations
                        ? 'Bu gider tahsis edilmiş ödemelerle bağlantılı. Önce tahsisleri iptal edin.'
                        : 'Bu gider ödenmiş olduğu için silinemez. Önce ödemeyi iptal edin.';
                @endphp
                <button type="button" onclick="alert('{{ $deleteWarning }}')" class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Sil</button>
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

    {{-- Summary Cards --}}
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gider Tutarı</div>
            <div class="mt-2 text-xl font-bold text-slate-900 tabular-nums">{{ number_format($expense->amount, 2, ',', '.') }} TL</div>
        </div>
        @if ($expense->is_imported && $expense->paid_amount !== null)
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Ödenen</div>
            <div class="mt-2 text-xl font-bold text-emerald-600 tabular-nums">{{ number_format($expense->paid_amount, 2, ',', '.') }} TL</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-amber-600">Kalan</div>
            <div class="mt-2 text-xl font-bold text-amber-600 tabular-nums">{{ number_format($expense->remaining_amount, 2, ',', '.') }} TL</div>
        </div>
        @else
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gider Tarihi</div>
            <div class="mt-2 text-xl font-bold text-slate-900 tabular-nums">{{ $expense->expense_date->format('d.m.Y') }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Son Ödeme Tarihi</div>
            <div class="mt-2 text-xl font-bold text-slate-900 tabular-nums">{{ $expense->due_date?->format('d.m.Y') ?? '-' }}</div>
        </div>
        @endif
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</div>
            <div class="mt-2">
                @if ($expense->is_paid)
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700">Ödendi</span>
                @elseif ($expense->is_imported && $expense->paid_amount > 0)
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-amber-100 text-amber-700">Kısmen Ödendi</span>
                @else
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-amber-100 text-amber-700">Bekliyor</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Payment Info Card --}}
    @if ($expense->is_paid || $hasAllocations || ($expense->is_imported && $expense->paid_amount > 0) || $cashTx)
    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-slate-950">Ödeme Bilgisi</h2>
            @if (!$hasAllocations && ($paymentTx || $cashTx || ($expense->is_imported && $expense->paid_amount > 0)))
                {{-- Direk ödeme yapılmışsa iptal edilebilir --}}
                <form method="POST" action="{{ route('expenses.payment.destroy', $expense) }}" onsubmit="return confirm('Gider ödemesi silinsin mi? Gider tekrar ödenmemiş durumuna döner.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-xl border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Ödemeyi İptal Et</button>
                </form>
            @endif
        </div>

        {{-- Tahsis edilmiş ödemeler --}}
        @if ($hasAllocations)
        <div class="mb-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Tahsis Edilmiş Ödemeler ({{ $allocations->count() }})</div>
            <div class="space-y-2">
                @foreach ($allocations as $allocation)
                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50 border border-slate-200">
                    <div class="flex items-center gap-3">
                        <div>
                            @if ($allocation->payment && $allocation->payment->account_id)
                                <a href="{{ route('accounts.show', $allocation->payment->account_id) }}" class="text-sm font-medium text-blue-700 hover:text-blue-800">
                                    {{ $allocation->payment->account?->name ?? 'Hesap' }}
                                </a>
                            @else
                                <span class="text-sm font-medium text-slate-700">{{ $allocation->payment->account?->name ?? 'Hesapsız Ödeme' }}</span>
                            @endif
                            <div class="text-xs text-slate-500">Ödeme: {{ $allocation->payment->reference_number ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <div class="text-sm font-semibold text-slate-900 tabular-nums">{{ number_format($allocation->amount, 2, ',', '.') }} TL</div>
                            <div class="text-xs text-slate-500">{{ $allocation->created_at->format('d.m.Y') }}</div>
                        </div>
                        {{-- Tahsis iptal butonu --}}
                        <form method="POST" action="{{ route('payments.allocations.destroy', ['payment' => $allocation->payment_id, 'allocation' => $allocation]) }}" onsubmit="return confirm('Tahsis geri alınsın mı? Ödeme tekrar tahsis edilebilir hale gelir.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-red-200 px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">İptal</button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="mt-3 flex justify-between items-center text-sm">
                <span class="text-slate-600">Toplam Tahsis:</span>
                <span class="font-semibold text-slate-900 tabular-nums">{{ number_format($totalAllocated, 2, ',', '.') }} TL</span>
            </div>
        </div>
        @endif

        {{-- Direk ödeme bilgisi --}}
        @if ($paymentTx || $cashTx)
        <div class="grid gap-6 md:grid-cols-3 {{ $hasAllocations ? 'border-t border-slate-200 pt-4' : '' }}">
            @if ($paymentTx)
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ödeme Tarihi</div>
                <div class="mt-2 text-sm font-medium text-slate-900 tabular-nums">{{ $paymentTx->transaction_date->format('d.m.Y') }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tutar</div>
                <div class="mt-2 text-sm font-medium text-slate-900 tabular-nums">{{ number_format($paymentTx->amount, 2, ',', '.') }} TL</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</div>
                <div class="mt-2 text-sm text-slate-900">{{ $paymentTx->description ?? '-' }}</div>
            </div>
            @endif
            @if ($cashTx)
            <div class="{{ $paymentTx ? 'md:col-span-3' : 'md:col-span-3' }}">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kasa Hareketi</div>
                <div class="mt-2">
                    <a href="{{ route('cash.show', $cashTx) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $cashTx->reference_number }} — {{ $cashTx->cashBox?->name }} — {{ number_format($cashTx->amount, 2, ',', '.') }} TL
                    </a>
                </div>
            </div>
            @endif
        </div>
        @endif
    </div>
    @endif

    {{-- Info Card --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
        <div class="grid gap-6 md:grid-cols-3">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hesap / Tedarikçi</div>
                <div class="mt-2 text-sm font-medium text-slate-900">
                    @if ($expense->account)
                        <a href="{{ route('accounts.show', $expense->account) }}" class="hover:text-emerald-600 hover:underline">{{ $expense->account->name }}</a>
                    @else
                        -
                    @endif
                </div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kategori</div>
                <div class="mt-2 text-sm font-medium text-slate-900">{{ $expense->category }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dönem</div>
                <div class="mt-2 text-sm font-medium text-slate-900">{{ $periodText ?? '-' }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Referans No</div>
                <div class="mt-2 text-sm font-medium text-slate-900 tabular-nums">{{ $expense->reference_number ?? '-' }}</div>
            </div>
            @if ($expense->is_paid && $paymentTx)
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ödeme Tarihi</div>
                <div class="mt-2 text-sm font-medium text-slate-900 tabular-nums">{{ $paymentTx->transaction_date->format('d.m.Y') }}</div>
            </div>
            @endif
            @if ($cashTx)
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kasa Hareketi</div>
                <div class="mt-2">
                    <a href="{{ route('cash.show', $cashTx) }}" class="text-sm font-medium text-blue-700 hover:text-blue-800 hover:underline">
                        {{ $cashTx->reference_number }}
                    </a>
                </div>
            </div>
            @endif
            <div class="{{ $expense->is_paid && $paymentTx && !$cashTx ? '' : ($cashTx ? '' : 'md:col-span-2') }} {{ $cashTx ? 'md:col-span-2' : 'md:col-span-3' }}">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</div>
                <div class="mt-2 text-sm text-slate-900">{{ $expense->description ?: '-' }}</div>
            </div>
        </div>
    </div>
@endsection
