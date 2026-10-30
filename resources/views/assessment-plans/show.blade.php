@extends('layouts.app')

@section('content')
    @php $editing = request()->boolean('edit') || $errors->any(); @endphp
    @include('assessment-plans._header')

    @if ($editing)
        @include('assessment-plans._edit')
        <div class="mb-6 flex justify-end">
            <a href="{{ route('assessment-plans.cancel-preview', $plan) }}" class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">Gelecek Taksitleri İptal Et</a>
        </div>
    @else
        @include('assessment-plans._details')
    @endif

    <h2 class="mb-3 text-base font-semibold text-slate-900">Taksitler</h2>
    <div class="mb-8 hidden overflow-x-auto rounded-2xl bg-white shadow-sm md:block">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">#</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">Vade</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">Durum</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">Tutar</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($plan->installments as $inst)
                    <tr>
                        <td class="px-5 py-3">{{ $inst->sequence }}</td>
                        <td class="px-5 py-3">{{ $inst->due_date->format('d.m.Y') }}</td>
                        <td class="px-5 py-3">
                            @include('assessment-plans._installment-status')
                            <p class="mt-2 text-xs text-slate-500">Borç / Daire: {{ $inst->live_due_count }} / {{ $inst->lines->count() }}</p>
                        </td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $inst->lines->sum('amount'), 2, ',', '.') }} ₺</td>
                        <td class="px-5 py-3 text-right">
                            @include('assessment-plans._installment-actions')
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mb-8 space-y-2 md:hidden">
        @foreach ($plan->installments as $inst)
            <article class="min-w-0 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="text-sm font-medium text-slate-900">{{ $inst->sequence }}. Taksit</h3>
                    @include('assessment-plans._installment-status')
                </div>
                <p class="mt-2 text-xs text-slate-500">Son ödeme: {{ $inst->due_date->format('d.m.Y') }}</p>
                @if ($plan->is_active && $plan->createsOnSchedule() && $inst->status === 'pending' && ! in_array($inst->period, $plan->skipped_periods ?? [], true))
                    <p class="mt-2 inline-flex items-center gap-1 text-xs text-slate-500">@include('financial-plans._clock') Planlanan: {{ $inst->due_date->copy()->startOfMonth()->day($plan->generate_day)->format('d.m.Y') }}</p>
                @endif
                <p class="mt-2 text-xs text-slate-500">Borç / Daire: {{ $inst->live_due_count }} / {{ $inst->lines->count() }}</p>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold tabular-nums text-slate-900">{{ number_format($inst->lines->sum('amount'), 2, ',', '.') }} ₺</p>
                    @include('assessment-plans._installment-actions')
                </div>
            </article>
        @endforeach
    </div>

    <h2 class="mb-3 text-base font-semibold text-slate-900">Daire Payları</h2>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">Daire</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">Toplam pay</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($plan->unitShares as $share)
                    <tr>
                        <td class="px-5 py-3">{{ $share->unit?->unit_no }}</td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $share->share_amount, 2, ',', '.') }} ₺</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
