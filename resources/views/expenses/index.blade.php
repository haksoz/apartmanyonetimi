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
    <div class="mb-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
        {{-- Arama --}}
        <form method="GET" action="{{ route('expenses.index') }}" class="flex gap-2 flex-1 w-full md:w-auto">
            @if ($filters['filterStartDate'])
                <input type="hidden" name="start_date" value="{{ $filters['filterStartDate'] }}">
            @endif
            @if ($filters['filterEndDate'])
                <input type="hidden" name="end_date" value="{{ $filters['filterEndDate'] }}">
            @endif
            @if ($filters['filterStatus'])
                <input type="hidden" name="status" value="{{ $filters['filterStatus'] }}">
            @endif
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
                placeholder="Hesap adı veya tutar..."
                class="flex-1 rounded-xl border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ara</button>
            @if ($filters['filterSearch'] ?? '')
                <a href="{{ route('expenses.index', request()->except('search')) }}"
                   class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-50">✕</a>
            @endif
        </form>

        @php
            $activeFilterCount = 0;
            if ($filters['filterStartDate'] || $filters['filterEndDate']) $activeFilterCount++;
            if ($filters['filterStatus']) $activeFilterCount++;
            if ($filters['filterCategory']) $activeFilterCount++;
            if ($showImported) $activeFilterCount++;
        @endphp

        <button type="button" onclick="openFilterModal()"
            class="flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.238 2.022l-3.158 1.579A2.25 2.25 0 018.25 20.05v-5.83a2.25 2.25 0 00-.659-1.591L2.659 7.197A2.25 2.25 0 012 5.606V4.562c0-.54.384-1.006.917-1.096A49.32 49.32 0 0112 3z"/>
            </svg>
            Filtrele
            @if ($activeFilterCount > 0)
                <span class="ml-1 flex h-5 w-5 items-center justify-center rounded-full bg-white text-xs font-bold text-slate-950">{{ $activeFilterCount }}</span>
            @endif
        </button>
    </div>

    @php
        $statusLabels = ['unpaid' => 'Bekliyor', 'paid' => 'Ödendi'];
        $activeCategory = $filters['filterCategory'] ? $categories->get($filters['filterCategory']) : null;
    @endphp

    @if ($activeFilterCount > 0)
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-slate-500">Aktif filtreler:</span>
            @if ($filters['filterStartDate'] || $filters['filterEndDate'])
                <a href="{{ route('expenses.index', request()->except(['start_date', 'end_date'])) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    {{ $filters['filterStartDate'] ? \Carbon\Carbon::parse($filters['filterStartDate'])->format('d.m.Y') : '...' }}
                    -
                    {{ $filters['filterEndDate'] ? \Carbon\Carbon::parse($filters['filterEndDate'])->format('d.m.Y') : '...' }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            @if ($filters['filterStatus'])
                <a href="{{ route('expenses.index', request()->except('status')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    {{ $statusLabels[$filters['filterStatus']] ?? $filters['filterStatus'] }}
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
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Başlangıç Tarihi</label>
                        <input type="date" name="start_date" value="{{ $filters['filterStartDate'] }}"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Bitiş Tarihi</label>
                        <input type="date" name="end_date" value="{{ $filters['filterEndDate'] ?: \Carbon\Carbon::now()->format('Y-m-d') }}"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Durum</label>
                        <select name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 bg-white">
                            <option value="">Tüm Durumlar</option>
                            <option value="paid"   @selected($filters['filterStatus'] === 'paid')>Ödendi</option>
                            <option value="unpaid" @selected($filters['filterStatus'] === 'unpaid')>Bekliyor</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Kategori</label>
                        <select name="category" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 bg-white">
                            <option value="">Tüm Kategoriler</option>
                            @foreach ($categories as $id => $name)
                                <option value="{{ $id }}" @selected((string)$filters['filterCategory'] === (string)$id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($hasImported)
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-700 select-none">
                            <input type="checkbox" name="show_imported" value="1" class="rounded border-slate-300 text-slate-700 focus:ring-slate-300" {{ $showImported ? 'checked' : '' }}>
                            Devir Öncesini Göster
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
                            {{ $expense->is_paid ? '—' : number_format($expense->remaining_amount ?? $expense->amount, 2, ',', '.') . ' TL' }}
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
            <div class="rounded-xl bg-white p-3 shadow-sm border border-slate-200 hover:bg-slate-50 transition-colors">
                <a href="{{ route('expenses.show', $expense) }}" class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-start gap-2 flex-wrap">
                            @if ($expense->description)
                                <div class="text-base font-bold text-slate-900">{{ $expense->description }}</div>
                            @endif
                            @if ($expense->is_imported)
                                <span class="inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>
                            @endif
                        </div>
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
                    <div class="ml-3 text-right shrink-0">
                        <div class="font-bold text-slate-900">{{ number_format($expense->amount, 2, ',', '.') }} TL</div>
                        @if (!$expense->is_paid)
                            <div class="text-xs text-amber-600 font-semibold mt-0.5">Kalan: {{ number_format($expense->remaining_amount ?? $expense->amount, 2, ',', '.') }} TL</div>
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
                @if (! $expense->is_paid)
                    <div class="mt-2 flex justify-end">
                        <a href="{{ route('expenses.payment.create', $expense) }}"
                           class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition-colors"
                           onclick="event.stopPropagation();">
                            Öde
                        </a>
                    </div>
                @endif
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
            form.querySelectorAll('input[type="date"]').forEach(function (el) { el.value = ''; });
            var cb = form.querySelector('input[name="show_imported"]');
            if (cb) cb.checked = false;
            form.submit();
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeFilterModal();
        });
    </script>
@endsection
