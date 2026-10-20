@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Finansal Planlama</h1>
            <p class="mt-1 text-sm text-slate-500">Aidat, gider ve ek borçlandırma planlarını buradan yönetin.</p>
        </div>
        <a href="{{ route('financial-plans.create') }}"
           class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
            Yeni Plan Oluştur
        </a>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 mb-6">{{ session('status') }}</div>
    @endif

    <div class="rounded-2xl bg-white shadow-sm overflow-hidden">
        @if ($plans->isEmpty())
            <div class="p-8 text-center text-sm text-slate-500">Henüz plan yok. Yeni plan oluşturarak başlayın.</div>
        @else
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Tür</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Ad</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Dönem</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</th>
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
