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
                @if ($due->account)
                    @php
                        $title = match($due->account->type) {
                            App\Models\Account::TYPE_OWNER => 'Kat Maliki',
                            App\Models\Account::TYPE_TENANT => 'Kiracı',
                            App\Models\Account::TYPE_SUPPLIER => 'Tedarikçi',
                            default => ''
                        };
                    @endphp
                    @if ($title)
                        <span class="font-semibold">{{ $title }}</span> -
                    @endif
                    {{ $due->account->name }}
                @else
                    -
                @endif
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
        <div class="space-y-4">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</div>
                <div class="mt-2 text-sm text-slate-900">{{ $due->description ?: '-' }}</div>
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                <div class="flex items-center gap-1">
                    <span class="text-xs text-slate-400">KATEGORİ</span>
                    <span class="text-slate-300">:</span>
                    <span class="font-medium text-slate-700">{{ $due->category?->name ?? '-' }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="text-xs text-slate-400">OLUŞTURULMA TARİHİ</span>
                    <span class="text-slate-300">:</span>
                    <span class="font-medium text-slate-700">{{ $due->created_at_manual ? \Carbon\Carbon::parse($due->created_at_manual)->format('d.m.Y') : $due->created_at->format('d.m.Y') }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="text-xs text-slate-400">DURUM</span>
                    <span class="text-slate-300">:</span>
                    @php
                        $isOverdue = $due->status !== 'paid' && $due->due_date && $due->due_date->isPast();
                        if ($isOverdue) {
                            $statusInfo = ['label' => 'Gecikmiş', 'class' => 'bg-red-50 text-red-600 border border-red-200'];
                        } elseif ($due->status === 'paid') {
                            $statusInfo = ['label' => 'Ödendi', 'class' => 'bg-emerald-50 text-emerald-600 border border-emerald-200'];
                        } elseif ($due->status === 'partial') {
                            $statusInfo = ['label' => 'Kısmen Ödendi', 'class' => 'bg-amber-50 text-amber-600 border border-amber-200'];
                        } else {
                            $statusInfo = ['label' => 'Bekliyor', 'class' => 'bg-slate-50 text-slate-600 border border-slate-200'];
                        }
                    @endphp
                    <span class="inline-flex rounded-lg px-2 py-0.5 text-xs font-semibold {{ $statusInfo['class'] }}">
                        {{ $statusInfo['label'] }}
                    </span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="text-xs text-slate-400">KAYNAK</span>
                    <span class="text-slate-300">:</span>
                    @if ($due->batch?->plan)
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-violet-50 px-2.5 py-0.5 text-xs font-semibold text-violet-700 border border-violet-200">
                            Aidat Planı: {{ $due->batch->plan->name }}
                        </span>
                    @elseif ($due->batch)
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-600 border border-slate-200">
                            Toplu Borçlandırma
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-600 border border-slate-200">
                            Manuel
                        </span>
                    @endif
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
