@extends('layouts.app')

@section('content')
    @php $editing = request()->boolean('edit') || $errors->any(); @endphp
    <div class="mb-6 flex min-w-0 flex-row items-start justify-between gap-3 sm:items-center">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold text-slate-950">
                <span class="inline-block"><a href="{{ route('financial-plans.index') }}" class="hover:text-slate-600">Finansal Planlama</a><span class="font-normal text-slate-400"> / </span></span>
                <span class="block break-words text-lg font-semibold text-slate-800 sm:inline">Gider Planı · {{ $plan->name }}</span>
            </h1>
            <p class="mt-1 text-sm text-slate-500">Aylık gider tutarı, oluşturma yöntemi ve vade bilgileri.</p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
            @unless ($editing)
                <a href="{{ route('expense-plans.show', ['expensePlan' => $plan, 'edit' => 1]) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
            @endunless
            <a href="{{ route('financial-plans.index') }}" aria-label="Finansal planlamaya geri dön" class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-xl border border-slate-300 px-3 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:px-4 sm:text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h14" />
                </svg>
                <span class="hidden sm:inline">Geri</span>
            </a>
        </div>
    </div>

    @if ($editing)
    <form id="expense-plan-form" method="POST" action="{{ route('expense-plans.update', $plan) }}" class="mb-8 space-y-4">
        @csrf
        @method('PUT')
        <fieldset id="expense-plan-fields" class="min-w-0 space-y-4">
        @include('expense-plans._form', [
            'accounts' => collect([$plan->account])->filter(),
            'categories' => collect([$plan->category])->filter(),
        ])
        </fieldset>
    </form>
    @else
        @include('expense-plans._details')
    @endif

    <h2 class="mb-3 text-base font-semibold text-slate-900">Plan Dönemleri</h2>
    <div class="hidden overflow-x-auto rounded-2xl bg-white shadow-sm md:block">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">Dönem</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">Durum</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($periods as $p)
                    <tr>
                        <td class="px-5 py-3 font-medium">{{ $p['label'] }}</td>
                        <td class="px-5 py-3">
                            @include('expense-plans._period-status')
                        </td>
                        <td class="px-5 py-3 text-right">
                            @include('expense-plans._period-actions')
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="space-y-2 md:hidden">
        @foreach ($periods as $p)
            <article class="min-w-0 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="text-sm font-medium text-slate-900">{{ $p['label'] }}</h3>
                    <div class="shrink-0">@include('expense-plans._period-status')</div>
                </div>
                <p class="mt-2 flex flex-wrap items-center gap-1 text-xs text-slate-500">
                    @if ($p['status'] === 'not_generated' && $plan->generation_mode === 'on_schedule')
                        @include('financial-plans._clock')
                    @endif
                    {{ $p['expense_id'] ? 'Oluşturma' : 'Planlanan' }}: {{ $p['expense_date'] }} · Vade: {{ $p['due_date'] ?? '—' }}
                </p>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold tabular-nums text-slate-900">{{ number_format($p['amount'], 2, ',', '.') }} ₺</p>
                    @include('expense-plans._period-actions')
                </div>
            </article>
        @endforeach
    </div>
@endsection
