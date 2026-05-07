@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">{{ $account->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $account->unit ? $account->unit->unit_no.' no.lu daire' : 'Daire bağlantısı yok' }}</p>
        </div>
        <a href="{{ route('payments.create') }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Ödeme Al</a>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Borç</div><div class="mt-2 text-2xl font-bold">{{ number_format($account->ledger_debit, 2, ',', '.') }} TL</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Alacak</div><div class="mt-2 text-2xl font-bold">{{ number_format($account->ledger_credit, 2, ',', '.') }} TL</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Bakiye</div><div class="mt-2 text-2xl font-bold">{{ number_format($account->ledger_balance, 2, ',', '.') }} TL</div></div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
        <div class="flex items-center justify-between">
            <h2 class="mb-4 text-lg font-semibold text-slate-950">Açık Aidatlar</h2>
            <a href="{{ route('dues.index') }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Aidatlar</a>
        </div>
        @if ($account->dues->isEmpty())
            <div class="py-6 text-sm text-slate-500">Bu hesap için ödenmemiş aidat yok.</div>
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Tarih</th>
                            <th class="px-5 py-3">Açıklama</th>
                            <th class="px-5 py-3 text-right">Tutar</th>
                            <th class="px-5 py-3 text-right">Durum</th>
                            <th class="px-5 py-3 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($account->dues as $due)
                            <tr>
                                <td class="px-5 py-4 text-slate-700">{{ $due->due_date->format('d.m.Y') }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $due->description ?: 'Aidat' }}</td>
                                <td class="px-5 py-4 text-right text-slate-900 font-semibold">{{ number_format($due->amount, 2, ',', '.') }} TL</td>
                                <td class="px-5 py-4 text-right text-amber-600">{{ ucfirst($due->status) }}</td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('dues.payment.create', $due) }}" class="rounded-xl bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Öde</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($account->payments->isNotEmpty())
        <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
            <div class="flex items-center justify-between">
                <h2 class="mb-4 text-lg font-semibold text-slate-950">Dağıtılmamış Ödemeler</h2>
                <a href="{{ route('dues.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Aidatlara Dön</a>
            </div>
            <div class="overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3">#</th>
                            <th class="px-5 py-3">Tarih</th>
                            <th class="px-5 py-3">Açıklama</th>
                            <th class="px-5 py-3 text-right">Kalan</th>
                            <th class="px-5 py-3 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($account->payments as $payment)
                            <tr>
                                <td class="px-5 py-4 text-slate-700">{{ $payment->id }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $payment->description ?: 'Ödeme' }}</td>
                                <td class="px-5 py-4 text-right text-slate-900 font-semibold">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</td>
                                <td class="px-5 py-4 text-right space-x-2">
                                    <a href="{{ route('payments.show', $payment) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detay</a>
                                    <a href="{{ route('payments.allocations.create', $payment) }}" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Tahsis Et</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-slate-950">Hareketler</h2>
        <div class="divide-y divide-slate-100">
            @forelse ($account->transactions as $transaction)
                <div class="flex items-center justify-between py-3 text-sm">
                    <div>
                        <div class="font-medium text-slate-950">{{ $transaction->description ?: ucfirst($transaction->type) }}</div>
                        <div class="text-slate-500">{{ $transaction->transaction_date->format('d.m.Y') }}</div>
                    </div>
                    <div class="font-semibold {{ $transaction->type === 'debit' ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($transaction->amount, 2, ',', '.') }} TL</div>
                </div>
            @empty
                <div class="py-6 text-sm text-slate-500">Henüz hareket yok.</div>
            @endforelse
        </div>
    </div>
@endsection
