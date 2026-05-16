@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Ödeme Detayı</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $payment->reference_number ?? 'Ödeme' }}
                @if ($payment->account)
                    <span class="mx-1 text-slate-300">&bull;</span>{{ $payment->account->name }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($payment->unallocated_amount > 0)
                <a href="{{ route('payments.allocations.create', $payment) }}" class="flex-1 md:flex-none rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-emerald-700">Tahsis Et</a>
            @endif
            <form method="POST" action="{{ route('payments.destroy', $payment) }}" onsubmit="return confirm('Ödeme kaydı ve tüm tahsisler silinsin mi? Bu işlem geri alınamaz.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="flex-1 md:flex-none rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Ödemeyi Sil</button>
            </form>
            <a href="{{ route('accounts.show', $payment->account) }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Hesaba Dön</a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ödeme Tutarı</div>
            <div class="mt-2 text-xl font-bold text-slate-900">{{ number_format($payment->amount, 2, ',', '.') }} TL</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tahsis Edilen</div>
            <div class="mt-2 text-xl font-bold text-emerald-600">{{ number_format($payment->allocated_amount, 2, ',', '.') }} TL</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kalan (Dağıtılmamış)</div>
            <div class="mt-2 text-xl font-bold {{ $payment->unallocated_amount > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ödeme Tarihi</div>
            <div class="mt-2 text-xl font-bold text-slate-900">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</div>
        </div>
    </div>

    {{-- Info Card --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
        <div class="grid gap-6 md:grid-cols-3">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hesap</div>
                <div class="mt-2 text-sm font-medium text-slate-900">
                    @if ($payment->account)
                        <a href="{{ route('accounts.show', $payment->account) }}" class="hover:text-emerald-600 hover:underline">{{ $payment->account->name }}</a>
                    @else
                        -
                    @endif
                </div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Referans No</div>
                <div class="mt-2 text-sm font-medium text-slate-900 tabular-nums">{{ $payment->reference_number ?? '-' }}</div>
            </div>
            <div class="md:col-span-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</div>
                <div class="mt-2 text-sm text-slate-900">{{ $payment->description ?: '-' }}</div>
            </div>
        </div>
    </div>

    {{-- Allocations Section --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
        <h2 class="text-lg font-semibold text-slate-950 mb-4">Tahsis Edilen Borçlar</h2>
        @if ($payment->allocations->isEmpty())
            <div class="py-6 text-sm text-slate-500">Bu ödeme henüz herhangi bir borca tahsis edilmedi.</div>
        @else
            <div class="overflow-hidden rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Ref No</th>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</th>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">Tahsis Edilen</th>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Borç Durumu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($payment->allocations as $allocation)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-5 py-4">
                                    <a href="{{ route('dues.show', $allocation->due) }}" class="font-medium text-slate-900 hover:text-emerald-600">{{ $allocation->due->reference_number ?? '#'.$allocation->due->id }}</a>
                                </td>
                                <td class="px-5 py-4 text-slate-700">{{ $allocation->due->description ?: 'Aidat' }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($allocation->amount, 2, ',', '.') }} TL</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $allocation->due->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($allocation->due->status === 'partial' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700') }}">
                                        {{ $allocation->due->status === 'paid' ? 'Ödendi' : ($allocation->due->status === 'partial' ? 'Kısmen Ödendi' : 'Bekliyor') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Accounting Transactions Section --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-950 mb-4">Muhasebe Hareketleri</h2>
        @if ($payment->transactions->isEmpty())
            <div class="py-6 text-sm text-slate-500">Bu ödeme için kayıtlı muhasebe hareketi bulunamadı.</div>
        @else
            <div class="overflow-hidden rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</th>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Tarih</th>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Tip</th>
                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($payment->transactions as $transaction)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-5 py-4 text-slate-700">{{ $transaction->description ?: ($transaction->type === 'debit' ? 'Borç' : 'Alacak') }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $transaction->transaction_date?->format('d.m.Y') ?? '-' }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $transaction->type === 'debit' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        {{ $transaction->type === 'debit' ? 'Borç' : 'Alacak' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right font-semibold {{ $transaction->type === 'debit' ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($transaction->amount, 2, ',', '.') }} TL</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
