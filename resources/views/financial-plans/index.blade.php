@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Finansal Planlama</h1>
            <p class="mt-1 text-sm text-slate-500">Aidat, gider ve taksitli borç planlarını buradan yönetin.</p>
        </div>
        <a href="{{ route('financial-plans.create') }}"
           class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
            Yeni Plan Oluştur
        </a>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 mb-6">{{ session('status') }}</div>
    @endif

    <div class="md:rounded-2xl md:bg-white md:shadow-sm md:overflow-x-auto">
        @if ($plans->isEmpty())
            <div class="p-8 text-center text-sm text-slate-500">Henüz plan yok. Yeni plan oluşturarak başlayın.</div>
        @else
            <div class="space-y-4 md:hidden">
                @foreach ($plans as $row)
                    <article class="min-w-0 rounded-2xl bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-slate-500">{{ $row['type_label'] }}</p>
                                <h2 class="mt-1 break-words text-base font-semibold text-slate-900">
                                    <a href="{{ $row['url'] }}" class="hover:underline">{{ $row['name'] }}</a>
                                </h2>
                            </div>
                            <div class="shrink-0">
                                @if ($row['is_expired'] ?? false)
                                    <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">Süresi Doldu</span>
                                @elseif ($row['is_active'])
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Aktif</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Pasif</span>
                                @endif
                            </div>
                        </div>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <dt class="text-slate-500">Dönem</dt>
                                <dd class="break-words text-slate-700">{{ $row['period_label'] }}</dd>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <dt class="text-slate-500">Oluşturma Yöntemi</dt>
                                <dd class="inline-flex items-center gap-2 text-slate-700">
                                    @if ($row['generation_mode'] === 'on_schedule')
                                        <svg class="h-5 w-5 shrink-0 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2" /></svg>
                                        Zamanı geldikçe
                                    @else
                                        <svg class="h-5 w-5 shrink-0 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 3 8l9 5 9-5-9-5ZM3 12l9 5 9-5M3 16l9 5 9-5" /></svg>
                                        Tümünü şimdi oluştur
                                    @endif
                                </dd>
                            </div>
                            <div class="flex flex-wrap items-baseline justify-between gap-2 border-t border-slate-100 pt-3">
                                <dt class="text-slate-500">Tutar</dt>
                                <dd class="break-all font-semibold tabular-nums text-slate-900">{{ number_format((float) $row['amount'], 2, ',', '.') }} ₺</dd>
                            </div>
                        </dl>
                        <a href="{{ $row['url'] }}" aria-label="{{ $row['name'] }} planını görüntüle" class="mt-4 flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Planı Görüntüle</a>
                    </article>
                @endforeach
            </div>
            <table class="hidden min-w-full text-sm md:table">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Tür</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Ad</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Dönem</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Oluşturma Yöntemi</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">Tutar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($plans as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3 text-slate-600">{{ $row['type_label'] }}</td>
                            <td class="px-5 py-3 font-medium text-slate-900">
                                <a href="{{ $row['url'] }}" class="underline hover:text-slate-700">{{ $row['name'] }}</a>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $row['period_label'] }}</td>
                            <td class="px-5 py-3">
                                @if ($row['is_expired'] ?? false)
                                    <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">Süresi Doldu</span>
                                @elseif ($row['is_active'])
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Aktif</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Pasif</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if ($row['generation_mode'] === 'on_schedule')
                                    <span class="inline-flex items-center gap-2 whitespace-nowrap text-sm text-slate-600" title="Aktif planlarda kayıtlar zamanı geldiğinde otomatik oluşturulur.">
                                        <svg class="h-5 w-5 shrink-0 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <circle cx="12" cy="12" r="9" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2" />
                                        </svg>
                                        Zamanı geldikçe
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-2 whitespace-nowrap text-sm text-slate-600" title="Kayıtlar toplu oluşturma yöntemiyle hazırlanır; tamamlanma durumunu plan detayından inceleyebilirsiniz.">
                                        <svg class="h-5 w-5 shrink-0 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 3 8l9 5 9-5-9-5ZM3 12l9 5 9-5M3 16l9 5 9-5" />
                                        </svg>
                                        Tümünü şimdi oluştur
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-slate-900">
                                {{ number_format((float) $row['amount'], 2, ',', '.') }} ₺
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
