@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Aidat Planı</h1>
            <p class="mt-1 text-sm text-slate-500">Yıllık aidat planı tanımlayın; sistem seçilen yönteme göre aidatları oluşturur.</p>
        </div>
        @if (($plans ?? collect())->isNotEmpty())
            <form method="GET" action="{{ route('due-plans.index') }}" class="flex items-center gap-2">
                <label for="year" class="text-sm text-slate-600">Plan yılı</label>
                <select id="year" name="year" onchange="this.form.submit()"
                        class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    @foreach ($plans as $p)
                        <option value="{{ $p->plan_year }}" @selected((int) $selectedYear === (int) $p->plan_year)>{{ $p->plan_year }}</option>
                    @endforeach
                    @if (! $plans->contains(fn ($p) => (int) $p->plan_year === (int) $selectedYear))
                        <option value="{{ $selectedYear }}" selected>{{ $selectedYear }} (yeni)</option>
                    @endif
                </select>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 mb-6">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 mb-6">
            {{ session('error') }}
        </div>
    @endif

    @if (session('error_html'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 mb-6">
            {!! session('error_html') !!}
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-sm max-w-4xl">
        <form method="POST" action="{{ route('due-plans.store') }}" class="space-y-6">
            @csrf
            @include('due-plans._form', ['plan' => $plan, 'selectedYear' => $selectedYear ?? now()->year])

            <div class="flex items-center justify-between gap-3 pt-2 border-t border-slate-100">
                <div class="flex gap-2">
                    @if ($plan->exists && $plan->is_active)
                        <button type="submit" form="deactivate-form" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                            Planı Durdur
                        </button>
                    @elseif ($plan->exists && ! $plan->is_active)
                        <button type="submit" form="activate-form" class="rounded-xl border border-emerald-300 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 transition-colors">
                            Yeniden Aktifleştir
                        </button>
                    @endif
                </div>
                <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 transition-colors">
                    Kaydet
                </button>
            </div>
        </form>
        @if ($plan->exists && $plan->is_active)
            <form id="deactivate-form" method="POST" action="{{ route('due-plans.deactivate', $plan) }}" class="hidden">
                @csrf
                @method('PATCH')
            </form>
        @elseif ($plan->exists && ! $plan->is_active)
            <form id="activate-form" method="POST" action="{{ route('due-plans.activate', $plan) }}" class="hidden">
                @csrf
                @method('PATCH')
            </form>
        @endif
    </div>

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
                        <a href="{{ route('due-plans.index', ['year' => $plan->plan_year]) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
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

    @if ($shouldPromptPastPeriods ?? false)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-bold text-slate-900 mb-2">Geçmiş Dönemler</h2>
                <p class="text-sm text-slate-600 mb-3">
                    Aşağıdaki geçmiş dönemlerde henüz aidat yok. Bunları da şimdi oluşturmak ister misiniz?
                </p>
                <ul class="mb-5 max-h-40 overflow-y-auto text-sm text-slate-700 list-disc pl-5 space-y-1">
                    @foreach ($pastPeriodsToConfirm as $pp)
                        <li>{{ $pp['label'] ?? $pp['period'] }}</li>
                    @endforeach
                </ul>
                <div class="flex flex-col sm:flex-row gap-2 justify-end">
                    <form method="POST" action="{{ route('due-plans.confirm-past-periods', $plan) }}">
                        @csrf
                        <input type="hidden" name="include_past_periods" value="0">
                        <button type="submit" class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                            Geçmişi atla
                        </button>
                    </form>
                    <form method="POST" action="{{ route('due-plans.confirm-past-periods', $plan) }}">
                        @csrf
                        <input type="hidden" name="include_past_periods" value="1">
                        <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 transition-colors">
                            Geçmişi de oluştur
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($plan->exists && ! empty($periods))
        <div class="mt-8 max-w-4xl">
            <h2 class="text-base font-semibold text-slate-900 mb-3">Plan Ayları — {{ $plan->plan_year }}</h2>

            <div class="hidden md:block rounded-2xl bg-white shadow-sm overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Dönem</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</th>
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
                                    @elseif ($p['status'] === 'stopped')
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">Durduruldu</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Oluşturulmadı</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right text-slate-500 tabular-nums">
                                    @if ($p['status'] !== 'not_generated' && $p['status'] !== 'stopped')
                                        {{ $p['active_count'] }} / {{ $p['expected_count'] }}
                                    @else
                                        –
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if ($p['status'] === 'complete')
                                        <a href="{{ route('dues.index', ['batch_id' => $p['batch_id']]) }}" class="text-xs font-medium text-slate-700 hover:text-slate-900 underline">Görüntüle</a>
                                    @elseif ($plan->is_active && $p['status'] === 'incomplete')
                                        <form method="POST" action="{{ route('due-plans.regenerate-period', $plan) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="period" value="{{ $p['period'] }}">
                                            <button type="submit" class="text-xs font-medium text-emerald-700 hover:text-emerald-800 underline">Eksikleri Tamamla</button>
                                        </form>
                                    @elseif ($plan->is_active && $p['status'] === 'not_generated')
                                        <form method="POST" action="{{ route('due-plans.generate-month', $plan) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="period" value="{{ $p['period'] }}">
                                            <button type="submit" class="text-xs font-medium text-slate-700 hover:text-slate-900 underline">Oluştur</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
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
                                @elseif ($p['status'] === 'stopped')
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Durduruldu</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Oluşturulmadı</span>
                                @endif
                            </div>
                        </div>
                        <div class="mt-2 flex justify-end">
                            @if ($p['status'] === 'complete')
                                <a href="{{ route('dues.index', ['batch_id' => $p['batch_id']]) }}" class="text-xs font-medium text-slate-700 hover:text-slate-900 underline">Görüntüle</a>
                            @elseif ($plan->is_active && $p['status'] === 'incomplete')
                                <form method="POST" action="{{ route('due-plans.regenerate-period', $plan) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="period" value="{{ $p['period'] }}">
                                    <button type="submit" class="text-xs font-medium text-emerald-700 hover:text-emerald-800 underline">Eksikleri Tamamla</button>
                                </form>
                            @elseif ($plan->is_active && $p['status'] === 'not_generated')
                                <form method="POST" action="{{ route('due-plans.generate-month', $plan) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="period" value="{{ $p['period'] }}">
                                    <button type="submit" class="text-xs font-medium text-slate-700 hover:text-slate-900 underline">Oluştur</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
