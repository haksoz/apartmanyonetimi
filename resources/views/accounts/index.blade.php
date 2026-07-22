@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Hesaplar</h1>
            <p class="mt-1 text-sm text-slate-500">Daire sakinleri, tedarikçiler ve apartmanla ilişkili tüm hesaplar.</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('accounts.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Hesap Ekle</a>
        </div>
    </div>

    {{-- Arama + Filtre --}}
    <div class="mb-4 flex flex-row items-center justify-between gap-3">
        {{-- Arama --}}
        <form method="GET" action="{{ route('accounts.index') }}" class="flex gap-2 flex-1 min-w-0">
            @if ($filters['filterType'])
                <input type="hidden" name="type" value="{{ $filters['filterType'] }}">
            @endif
            @if (($filters['filterStatus'] ?? 'active') !== 'active')
                <input type="hidden" name="status" value="{{ $filters['filterStatus'] }}">
            @endif
            @if ($sortBy !== 'unit_no')
                <input type="hidden" name="sort" value="{{ $sortBy }}">
            @endif
            @if ($sortDir !== 'asc')
                <input type="hidden" name="direction" value="{{ $sortDir }}">
            @endif
            <input type="text" name="search" value="{{ $filters['filterSearch'] }}"
                placeholder="Ad veya daire no..."
                class="flex-1 rounded-xl border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            <button type="submit" class="h-9 rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ara</button>
            @if ($filters['filterSearch'])
                <a href="{{ route('accounts.index', request()->except('search')) }}"
                   class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-50">✕</a>
            @endif
        </form>

        @php
            $activeFilterCount = 0;
            if ($filters['filterType']) $activeFilterCount++;
            if (($filters['filterStatus'] ?? 'active') !== 'active') $activeFilterCount++;
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
        $typeLabels = ['owner' => 'Kat Maliki', 'tenant' => 'Kiracı', 'supplier' => 'Tedarikçi'];
        $statusLabels = ['active' => 'Aktif', 'inactive' => 'Pasif', 'all' => 'Tümü'];
    @endphp

    @if ($activeFilterCount > 0)
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-slate-500">Aktif filtreler:</span>
            @if ($filters['filterType'])
                <a href="{{ route('accounts.index', request()->except('type')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    {{ $typeLabels[$filters['filterType']] ?? $filters['filterType'] }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            @if (($filters['filterStatus'] ?? 'active') !== 'active')
                <a href="{{ route('accounts.index', request()->except('status')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    {{ $statusLabels[$filters['filterStatus']] ?? $filters['filterStatus'] }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            <a href="{{ route('accounts.index', request()->only(['search', 'sort', 'direction'])) }}"
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

            <form id="filter-form" method="GET" action="{{ route('accounts.index') }}" class="flex flex-col flex-1">
                @if ($filters['filterSearch'])
                    <input type="hidden" name="search" value="{{ $filters['filterSearch'] }}">
                @endif
                @if ($sortBy !== 'unit_no')
                    <input type="hidden" name="sort" value="{{ $sortBy }}">
                @endif
                @if ($sortDir !== 'asc')
                    <input type="hidden" name="direction" value="{{ $sortDir }}">
                @endif

                <div class="flex-1 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Hesap Türü</label>
                        <select name="type" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 bg-white">
                            <option value="">Tüm Tipler</option>
                            <option value="owner"    @selected($filters['filterType'] === 'owner')>Kat Maliki</option>
                            <option value="tenant"   @selected($filters['filterType'] === 'tenant')>Kiracı</option>
                            <option value="supplier" @selected($filters['filterType'] === 'supplier')>Tedarikçi</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Durum</label>
                        <select name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 bg-white">
                            <option value="active"   @selected(($filters['filterStatus'] ?? 'active') === 'active')>Aktif</option>
                            <option value="inactive" @selected(($filters['filterStatus'] ?? '') === 'inactive')>Pasif</option>
                            <option value="all"      @selected(($filters['filterStatus'] ?? '') === 'all')>Tümü</option>
                        </select>
                    </div>
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

    {{-- Hesapsız Ödemeler Kartı --}}
    @if($orphanPaymentsCount > 0)
    <div class="mb-5">
        <a href="{{ route('payments.index') }}?filter=orphan" class="flex items-center justify-between p-4 rounded-xl bg-amber-50 border border-amber-200 hover:bg-amber-100 transition-colors">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-amber-100">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-amber-900">Hesapsız Ödemeler</div>
                    <div class="text-sm text-amber-700">{{ $orphanPaymentsCount }} adet tahsis edilecek ödeme</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold text-amber-900">{{ number_format($orphanPaymentsTotal, 2, ',', '.') }} TL</div>
                <div class="text-xs text-amber-600">Tahsis için tıklayın</div>
            </div>
        </a>
    </div>
    @endif

    @php
        $sortBy  = $filters['sortBy']  ?? 'name';
        $sortDir = $filters['sortDir'] ?? 'asc';
        $baseParams = array_filter([
            'search'    => $filters['filterSearch'],
            'type'      => $filters['filterType'],
            'status'    => ($filters['filterStatus'] ?? 'active') !== 'active' ? $filters['filterStatus'] : null,
        ]);
        $sortLink = function (string $col) use ($sortBy, $sortDir, $baseParams): string {
            $dir = ($sortBy === $col && $sortDir === 'asc') ? 'desc' : 'asc';
            return route('accounts.index', array_merge($baseParams, ['sort' => $col, 'direction' => $dir]));
        };
        $sortIcon = function (string $col) use ($sortBy, $sortDir): string {
            if ($sortBy !== $col) {
                return '<span class="inline-flex flex-col leading-none ml-1 text-slate-300"><span>▲</span><span>▼</span></span>';
            }
            return $sortDir === 'asc'
                ? '<span class="ml-1 text-slate-700">▲</span>'
                : '<span class="ml-1 text-slate-700">▼</span>';
        };
    @endphp

    {{-- Desktop Table View --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-center">
                        <a href="{{ $sortLink('unit_no') }}" class="inline-flex items-center gap-0.5 hover:text-slate-700">Daire {!! $sortIcon('unit_no') !!}</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <a href="{{ $sortLink('name') }}" class="inline-flex items-center gap-0.5 hover:text-slate-700">Adı Soyadı / Ünvan {!! $sortIcon('name') !!}</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <a href="{{ $sortLink('type') }}" class="inline-flex items-center gap-0.5 hover:text-slate-700">Tip {!! $sortIcon('type') !!}</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">
                        <a href="{{ $sortLink('debit_total') }}" class="inline-flex items-center gap-0.5 hover:text-slate-700">Borç {!! $sortIcon('debit_total') !!}</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">
                        <a href="{{ $sortLink('credit_total') }}" class="inline-flex items-center gap-0.5 hover:text-slate-700">Alacak {!! $sortIcon('credit_total') !!}</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">
                        <a href="{{ $sortLink('balance') }}" class="inline-flex items-center gap-0.5 hover:text-slate-700">Bakiye {!! $sortIcon('balance') !!}</a>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($accounts as $account)
                    @php
                        $debit = (float) ($account->debit_total ?? 0);
                        $credit = (float) ($account->credit_total ?? 0);
                        $balance = $credit - $debit;
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors cursor-pointer {{ ! $account->is_active ? 'bg-slate-50/50 text-slate-400' : '' }}" onclick="window.location.href='{{ route('accounts.show', $account) }}'">
                        <td class="px-5 py-4 text-center tabular-nums">
                            @if ($account->unit)
                                <span class="text-xs font-semibold text-slate-700">No: {{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}</span>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-semibold {{ $account->is_active ? 'text-slate-900' : 'text-slate-500' }}">{{ $account->name }}</div>
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-600">{{ $account->type_label }}</td>
                        <td class="px-5 py-4">
                            @if ($account->is_active)
                                <span class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 font-medium">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Aktif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-500">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-300 inline-block"></span> Pasif
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right font-medium text-red-600 tabular-nums">{{ number_format($debit, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4 text-right font-medium text-emerald-600 tabular-nums">{{ number_format($credit, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4 text-right font-semibold tabular-nums {{ $balance < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                            <div class="flex items-center justify-end gap-2">
                                <span>{{ number_format(abs($balance), 2, ',', '.') }} TL</span>
                                @if ($balance < 0)
                                    <span class="text-xs text-red-500">(B)</span>
                                @elseif ($balance > 0)
                                    <span class="text-xs text-emerald-500">(A)</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Henüz hesap yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Card View --}}
    <div class="md:hidden space-y-3">
        @forelse ($accounts as $account)
            @php
                $debit = (float) ($account->debit_total ?? 0);
                $credit = (float) ($account->credit_total ?? 0);
                $balance = $credit - $debit;
            @endphp
            <div class="rounded-xl bg-white p-3 shadow-sm border border-slate-200 hover:bg-slate-50 transition-colors">
                <a href="{{ route('accounts.show', $account) }}" class="block">
                    <div class="flex items-start gap-2 flex-wrap">
                        <div class="text-base font-bold text-slate-900">{{ $account->name }}</div>
                        @if ($account->is_active)
                            <span class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-500">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-300 inline-block"></span> Pasif
                            </span>
                        @endif
                    </div>
                    <div class="text-xs text-slate-600 mt-1">
                        <span>{{ $account->type_label }}</span>
                        @if ($account->unit)
                            <span class="mx-1 text-slate-400">•</span>
                            <span>Daire {{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}</span>
                        @endif
                    </div>
                    <div class="mt-2 grid grid-cols-3 gap-2 text-center text-xs">
                        <div>
                            <div class="text-slate-500">Borç</div>
                            <div class="font-medium text-red-600 tabular-nums">{{ number_format($debit, 2, ',', '.') }} TL</div>
                        </div>
                        <div>
                            <div class="text-slate-500">Alacak</div>
                            <div class="font-medium text-emerald-600 tabular-nums">{{ number_format($credit, 2, ',', '.') }} TL</div>
                        </div>
                        <div>
                            <div class="text-slate-500">Bakiye</div>
                            <div class="font-bold {{ $balance < 0 ? 'text-red-600' : 'text-emerald-600' }} tabular-nums">
                                {{ number_format(abs($balance), 2, ',', '.') }} TL
                                @if ($balance < 0)
                                    <span class="inline-block rounded-md bg-red-50 px-1.5 py-0.5 text-xs font-semibold text-red-700">(B)</span>
                                @elseif ($balance > 0)
                                    <span class="inline-block rounded-md bg-emerald-50 px-1.5 py-0.5 text-xs font-semibold text-emerald-700">(A)</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm">
                Henüz hesap yok.
            </div>
        @endforelse
    </div>

    {{-- Sayfalama --}}
    @if ($accounts->hasPages())
        <div class="mt-6">
            {{ $accounts->links() }}
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
            form.submit();
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeFilterModal();
        });
    </script>
@endsection
