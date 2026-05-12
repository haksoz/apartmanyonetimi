@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Borç - Aidat Detayı</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $due->reference_number ?? '' }}</p>
        </div>
        <div class="flex gap-2">
            @if ($due->status !== 'paid')
                <a href="{{ route('dues.payment.create', $due) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Ödeme Ekle</a>
            @endif
            <a href="{{ route('dues.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Aidatlara Dön</a>
        </div>
    </div>

    {{-- Status & Summary Cards --}}
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Borç Tutarı</div>
            <div class="mt-2 text-xl font-bold text-red-600">{{ number_format($due->amount, 2, ',', '.') }} TL</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ödenen</div>
            @php
                $paidAmount = $due->amount - $due->remaining_amount;
            @endphp
            <div class="mt-2 text-xl font-bold text-emerald-600">{{ number_format($paidAmount, 2, ',', '.') }} TL</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kalan Borç</div>
            <div class="mt-2 text-xl font-bold {{ $due->remaining_amount > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ number_format($due->remaining_amount, 2, ',', '.') }} TL</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Son Ödeme Tarihi</div>
            <div class="mt-2 text-xl font-bold text-slate-900">{{ $due->due_date?->format('d.m.Y') ?? '-' }}</div>
        </div>
    </div>

    {{-- Info Card --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
        <div class="grid gap-6 md:grid-cols-3">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hesap</div>
                <div class="mt-2 text-sm font-medium text-slate-900">{{ $due->account?->name ?? '-' }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Daire No</div>
                <div class="mt-2 text-sm font-medium text-slate-900">{{ $due->unit ? str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-' }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dönem</div>
                <div class="mt-2 text-sm font-medium text-slate-900">{{ $due->period }}</div>
            </div>
            <div class="md:col-span-2">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</div>
                <div class="mt-2 text-sm text-slate-900">{{ $due->description ?: '-' }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</div>
                <div class="mt-2">
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $due->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($due->status === 'partial' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                        {{ $due->status === 'paid' ? 'Ödendi' : ($due->status === 'partial' ? 'Kısmen Ödendi' : 'Ödenmedi') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Payment Allocations Section --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-950 mb-4">Ödeme Tahsisleri</h2>
        @if ($due->allocations->isEmpty())
            <div class="py-6 text-sm text-slate-500">Bu borca henüz tahsis edilmiş ödeme yok.</div>
        @else
            <div class="overflow-hidden rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Ref No</th>
                            <th class="px-5 py-3">Açıklama</th>
                            <th class="px-5 py-3 text-right">Tahsis Edilen</th>
                            <th class="px-5 py-3">Ödeme Tarihi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($due->allocations as $allocation)
                            <tr>
                                <td class="px-5 py-4">
                                    <a href="{{ route('payments.show', $allocation->payment) }}" class="font-medium text-slate-900 hover:text-emerald-600">{{ $allocation->payment->reference_number ?? '#'.$allocation->payment->id }}</a>
                                </td>
                                <td class="px-5 py-4 text-slate-700">{{ $allocation->payment->description ?: 'Ödeme' }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-slate-900">{{ number_format($allocation->amount, 2, ',', '.') }} TL</td>
                                <td class="px-5 py-4 text-slate-700">{{ $allocation->payment->payment_date?->format('d.m.Y') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
