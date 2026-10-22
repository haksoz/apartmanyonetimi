@extends('layouts.app')

@section('content')
    @php $editing = ! $plan->exists || request()->boolean('edit') || $errors->any(); @endphp
    <div class="mb-6 flex min-w-0 flex-row items-start justify-between gap-3 sm:items-center">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold text-slate-950">
                <span class="inline-block"><a href="{{ route('financial-plans.index') }}" class="hover:text-slate-600">Finansal Planlama</a><span class="font-normal text-slate-400"> / </span></span>
                <span class="block text-lg font-semibold text-slate-800 sm:inline">{{ $plan->exists ? 'Aidat Planı · '.$selectedYear : 'Aidat Planı Oluştur' }}</span>
            </h1>
            <p class="mt-1 text-sm text-slate-500">Aylık aidat tutarı, dağıtım ve vade bilgileri.</p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
            @unless ($editing)
                <a href="{{ route('due-plans.index', [...$plan->pageParameters(), 'edit' => 1]) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
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
        <form method="POST" action="{{ route('due-plans.store') }}" class="space-y-4">
            @csrf
            @include('due-plans._form', ['plan' => $plan, 'selectedYear' => $selectedYear ?? now()->year])

            <div class="flex flex-wrap items-center justify-end gap-3">
                <p class="mr-auto text-xs text-slate-500">Son onayınıza kadar kayıt yapılmaz.</p>
                @if ($plan->exists)
                    <a href="{{ route('due-plans.index', $plan->pageParameters()) }}" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">İptal</a>
                @else
                    <a href="{{ route('financial-plans.index') }}" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">İptal</a>
                @endif
                <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 transition-colors">
                    Kaydet
                </button>
            </div>
        </form>

    @else
        @include('due-plans._details')
    @endif

    @if ($shouldPromptGenerate ?? false)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-bold text-slate-900 mb-2">Aidat Oluştur</h2>
                <p class="text-sm text-slate-600 mb-5">
                    {{ \Carbon\Carbon::parse($currentPeriod . '-01')->locale('tr')->isoFormat('MMMM YYYY') }} dönemi için henüz aidat oluşturulmamış. Şimdi oluşturmak istiyor musunuz?
                </p>
                <form method="POST" action="{{ route('due-plans.generate-month', $plan) }}">
                    @csrf
                    <input type="hidden" name="period" value="{{ $currentPeriod }}">
                    <div class="flex gap-3 justify-end">
                        <a href="{{ route('due-plans.index', $plan->pageParameters()) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                            Hayır
                        </a>
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 transition-colors">
                            Evet, Oluştur
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($plan->exists && ! empty($periods))
        <div class="mt-8">
            <h2 class="text-base font-semibold text-slate-900 mb-3">Plan Ayları — {{ $plan->plan_year }}</h2>

            <div class="hidden md:block rounded-2xl bg-white shadow-sm overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Dönem</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Planlanan Oluşturma</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">Aidat / Daire</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($periods as $p)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $p['label'] }}</td>
                                <td class="px-5 py-3">
                                    @if ($p['status'] === 'complete')
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Tamamlandı</span>
                                    @elseif ($p['status'] === 'incomplete')
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">Eksik</span>
                                        <span class="ml-1 text-xs text-slate-500">{{ $p['active_count'] }} / {{ $p['expected_count'] }}</span>
                                    @elseif (in_array($p['status'], ['stopped', 'skipped'], true))
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">{{ $p['status'] === 'skipped' ? 'Atlandı' : 'Durduruldu' }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Oluşturulmadı</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">@include('due-plans._schedule')</td>
                                <td class="px-5 py-3 text-right text-slate-500 tabular-nums">
                                    @if (! in_array($p['status'], ['not_generated', 'stopped', 'skipped'], true))
                                        {{ $p['active_count'] }} / {{ $p['expected_count'] }}
                                    @else
                                        –
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @include('due-plans._period-actions')
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="md:hidden space-y-2">
                @foreach ($periods as $p)
                    <div class="rounded-xl bg-white p-3 shadow-sm border border-slate-200">
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-medium text-slate-900 text-sm">{{ $p['label'] }}</div>
                            <div class="shrink-0 text-right">
                                @if ($p['status'] === 'complete')
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Tamamlandı</span>
                                @elseif ($p['status'] === 'incomplete')
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Eksik</span>
                                @elseif (in_array($p['status'], ['stopped', 'skipped'], true))
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">{{ $p['status'] === 'skipped' ? 'Atlandı' : 'Durduruldu' }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Oluşturulmadı</span>
                                @endif
                            </div>
                        </div>
                        @if ($p['scheduled_for'])
                            <div class="mt-2">@include('due-plans._schedule')</div>
                        @endif
                        <div class="mt-2 flex items-center justify-between gap-2 text-xs text-slate-500">
                            <span>Aidat / Daire</span>
                            <span class="tabular-nums">
                                @if (! in_array($p['status'], ['not_generated', 'stopped', 'skipped'], true))
                                    {{ $p['active_count'] }} / {{ $p['expected_count'] }}
                                @else
                                    –
                                @endif
                            </span>
                        </div>
                        <div class="mt-2 flex justify-end">
                            @include('due-plans._period-actions')
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
