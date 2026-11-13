@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Giderler</h1>
            <p class="mt-1 text-sm text-slate-500">Apartman gider yönetimi</p>
        </div>
        @if($isOwner)
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('expenses.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Gider Ekle</a>
        </div>
        @endif
    </div>

    {{-- Arama + Filtre --}}
    <div class="mb-4 flex flex-row items-center justify-between gap-3">
        {{-- Arama --}}
        <form method="GET" action="{{ route('expenses.index') }}" class="flex gap-2 flex-1 min-w-0">
            @if ($filters['filterPeriod'])
                <input type="hidden" name="period" value="{{ $filters['filterPeriod'] }}">
            @endif
            @foreach ($filters['filterStatus'] as $status)
                <input type="hidden" name="status[]" value="{{ $status }}">
            @endforeach
            @if ($filters['filterCategory'])
                <input type="hidden" name="category" value="{{ $filters['filterCategory'] }}">
            @endif
            @if ($showImported)
                <input type="hidden" name="show_imported" value="1">
            @endif
            @if ($sortBy !== 'expense_date')
                <input type="hidden" name="sort_by" value="{{ $sortBy }}">
            @endif
            @if ($sortDirection !== 'desc')
                <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">
            @endif
            <input type="text" name="search" value="{{ $filters['filterSearch'] ?? '' }}"
                placeholder="Hesap adı, tutar veya açıklama..."
                class="flex-1 rounded-xl border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            <button type="submit" class="h-9 rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ara</button>
            @if ($filters['filterSearch'] ?? '')
                <a href="{{ route('expenses.index', request()->except('search')) }}"
                   class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-50">✕</a>
            @endif
        </form>

        @php
            $activeFilterCount = 0;
            if ($filters['filterPeriod']) $activeFilterCount++;
            if ($filters['filterStatus']) $activeFilterCount++;
            if ($filters['filterCategory']) $activeFilterCount++;
            if ($showImported) $activeFilterCount++;
        @endphp

        <button type="button" onclick="openFilterModal()"
            class="h-9 ml-auto flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.238 2.022l-3.158 1.579A2.25 2.25 0 018.25 20.05v-5.83a2.25 2.25 0 00-.659-1.591L2.659 7.197A2.25 2.25 0 012 5.606V4.562c0-.54.384-1.006.917-1.096A49.32 49.32 0 0112 3z"/>
            </svg>
            <span class="hidden md:inline">Filitrele</span>
            @if ($activeFilterCount > 0)
                <span class="ml-1 flex h-5 w-5 items-center justify-center rounded-full bg-white text-xs font-bold text-slate-950">{{ $activeFilterCount }}</span>
            @endif
        </button>
    </div>

    @php
        $statusLabels = ['paid' => 'Ödendi', 'overdue' => 'Ödenmedi', 'pending' => 'Bekliyor'];
        $activeCategory = $filters['filterCategory'] ? $categories->get($filters['filterCategory']) : null;
    @endphp

    @if ($activeFilterCount > 0)
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-slate-500">Aktif filtreler:</span>
            @if ($filters['filterPeriod'])
                <a href="{{ route('expenses.index', request()->except('period')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    {{ \Carbon\Carbon::parse($filters['filterPeriod'] . '-01')->format('m/Y') }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            @if ($filters['filterStatus'])
                <a href="{{ route('expenses.index', request()->except('status')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    {{ implode(', ', array_map(fn ($status) => $statusLabels[$status], $filters['filterStatus'])) }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            @if ($filters['filterCategory'] && $activeCategory)
                <a href="{{ route('expenses.index', request()->except('category')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    {{ $activeCategory }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            @if ($showImported)
                <a href="{{ route('expenses.index', request()->except('show_imported')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">
                    Devir Öncesi
                    <span class="text-blue-500">&times;</span>
                </a>
            @endif
            <a href="{{ route('expenses.index', request()->only(['search', 'sort_by', 'sort_direction'])) }}"
               class="ml-1 text-xs font-medium text-slate-400 hover:text-slate-600 underline">
                Tümünü temizle
            </a>
        </div>
    @endif

    {{-- Filter Modal --}}
    <div id="filter-modal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-black/50 transition-opacity" onclick="closeFilterModal()"></div>
        <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl p-6 overflow-y-auto flex flex-col">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-bold text-slate-900">Filtrele</h2>
                <button type="button" onclick="closeFilterModal()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="filter-form" method="GET" action="{{ route('expenses.index') }}" class="flex flex-col flex-1">
                @if ($filters['filterSearch'] ?? '')
                    <input type="hidden" name="search" value="{{ $filters['filterSearch'] }}">
                @endif
                @if ($sortBy !== 'expense_date')
                    <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                @endif
                @if ($sortDirection !== 'desc')
                    <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">
                @endif

                <div class="flex-1 space-y-4">
                    <div>
                        <label id="expense-period-label" class="block text-sm font-medium text-slate-700 mb-1.5">Tarih/Dönem</label>
                        <input type="hidden" id="expense-period" name="period" value="{{ $filters['filterPeriod'] }}">
                        <button type="button" id="expense-period-open" aria-haspopup="dialog" aria-labelledby="expense-period-label expense-period-text"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-left text-base focus:outline-none focus:ring-2 focus:ring-slate-300 sm:py-2 sm:text-sm">
                            <span id="expense-period-text">{{ $filters['filterPeriod'] ? \Carbon\Carbon::parse($filters['filterPeriod'].'-01')->locale('tr')->translatedFormat('F Y') : 'Tüm Dönemler' }}</span>
                        </button>
                    </div>

                    <div>
                        <label id="expense-status-label" class="block text-sm font-medium text-slate-700 mb-1.5">Durum</label>
                        <div id="expense-status-inputs">
                            @foreach ($filters['filterStatus'] as $status)
                                <input type="hidden" name="status[]" value="{{ $status }}">
                            @endforeach
                        </div>
                        <button type="button" id="expense-status-open" aria-haspopup="dialog" aria-labelledby="expense-status-label expense-status-text"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-left text-base focus:outline-none focus:ring-2 focus:ring-slate-300 sm:py-2 sm:text-sm">
                            <span id="expense-status-text">{{ $filters['filterStatus'] ? implode(', ', array_map(fn ($status) => $statusLabels[$status], $filters['filterStatus'])) : 'Tüm Durumlar' }}</span>
                        </button>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Kategori</label>
                        <select name="category" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-base focus:outline-none focus:ring-2 focus:ring-slate-300 bg-white sm:py-2 sm:text-sm">
                            <option value="">Tüm Kategoriler</option>
                            @foreach ($categories as $id => $name)
                                <option value="{{ $id }}" @selected((string)$filters['filterCategory'] === (string)$id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($hasImported)
                        <label class="flex items-center justify-between cursor-pointer select-none">
                            <span class="text-sm font-medium text-slate-700">Devir Öncesini Göster</span>
                            <input type="checkbox" name="show_imported" value="1" role="switch" class="peer sr-only" {{ $showImported ? 'checked' : '' }}>
                            <span aria-hidden="true" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full bg-slate-200 transition-colors peer-checked:bg-slate-950 peer-focus-visible:outline-none peer-focus-visible:ring-2 peer-focus-visible:ring-slate-300 after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-transform after:content-[''] peer-checked:after:translate-x-5"></span>
                        </label>
                    @endif
                </div>

                <div class="mt-6 flex items-center gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="resetFilters()"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Temizle
                    </button>
                    <button type="button" onclick="closeFilterModal()"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        İptal
                    </button>
                    <button type="submit"
                        class="ml-auto rounded-xl bg-slate-950 px-6 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                        Uygula
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <a href="{{ route('expenses.index', ['sort_by' => 'expense_date', 'sort_direction' => $sortBy === 'expense_date' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 hover:text-slate-700">Tarih @if ($sortBy === 'expense_date')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Son Ödeme</th>
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
                        $isOverdue = ! $expense->is_paid && $expense->due_date && $expense->due_date->isPast();
                        $remaining = $expense->remaining_amount ?? $expense->amount;
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors cursor-pointer" onclick="window.location.href='{{ route('expenses.show', $expense) }}'">
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
                        <td class="px-5 py-4">
                            <div class="text-slate-700 tabular-nums">{{ $expense->expense_date?->format('d.m.Y') ?? '-' }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $periodText ?? '-' }}</div>
                        </td>
                        <td class="px-5 py-4">
                            @if ($expense->due_date)
                                <div class="tabular-nums {{ $isOverdue ? 'text-red-600 font-semibold' : 'text-slate-700' }}">{{ $expense->due_date->format('d.m.Y') }}</div>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                            <div class="mt-1 text-xs text-slate-500">{{ $expense->categoryRelation?->name ?? $expense->category ?? '—' }}</div>
                        </td>
                        <td class="px-5 py-4 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($expense->amount, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4 text-right tabular-nums">
                            @if ($expense->is_paid || $remaining == 0)
                                <span class="text-emerald-600 font-semibold">—</span>
                            @elseif ($remaining != $expense->amount)
                                <span class="text-amber-600 font-semibold">{{ number_format($remaining, 2, ',', '.') }} TL</span>
                            @else
                                <span class="text-slate-700 font-semibold">{{ number_format($remaining, 2, ',', '.') }} TL</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if ($isOverdue)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Gecikti</span>
                            @elseif ($expense->is_paid)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Ödendi</span>
                            @elseif (($expense->paid_amount ?? 0) > 0)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Kısmi</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Bekliyor</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                            <div class="flex items-center justify-end gap-2">
                                @unless ($expense->is_paid)
                                    <button type="button"
                                            onclick="openExpensePaymentModal({{ $expense->id }}, {{ $expense->amount }}, '{{ addslashes($expense->description ?: $expense->category) }}', '{{ addslashes($expense->category ?: '-') }}', '{{ addslashes($expense->account?->name ?: '-') }}')"
                                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition-colors">
                                        Öde
                                    </button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Henüz gider kaydı yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Card View --}}
    <div class="md:hidden space-y-3">
        @forelse ($expenses as $expense)
            @php
                $isOverdue = ! $expense->is_paid && $expense->due_date && $expense->due_date->isPast();
                $remaining = $expense->remaining_amount ?? $expense->amount;
                $expenseCategory = $expense->categoryRelation?->name ?? $expense->category;
                $statusColor = $isOverdue ? 'red' : ($expense->is_paid ? 'emerald' : (($expense->paid_amount ?? 0) > 0 ? 'amber' : 'slate'));
                $statusLabel = $isOverdue ? 'Gecikmiş' : ($expense->is_paid ? 'Ödendi' : (($expense->paid_amount ?? 0) > 0 ? 'Kısmi' : 'Bekliyor'));
            @endphp
            <div class="rounded-xl bg-white p-3 shadow-sm border border-slate-200 hover:bg-slate-50 transition-colors">
                {{-- Parça 1: Bilgiler --}}
                <a href="{{ route('expenses.show', $expense) }}" class="block">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1 text-base font-bold text-slate-900 break-words">{{ $expense->description }}</div>
                        <span class="shrink-0 pt-0.5 text-xs text-slate-500 tabular-nums" aria-label="Gider tarihi">{{ $expense->expense_date?->format('d.m.Y') ?? '-' }}</span>
                    </div>
                    @if ($expense->due_date)
                        <div class="text-xs mt-1 {{ $isOverdue ? 'text-red-600 font-semibold' : 'text-slate-500' }}">Son Ödeme: {{ $expense->due_date->format('d.m.Y') }}{{ $expenseCategory ? ' · '.$expenseCategory : '' }}</div>
                    @elseif ($expenseCategory)
                        <div class="text-xs text-slate-500 mt-1">{{ $expenseCategory }}</div>
                    @endif
                    @if ($expense->is_imported)
                        <div class="mt-1.5">
                            <span class="inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>
                        </div>
                    @endif
                </a>

                {{-- Parça 2: Tutarlar | Parça 3: Öde butonu --}}
                <div class="mt-2 flex items-end justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('expenses.show', $expense) }}" class="block">
                            <div class="font-bold text-slate-900">{{ number_format($expense->amount, 2, ',', '.') }} TL</div>
                            @if ($remaining > 0 && $remaining != $expense->amount)
                                <div class="text-xs text-amber-600 font-semibold">Kalan: {{ number_format($remaining, 2, ',', '.') }} TL</div>
                            @endif
                        </a>
                        @php
                            $statusColorBg = match ($statusColor) { 'emerald' => 'bg-emerald-50', 'red' => 'bg-red-50', 'amber' => 'bg-amber-50', default => 'bg-slate-100' };
                            $statusColorText = match ($statusColor) { 'emerald' => 'text-emerald-700', 'red' => 'text-red-700', 'amber' => 'text-amber-700', default => 'text-slate-700' };
                            $statusColorDot = match ($statusColor) { 'emerald' => 'bg-emerald-600', 'red' => 'bg-red-600', 'amber' => 'bg-amber-600', default => 'bg-slate-500' };
                        @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-md {{ $statusColorBg }} px-2 py-1 text-xs font-semibold {{ $statusColorText }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $statusColorDot }}"></span>
                            {{ $statusLabel }}
                        </span>
                    </div>
                    @if (!$expense->is_paid)
                        <button type="button"
                                onclick="openExpensePaymentModal({{ $expense->id }}, {{ $expense->amount }}, '{{ addslashes($expense->description ?: $expense->category) }}', '{{ addslashes($expense->category ?: '-') }}', '{{ addslashes($expense->account?->name ?: '-') }}')"
                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition-colors">
                            Öde
                        </button>
                    @endif
                </div>
            </div>
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

    @include('expenses._payment_modal', ['cashBoxes' => $cashBoxes])
    @include('components.period-picker', ['periodPickerId' => 'expense'])
    @include('expenses._status-picker')

    <script>
        function openFilterModal() {
            document.getElementById('filter-modal').classList.remove('hidden');
        }

        function closeFilterModal() {
            document.getElementById('filter-modal').classList.add('hidden');
        }

        function resetFilters() {
            var form = document.getElementById('filter-form');
            form.querySelectorAll('select').forEach(function (el) { el.value = ''; });
            document.getElementById('expense-period').value = '';
            document.getElementById('expense-status-inputs').replaceChildren();
            var cb = form.querySelector('input[name="show_imported"]');
            if (cb) cb.checked = false;
            form.submit();
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !document.getElementById('expense-period-dialog').open && !document.getElementById('expense-status-dialog').open) closeFilterModal();
        });

    </script>
    @include('expenses._payment_modal_script')
@endsection
