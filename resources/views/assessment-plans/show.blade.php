@extends('layouts.app')

@section('content')
    @php $editing = request()->boolean('edit') || $errors->any(); @endphp
    <div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <a href="{{ route('financial-plans.index') }}" class="text-sm text-slate-500 hover:underline">← Finansal Planlama</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $plan->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ number_format((float) $plan->total_amount, 2, ',', '.') }} ₺ · {{ $plan->installment_count }} taksit
                · {{ $plan->is_active ? 'Aktif' : 'Pasif' }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @unless ($editing)
                <a href="{{ route('assessment-plans.show', ['assessmentPlan' => $plan, 'edit' => 1]) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
            @endunless
            <a href="{{ route('financial-plans.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</a>
            @if ($editing && $plan->is_active)
                <form method="POST" action="{{ route('assessment-plans.deactivate', $plan) }}">
                    @csrf @method('PATCH')
                    <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Planı Durdur</button>
                </form>
            @endif
            @if ($editing)
                <a href="{{ route('assessment-plans.cancel-preview', $plan) }}" class="rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Gelecek Taksitleri İptal Et</a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    @if ($promptPast && $pastInstallments->isNotEmpty())
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-bold text-slate-900 mb-2">Geçmiş Vadeler</h2>
                <p class="text-sm text-slate-600 mb-3">Aşağıdaki geçmiş vadeli taksitler henüz oluşturulmadı. Bunları da oluşturmak ister misiniz?</p>
                <ul class="mb-5 list-disc pl-5 text-sm text-slate-700 space-y-1">
                    @foreach ($pastInstallments as $inst)
                        <li>Taksit {{ $inst->sequence }} — {{ $inst->due_date->format('d.m.Y') }}</li>
                    @endforeach
                </ul>
                <div class="flex gap-2 justify-end">
                    <form method="POST" action="{{ route('assessment-plans.confirm-past', $plan) }}">
                        @csrf
                        <input type="hidden" name="include_past_installments" value="0">
                        <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Geçmişi atla</button>
                    </form>
                    <form method="POST" action="{{ route('assessment-plans.confirm-past', $plan) }}">
                        @csrf
                        <input type="hidden" name="include_past_installments" value="1">
                        <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Geçmişi de oluştur</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($editing)
        @include('assessment-plans._edit')
    @else
        @include('assessment-plans._details')
    @endif

    <h2 class="mb-3 text-base font-semibold text-slate-900">Taksitler</h2>
    <div class="mb-8 rounded-2xl bg-white shadow-sm overflow-hidden max-w-4xl">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">#</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">Vade</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">Durum</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">Tutar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($plan->installments as $inst)
                    <tr>
                        <td class="px-5 py-3">{{ $inst->sequence }}</td>
                        <td class="px-5 py-3">{{ $inst->due_date->format('d.m.Y') }}</td>
                        <td class="px-5 py-3">
                            @if ($inst->status === 'generated')
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Oluşturuldu</span>
                            @elseif ($inst->status === 'cancelled')
                                <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700">İptal</span>
                            @elseif (! $plan->is_active)
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">Durduruldu</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Bekliyor</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $inst->lines->sum('amount'), 2, ',', '.') }} ₺</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="mb-3 text-base font-semibold text-slate-900">Daire Payları</h2>
    <div class="rounded-2xl bg-white shadow-sm overflow-hidden max-w-2xl">
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
