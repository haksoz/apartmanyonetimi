@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Giderler</h1>
            <p class="mt-1 text-sm text-slate-500">Apartman giderleri ve tedarikçi hesap bağlantıları burada yönetilecek.</p>
        </div>
        @if($isOwner)
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('expenses.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Gider Ekle</a>
        </div>
        @endif
    </div>

    {{-- Arama + Filtre --}}
    <div class="mb-5 flex flex-col md:flex-row gap-2">

        {{-- Arama --}}
        <form method="GET" action="{{ route('expenses.index') }}" class="flex gap-2 flex-1">
            @if ($filters['filterPeriod'])
                <input type="hidden" name="period" value="{{ $filters['filterPeriod'] }}">
            @endif
            @if ($filters['filterStatus'])
                <input type="hidden" name="status" value="{{ $filters['filterStatus'] }}">
            @endif
            @if ($filters['filterCategory'])
                <input type="hidden" name="category" value="{{ $filters['filterCategory'] }}">
            @endif
            <input type="text" name="search" value="{{ $filters['filterSearch'] ?? '' }}"
                placeholder="Hesap adı veya tutar..."
                class="flex-1 rounded-xl border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ara</button>
            @if ($filters['filterSearch'] ?? '')
                <a href="{{ route('expenses.index', array_filter(['period' => $filters['filterPeriod'], 'status' => $filters['filterStatus'], 'category' => $filters['filterCategory']])) }}"
                   class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-50">✕</a>
            @endif
        </form>

        {{-- Filtreler --}}
        <form method="GET" action="{{ route('expenses.index') }}" class="flex gap-2 items-center flex-wrap md:flex-nowrap">
            @if ($filters['filterSearch'] ?? '')
                <input type="hidden" name="search" value="{{ $filters['filterSearch'] }}">
            @endif
            <input type="month" name="period" value="{{ $filters['filterPeriod'] }}"
                class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            <select name="status" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                <option value="">— Tüm Durumlar —</option>
                <option value="paid"   @selected($filters['filterStatus'] === 'paid')>Ödendi</option>
                <option value="unpaid" @selected($filters['filterStatus'] === 'unpaid')>Bekliyor</option>
            </select>
            <select name="category" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                <option value="">— Tüm Kategoriler —</option>
                @foreach ($categories as $id => $name)
                    <option value="{{ $id }}" @selected((string)$filters['filterCategory'] === (string)$id)>{{ $name }}</option>
                @endforeach
            </select>
            @if ($hasImported)
                <label class="flex items-center gap-1.5 cursor-pointer text-xs text-slate-500 select-none whitespace-nowrap">
                    <input type="checkbox" name="show_imported" value="1" class="rounded" {{ $showImported ? 'checked' : '' }}>
                    Devir Öncesini Göster
                </label>
            @endif
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrele</button>
            @if ($filters['filterPeriod'] || $filters['filterStatus'] || $filters['filterCategory'] || $showImported)
                <a href="{{ route('expenses.index', array_filter(['search' => $filters['filterSearch']])) }}"
                   class="text-xs text-slate-400 hover:text-slate-600 whitespace-nowrap">Temizle</a>
            @endif
        </form>
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <a href="{{ route('expenses.index', ['sort_by' => 'expense_date', 'sort_direction' => $sortBy === 'expense_date' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 hover:text-slate-700">Tarih @if ($sortBy === 'expense_date')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <a href="{{ route('expenses.index', ['sort_by' => 'period_month', 'sort_direction' => $sortBy === 'period_month' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 hover:text-slate-700">Dönem @if ($sortBy === 'period_month')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <a href="{{ route('expenses.index', ['sort_by' => 'category', 'sort_direction' => $sortBy === 'category' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 hover:text-slate-700">Kategori @if ($sortBy === 'category')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">
                        <a href="{{ route('expenses.index', ['sort_by' => 'amount', 'sort_direction' => $sortBy === 'amount' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center justify-end gap-1 hover:text-slate-700">Tutar @if ($sortBy === 'amount')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">Kalan</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <a href="{{ route('expenses.index', ['sort_by' => 'is_paid', 'sort_direction' => $sortBy === 'is_paid' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 hover:text-slate-700">Durum @if ($sortBy === 'is_paid')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($expenses as $expense)
                    @php
                        $months = ['January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart', 'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',
                                   'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül', 'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'];
                        $periodText = $expense->period_month ? $expense->period_month->format('F Y') : null;
                        if ($periodText) { foreach ($months as $en => $tr) { $periodText = str_replace($en, $tr, $periodText); } }
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors cursor-pointer" onclick="window.location.href='{{ route('expenses.show', $expense) }}'">
                        <td class="px-5 py-4 font-semibold text-slate-900 tabular-nums">{{ $expense->expense_date?->format('d.m.Y') ?? '-' }}</td>
                        <td class="px-5 py-4">
                            <div class="text-slate-900 font-medium">
                                {{ $expense->description ?? '-' }}
                                @if ($expense->is_imported)
                                    <span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>
                                @endif
                            </div>
                            @if ($expense->account)
                                <div class="text-xs text-slate-500 mt-1">{{ $expense->account->name }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-slate-700 tabular-nums">{{ $periodText ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $expense->categoryRelation?->name ?? $expense->category ?? '—' }}</td>
                        <td class="px-5 py-4 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($expense->amount, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4 text-right tabular-nums {{ $expense->is_paid ? 'text-slate-400' : 'text-amber-600 font-semibold' }}">
                            {{ $expense->is_paid ? '—' : number_format($expense->remaining_amount, 2, ',', '.') . ' TL' }}
                        </td>
                        <td class="px-5 py-4">
                            @if ($expense->is_paid)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Ödendi
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Bekliyor
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                            <div class="flex items-center justify-end gap-2">
                                @unless ($expense->is_paid)
                                    <a href="{{ route('expenses.payment.create', $expense) }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition-colors">Öde</a>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-slate-400">Henüz gider kaydı yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Card View --}}
    <div class="md:hidden space-y-3">
        @forelse ($expenses as $expense)
            @php
                $months = ['January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart', 'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',
                           'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül', 'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'];
                $periodText = $expense->period_month ? $expense->period_month->format('F Y') : null;
                if ($periodText) {
                    foreach ($months as $en => $tr) {
                        $periodText = str_replace($en, $tr, $periodText);
                    }
                }
            @endphp
            <a href="{{ route('expenses.show', $expense) }}" class="flex items-start justify-between rounded-xl bg-white p-3 shadow-sm border border-slate-200 hover:bg-slate-50 transition-colors">
                <div class="flex-1">
                    @if ($expense->description)
                        <div class="text-base font-bold text-slate-900">{{ $expense->description }}</div>
                    @endif
                    <div class="text-xs text-slate-600 mt-1">
                        <span>{{ $expense->expense_date?->format('d.m.Y') ?? '-' }}</span>
                        <span class="mx-1 text-slate-400">•</span>
                        <span>{{ $periodText ?? '-' }}</span>
                        <span class="mx-1 text-slate-400">•</span>
                        <span>{{ $expense->categoryRelation?->name ?? $expense->category ?? '—' }}</span>
                        @if ($expense->account)
                            <span class="mx-1 text-slate-400">•</span>
                            <span>{{ $expense->account->name }}</span>
                        @endif
                    </div>
                </div>
                <div class="ml-3 text-right">
                    <div class="font-bold text-slate-900">{{ number_format($expense->amount, 2, ',', '.') }} TL</div>
                    @if (!$expense->is_paid)
                        <div class="text-xs text-amber-600 font-semibold mt-0.5">Kalan: {{ number_format($expense->remaining_amount, 2, ',', '.') }} TL</div>
                    @endif
                    @if ($expense->is_imported)
                        <span class="inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700 mt-1">Devir Öncesi</span>
                    @endif
                    @if ($expense->is_paid)
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 mt-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                            Ödendi
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 mt-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                            Bekliyor
                        </span>
                    @endif
                </div>
            </a>
        @empty
            <div class="rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm">
                Henüz gider kaydı yok.
            </div>
        @endforelse
    </div>

    {{-- Sayfalama --}}
    @if ($expenses->hasPages())
        <div class="mt-6">
            {{ $expenses->links() }}
        </div>
    @endif
@endsection
