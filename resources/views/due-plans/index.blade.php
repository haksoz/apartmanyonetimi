@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Aidat Planlama</h1>
            <p class="mt-1 text-sm text-slate-500">Yıllık aidat planlarını tanımlayın ve aylık aidatları otomatik oluşturun.</p>
        </div>
        <a href="{{ route('due-plans.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">+ Yeni Plan</a>
    </div>

    @if ($plans->isEmpty())
        <div class="rounded-2xl bg-white p-10 shadow-sm text-center text-slate-500 text-sm">Henüz aidat planı tanımlanmamış.</div>
    @else
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($plans as $plan)
                <div class="rounded-2xl bg-white p-6 shadow-sm flex flex-col gap-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold text-slate-950 text-base">{{ $plan->name }}</div>
                            <div class="mt-1 text-sm text-slate-500">{{ $plan->year }} · {{ $plan->distribution_label }}</div>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $plan->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ $plan->is_active ? 'Aktif' : 'Pasif' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <div class="text-xs text-slate-500">Aylık Tutar</div>
                            <div class="mt-1 font-bold text-slate-900 tabular-nums">{{ number_format($plan->monthly_amount_resolved, 2, ',', '.') }} TL</div>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <div class="text-xs text-slate-500">Vade Günü</div>
                            <div class="mt-1 font-bold text-slate-900">Her ayın {{ $plan->due_day }}. günü</div>
                        </div>
                    </div>

                    @if ($plan->category)
                        <div class="text-xs text-slate-500">Kategori: <span class="font-medium text-slate-700">{{ $plan->category->name }}</span></div>
                    @endif

                    <div class="flex gap-2 pt-1">
                        <a href="{{ route('due-plans.edit', $plan) }}" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 text-center hover:bg-slate-50">Düzenle</a>
                        <form method="POST" action="{{ route('due-plans.destroy', $plan) }}" onsubmit="return confirm('Plan silinsin mi?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-xl border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Sil</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Aidat Planlaması ile Oluşturulan Aylar --}}
    @php
        $allBatches = $plans->flatMap(fn ($p) => $p->batches
                               ->filter(fn ($b) => $b->dues_count > 0)
                               ->map(fn ($b) => ['plan' => $p, 'batch' => $b]))
                           ->sortBy(fn ($row) => $row['batch']->period);
    @endphp
    @if ($allBatches->isNotEmpty())
        <div class="mt-8">
            <h2 class="text-base font-semibold text-slate-950 mb-3">Aidat Planlaması ile Oluşturulan Aylar</h2>
            <div class="rounded-2xl bg-white shadow-sm overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Dönem</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Plan</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Dağıtım</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">Toplam Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($allBatches as $row)
                            @php
                                $batchPeriod = \Carbon\Carbon::parse($row['batch']->period . '-01')->locale('tr')->isoFormat('MMMM YYYY');
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $batchPeriod }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $row['plan']->name }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ $row['plan']->distribution_label }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($row['batch']->source_amount, 2, ',', '.') }} TL</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
