@extends('layouts.app')

@section('content')
    @php
        $availableTypes = [];
        if (($canAidat || auth()->user()->isAdmin()) && ! $hasCurrentYearAidatPlan) $availableTypes[] = 'aidat';
        if ($canExpense || auth()->user()->isAdmin()) $availableTypes[] = 'expense';
        if ($canAssessment || auth()->user()->isAdmin()) $availableTypes[] = 'assessment';
        $selectedType = in_array(old('type'), $availableTypes, true) ? old('type') : ($availableTypes[0] ?? null);
    @endphp
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Yeni Plan Oluştur</h1>
        <p class="mt-1 text-sm text-slate-500">Önce plan türünü seçin.</p>
    </div>

    <form method="POST" action="{{ route('financial-plans.select-type') }}" class="max-w-xl space-y-4 rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        @if ($canAidat || auth()->user()->isAdmin())
            <label @class([
                'flex items-start gap-3 rounded-xl border border-slate-200 p-4',
                'cursor-not-allowed bg-slate-50 opacity-50' => $hasCurrentYearAidatPlan,
                'cursor-pointer hover:bg-slate-50 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50' => ! $hasCurrentYearAidatPlan,
            ])>
                <input type="radio" name="type" value="aidat" class="mt-1" required @disabled($hasCurrentYearAidatPlan) @checked($selectedType === 'aidat')>
                <span>
                    <span class="block font-semibold text-slate-900">Aidat Planı</span>
                    <span class="text-sm text-slate-500">{{ $hasCurrentYearAidatPlan ? now()->year.' yılı için aktif aidat planı var.' : 'Yıllık aidat planı ve aylık borç üretimi' }}</span>
                </span>
            </label>
        @endif
        @if ($canExpense || auth()->user()->isAdmin())
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 hover:bg-slate-50 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                <input type="radio" name="type" value="expense" class="mt-1" required @checked($selectedType === 'expense')>
                <span>
                    <span class="block font-semibold text-slate-900">Gider Planı</span>
                    <span class="text-sm text-slate-500">Sözleşmeli tekrarlayan giderler</span>
                </span>
            </label>
        @endif
        @if ($canAssessment || auth()->user()->isAdmin())
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 hover:bg-slate-50 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                <input type="radio" name="type" value="assessment" class="mt-1" required @checked($selectedType === 'assessment')>
                <span>
                    <span class="block font-semibold text-slate-900">Ek Borçlandırma Planı</span>
                    <span class="text-sm text-slate-500">Tek seferlik maliyetin taksitli dağıtımı</span>
                </span>
            </label>
        @endif

        @error('type')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('financial-plans.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">İptal</a>
            <button type="submit" @disabled(empty($availableTypes)) class="rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Devam</button>
        </div>
    </form>
@endsection
