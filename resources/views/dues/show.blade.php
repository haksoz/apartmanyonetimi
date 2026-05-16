@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Aidat Detayı
                @if ($due->unit)
                    <span class="font-normal text-slate-400">&mdash; {{ str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) }} No.lu Daire</span>
                @endif
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $due->account?->name ?? '-' }}
                @if ($due->reference_number)
                    <span class="mx-1 text-slate-300">&bull;</span>
                    {{ $due->reference_number }}
                @endif
            </p>
        </div>
        <div class="flex gap-2">
            @if ($due->status !== 'paid')
                <a href="{{ route('dues.payment.create', $due) }}" class="flex-1 md:flex-none rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-emerald-700">Tahsil Et</a>
            @endif
            <a href="{{ route('dues.edit', $due) }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Düzenle</a>
            @if (in_array($due->status, ['paid', 'partial']))
                <button type="button" onclick="alert('Bu aidat ödenmiş olduğu için silinemez. Önce ilgili ödemeleri iptal edin.')" class="flex-1 md:flex-none rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Sil</button>
            @else
                <form method="POST" action="{{ route('dues.destroy', $due) }}" onsubmit="return confirm('Aidat kaydı silinsin mi?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="flex-1 md:flex-none rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Sil</button>
                </form>
            @endif
            <a href="{{ route('dues.index') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Aidatlara Dön</a>
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
                <div class="mt-2 text-sm font-medium text-slate-900">
                    @if ($due->account)
                        <a href="{{ route('accounts.show', $due->account) }}" class="hover:text-emerald-600 hover:underline">{{ $due->account->name }}</a>
                    @else
                        -
                    @endif
                </div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Daire</div>
                <div class="mt-2 text-sm font-medium text-slate-900">{{ $due->unit ? str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT).' No.lu Daire' : '-' }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kategori</div>
                <div class="mt-2 text-sm font-medium text-slate-900">{{ $due->category?->name ?? '-' }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Referans No</div>
                <div class="mt-2 text-sm font-medium text-slate-900 tabular-nums">{{ $due->reference_number ?? '-' }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dönem</div>
                <div class="mt-2 text-sm font-medium text-slate-900">{{ $due->period }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Oluşturulma Tarihi</div>
                <div class="mt-2 text-sm font-medium text-slate-900">
                    {{ $due->created_at_manual ? \Carbon\Carbon::parse($due->created_at_manual)->format('d.m.Y') : $due->created_at->format('d.m.Y') }}
                </div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</div>
                <div class="mt-2">
                    @php
                        $statusMap = [
                            'paid'    => ['label' => 'Ödendi',        'class' => 'bg-emerald-100 text-emerald-700'],
                            'partial' => ['label' => 'Kısmen Ödendi', 'class' => 'bg-amber-100 text-amber-700'],
                            'overdue' => ['label' => 'Gecikmiş',      'class' => 'bg-red-100 text-red-700'],
                        ];
                        $statusInfo = $statusMap[$due->status] ?? ['label' => 'Bekliyor', 'class' => 'bg-slate-100 text-slate-700'];
                    @endphp
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusInfo['class'] }}">
                        {{ $statusInfo['label'] }}
                    </span>
                </div>
            </div>
            <div class="md:col-span-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</div>
                <div class="mt-2 text-sm text-slate-900">{{ $due->description ?: '-' }}</div>
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
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($due->allocations as $allocation)
                            <tr>
                                <td class="px-5 py-4">
                                    <a href="{{ route('payments.show', $allocation->payment) }}" class="font-medium text-slate-900 hover:text-emerald-600">{{ $allocation->payment->reference_number ?? '#'.$allocation->payment->id }}</a>
                                </td>
                                <td class="px-5 py-4 text-slate-700">{{ $allocation->payment->description ?: 'Ödeme' }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($allocation->amount, 2, ',', '.') }} TL</td>
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
    </div>
@endsection
