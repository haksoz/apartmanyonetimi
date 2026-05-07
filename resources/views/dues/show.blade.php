@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Aidat Detayı</h1>
            <p class="mt-1 text-sm text-slate-500">Aidat kaydı ve ödeme bilgileri.</p>
        </div>
        <a href="{{ route('dues.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Aidatlara Dön</a>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm p-6">
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hesap</h2>
                <p class="mt-2 text-sm text-slate-900">{{ $due->account?->name ?? '-' }}</p>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Daire No</h2>
                <p class="mt-2 text-sm text-slate-900">{{ $due->unit ? str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-' }}</p>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Oluşturulma Tarihi</h2>
                <p class="mt-2 text-sm text-slate-900">{{ $due->created_at->format('d.m.Y H:i') }}</p>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dönem</h2>
                <p class="mt-2 text-sm text-slate-900">{{ $due->period }}</p>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Son Ödeme Tarihi</h2>
                <p class="mt-2 text-sm text-slate-900">{{ $due->due_date?->format('d.m.Y') ?? '-' }}</p>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tutar</h2>
                <p class="mt-2 text-sm text-slate-900">{{ number_format($due->amount, 2, ',', '.') }} TL</p>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kalan Tutar</h2>
                <p class="mt-2 text-sm text-slate-900">{{ number_format($due->remaining_amount, 2, ',', '.') }} TL</p>
            </div>
            <div class="sm:col-span-2 lg:col-span-1">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</h2>
                <p class="mt-2 text-sm text-slate-900">{{ $due->description ?: '-' }}</p>
            </div>
            <div class="sm:col-span-2 lg:col-span-1">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</h2>
                <p class="mt-2 text-sm font-semibold {{ $due->status === 'paid' ? 'text-emerald-600' : 'text-slate-900' }}">{{ ucfirst($due->status) }}</p>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            @if ($due->status !== 'paid')
                <a href="{{ route('dues.payment.create', $due) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Ödeme Ekle</a>
            @endif
            <a href="{{ route('dues.edit', $due) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm mt-6">
        <h2 class="mb-4 text-lg font-semibold text-slate-950">Ödeme Tahsisleri</h2>
        @if ($due->allocations->isEmpty())
            <div class="py-6 text-sm text-slate-500">Bu borca henüz tahsis edilmiş ödeme yok.</div>
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Ödeme</th>
                            <th class="px-5 py-3">Tutar</th>
                            <th class="px-5 py-3">Ödeme Tarihi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($due->allocations as $allocation)
                            <tr>
                                <td class="px-5 py-4 text-slate-700"><a href="{{ route('payments.show', $allocation->payment) }}" class="underline hover:text-slate-700">#{{ $allocation->payment->id }} - {{ $allocation->payment->description ?: 'Ödeme' }}</a></td>
                                <td class="px-5 py-4 text-slate-700">{{ number_format($allocation->amount, 2, ',', '.') }} TL</td>
                                <td class="px-5 py-4 text-slate-700">{{ $allocation->payment->payment_date?->format('d.m.Y') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
