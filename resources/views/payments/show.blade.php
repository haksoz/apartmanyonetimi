@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Ödeme Detayı</h1>
            <p class="mt-1 text-sm text-slate-500">Ödemenin genel bilgileri ve hangi aidatlara tahsis edildiği.</p>
        </div>
        <a href="{{ route('accounts.show', $payment->account) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Hesaba Dön</a>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
        <div class="grid gap-4 lg:grid-cols-3">
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hesap</h2>
                <p class="mt-2 text-sm text-slate-900">{{ $payment->account?->name ?? '-' }}</p>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tutar</h2>
                <p class="mt-2 text-sm text-slate-900">{{ number_format($payment->amount, 2, ',', '.') }} TL</p>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kalan Tahsis Edilmemiş</h2>
                <p class="mt-2 text-sm font-semibold text-slate-900">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</p>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ödeme Tarihi</h2>
                <p class="mt-2 text-sm text-slate-900">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</p>
            </div>
            <div class="lg:col-span-3">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</h2>
                <p class="mt-2 text-sm text-slate-900">{{ $payment->description ?: '-' }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="mb-4 text-lg font-semibold text-slate-950">Tahsis Edilen Borçlar</h2>
            @if ($payment->unallocated_amount > 0)
                <a href="{{ route('payments.allocations.create', $payment) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Tahsis Et</a>
            @endif
        </div>

        @if ($payment->allocations->isEmpty())
            <div class="py-6 text-sm text-slate-500">Bu ödeme henüz herhangi bir borca tahsis edilmedi.</div>
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-200 mb-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Aidat</th>
                            <th class="px-5 py-3">Tahsis Edilen</th>
                            <th class="px-5 py-3">Borç Durumu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($payment->allocations as $allocation)
                            <tr>
                                <td class="px-5 py-4 text-slate-700"><a href="{{ route('dues.show', $allocation->due) }}" class="underline hover:text-slate-700">#{{ $allocation->due->id }} - {{ $allocation->due->description ?: 'Aidat' }}</a></td>
                                <td class="px-5 py-4 text-slate-700">{{ number_format($allocation->amount, 2, ',', '.') }} TL</td>
                                <td class="px-5 py-4 text-slate-700">{{ ucfirst($allocation->due->status) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-950">Muhasebe Hareketleri</h2>
            @if ($payment->transactions->isEmpty())
                <div class="py-6 text-sm text-slate-500">Bu ödeme için kayıtlı muhasebe hareketi bulunamadı.</div>
            @else
                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Açıklama</th>
                                <th class="px-5 py-3">Tarih</th>
                                <th class="px-5 py-3 text-right">Tutar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($payment->transactions as $transaction)
                                <tr>
                                    <td class="px-5 py-4 text-slate-700">{{ $transaction->description ?: ucfirst($transaction->type) }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $transaction->transaction_date?->format('d.m.Y') ?? '-' }}</td>
                                    <td class="px-5 py-4 text-right font-semibold {{ $transaction->type === 'debit' ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($transaction->amount, 2, ',', '.') }} TL</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
