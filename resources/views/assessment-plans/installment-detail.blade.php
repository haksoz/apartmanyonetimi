@extends('layouts.app')
@section('content')
<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <div><h1 class="text-2xl font-bold text-slate-950">{{ $inst->sequence }}. Taksit · {{ $plan->name }}</h1><p class="mt-1 text-sm text-slate-500">Vade: {{ $inst->due_date->format('d.m.Y') }} · {{ \App\Models\AssessmentPlan::debtTypeLabel($plan->due_type) }} · {{ $plan->category?->name ?? '—' }}</p></div>
    <a href="{{ route('assessment-plans.show', $plan) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Taksitli Plana Dön</a>
</div>
<div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <p class="rounded-xl bg-white p-4 text-sm">Beklenen / Mevcut: <strong>{{ $inst->lines->count() }} / {{ $inst->live_due_count }}</strong></p>
    <p class="rounded-xl bg-white p-4 text-sm">Eksik Borç: <strong>{{ $inst->status === 'generated' ? $inst->lines->count() - $inst->live_due_count : '—' }}</strong></p>
    <p class="rounded-xl bg-white p-4 text-sm">Planlanan Toplam: <strong>{{ number_format($inst->lines->sum('amount'), 2, ',', '.') }} TL</strong></p>
    <p class="rounded-xl bg-white p-4 text-sm">Mevcut Borçların Toplamı: <strong>{{ number_format($inst->lines->sum(fn ($line) => (float) ($line->due?->amount ?? 0)), 2, ',', '.') }} TL</strong></p>
</div>
<div class="mb-4 text-sm">@include('assessment-plans._installment-status')</div>
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50"><tr>@foreach(['Daire', 'Hesap', 'Planlanan Tutar', 'Borç Tutarı'] as $label)<th class="whitespace-nowrap p-3">{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse($inst->lines as $line)
            <tr class="border-t border-slate-100">
                <td class="whitespace-nowrap p-3">{{ $line->unit?->unit_no ?? '—' }}</td>
                <td class="p-3">{{ $line->due?->account?->name ?? '—' }}</td>
                <td class="whitespace-nowrap p-3">{{ number_format($line->amount, 2, ',', '.') }} TL</td>
                <td class="whitespace-nowrap p-3">
                    @if($line->due)<a href="{{ route('dues.show', $line->due) }}" class="font-semibold text-emerald-700 hover:underline">{{ number_format($line->due->amount, 2, ',', '.') }} TL</a>
                    @elseif($inst->isCancelled())<span class="text-slate-500">İptal edildi</span>
                    @elseif($inst->status === 'generated')<span class="font-semibold text-amber-700">Eksik</span>
                    @else<span class="text-slate-500">Henüz oluşturulmadı</span>@endif
                </td>
            </tr>
        @empty<tr><td colspan="4" class="p-6 text-center text-slate-500">Daire bulunamadı.</td></tr>@endforelse
        </tbody>
    </table>
</div>
@if($inst->status === 'generated' && $inst->has_missing_dues && $plan->is_active && ! in_array($inst->period, $plan->skipped_periods ?? [], true))
<form method="POST" action="{{ route('assessment-plans.regenerate-installment', [$plan, $inst]) }}" class="mt-5">@csrf<button class="rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white">Eksik Borçları Oluştur</button><p class="mt-2 text-xs text-slate-500">Mevcut borçlar korunur.</p></form>
@endif
@endsection
