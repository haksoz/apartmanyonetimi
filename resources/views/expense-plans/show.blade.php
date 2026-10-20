@extends('layouts.app')

@section('content')
    @php $editing = request()->boolean('edit') || $errors->any(); @endphp
    <div class="mb-6 flex flex-row items-center justify-between gap-3 min-w-0">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold text-slate-950">
                <a href="{{ route('financial-plans.index') }}" class="hover:text-slate-600">Finansal Planlama</a>
                <span class="font-normal text-slate-400"> / </span>
                <span class="text-lg font-semibold text-slate-800">Gider Planı · {{ $plan->name }}</span>
            </h1>
            <p class="mt-1 text-sm text-slate-500">Aylık gider tutarı, oluşturma yöntemi ve vade bilgileri.</p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
            @unless ($editing)
                <a href="{{ route('expense-plans.show', ['expensePlan' => $plan, 'edit' => 1]) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
            @endunless
            <a href="{{ route('financial-plans.index') }}" class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-xl border border-slate-300 px-3 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:px-4 sm:text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h14" />
                </svg>
                Geri
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

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
    <div class="rounded-2xl bg-white shadow-sm overflow-x-auto">
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
                            @if ($p['status'] === 'complete')
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Oluşturuldu</span>
                            @elseif ($p['status'] === 'stopped')
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">Durduruldu</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Oluşturulmadı</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if ($p['status'] === 'complete' && $p['expense_id'])
                                <a href="{{ route('expenses.show', $p['expense_id']) }}" class="text-xs underline">Görüntüle</a>
                            @elseif ($plan->is_active && $p['status'] === 'not_generated')
                                <form method="POST" action="{{ route('expense-plans.generate-month', $plan) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="period" value="{{ $p['period'] }}">
                                    <button class="text-xs underline">Oluştur</button>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
