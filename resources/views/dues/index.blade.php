@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Aidatlar</h1>
            <p class="mt-1 text-sm text-slate-500">Tekil veya toplu aidat tahakkukları burada yönetilecek.</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('dues.export', array_merge(request()->query(), [])) }}"
               class="flex-1 md:flex-none rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 text-center">
                Excel'e Aktar
            </a>
            @if($isOwner)
                <a href="{{ route('dues.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 text-center">Borçlandır</a>
                <a href="{{ route('dues.batch.create') }}" class="flex-1 md:flex-none rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 text-center">Toplu Borçlandır</a>
            @endif
        </div>
    </div>

    {{-- Arama + Filtre --}}
    <div class="mb-4 flex flex-row items-center justify-between gap-3">
        {{-- Arama --}}
        <form method="GET" action="{{ route('dues.index') }}" class="flex gap-2 flex-1 min-w-0">
            @if ($filters['filterBatchId'])
                <input type="hidden" name="batch_id" value="{{ $filters['filterBatchId'] }}">
            @endif
            @if ($filters['filterStartDate'])
                <input type="hidden" name="start_date" value="{{ $filters['filterStartDate'] }}">
            @endif
            @if ($filters['filterEndDate'])
                <input type="hidden" name="end_date" value="{{ $filters['filterEndDate'] }}">
            @endif
            @if ($filters['filterPeriod'])
                <input type="hidden" name="period" value="{{ $filters['filterPeriod'] }}">
            @endif
            @if ($filters['filterStatus'])
                <input type="hidden" name="status" value="{{ $filters['filterStatus'] }}">
            @endif
            @if ($filters['filterSource'])
                <input type="hidden" name="source" value="{{ $filters['filterSource'] }}">
            @endif
            @if ($filters['filterUnitId'])
                <input type="hidden" name="unit_id" value="{{ $filters['filterUnitId'] }}">
            @endif
            @if ($filters['filterAccountType'])
                <input type="hidden" name="account_type" value="{{ $filters['filterAccountType'] }}">
            @endif
            @if ($showImported)
                <input type="hidden" name="show_imported" value="1">
            @endif
            @if ($sortBy !== 'created_at')
                <input type="hidden" name="sort_by" value="{{ $sortBy }}">
            @endif
            @if ($sortDirection !== 'desc')
                <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">
            @endif
            <input type="text" name="search" value="{{ $filters['filterSearch'] ?? '' }}"
                placeholder="Ad, daire no veya açıklama..."
                class="flex-1 rounded-xl border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            <button type="submit" class="h-9 rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ara</button>
            @if ($filters['filterSearch'] ?? '')
                <a href="{{ route('dues.index', request()->except('search')) }}"
                   class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-50">✕</a>
            @endif
        </form>

        @php
            $activeFilterCount = 0;
            if ($filters['filterStartDate'] || $filters['filterEndDate']) $activeFilterCount++;
            if ($filters['filterPeriod']) $activeFilterCount++;
            if ($filters['filterStatus']) $activeFilterCount++;
            if ($filters['filterSource']) $activeFilterCount++;
            if ($filters['filterUnitId']) $activeFilterCount++;
            if ($filters['filterAccountType']) $activeFilterCount++;
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
        $statusLabels = ['unpaid' => 'Bekliyor', 'partial' => 'Kısmen Ödendi', 'paid' => 'Ödendi', 'overdue' => 'Gecikmiş'];
        $accountTypeLabels = ['owner' => 'Kat Maliki', 'tenant' => 'Kiracı', 'supplier' => 'Tedarikçi'];
        $sourceLabels = ['plan' => 'Aidat Planı', 'batch' => 'Toplu Borçlandırma', 'manual' => 'Manuel'];
    @endphp

    @if ($activeFilterCount > 0)
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-slate-500">Aktif filtreler:</span>
            @if ($filters['filterStartDate'] || $filters['filterEndDate'])
                <a href="{{ route('dues.index', request()->except(['start_date', 'end_date'])) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    {{ $filters['filterStartDate'] ? \Carbon\Carbon::parse($filters['filterStartDate'])->format('d.m.Y') : '...' }}
                    -
                    {{ $filters['filterEndDate'] ? \Carbon\Carbon::parse($filters['filterEndDate'])->format('d.m.Y') : '...' }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            @if ($filters['filterPeriod'])
                <a href="{{ route('dues.index', request()->except('period')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    {{ \Carbon\Carbon::parse($filters['filterPeriod'] . '-01')->format('m/Y') }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            @if ($filters['filterStatus'])
                <a href="{{ route('dues.index', request()->except('status')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    {{ $statusLabels[$filters['filterStatus']] ?? $filters['filterStatus'] }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            @if ($filters['filterUnitId'])
                @php $activeUnit = $units->firstWhere('id', $filters['filterUnitId']); @endphp
                <a href="{{ route('dues.index', request()->except('unit_id')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    Daire {{ $activeUnit ? str_pad($activeUnit->unit_no, 2, '0', STR_PAD_LEFT) : $filters['filterUnitId'] }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            @if ($filters['filterAccountType'])
                <a href="{{ route('dues.index', request()->except('account_type')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    {{ $accountTypeLabels[$filters['filterAccountType']] ?? $filters['filterAccountType'] }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            @if ($filters['filterSource'])
                <a href="{{ route('dues.index', request()->except('source')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    {{ $sourceLabels[$filters['filterSource']] ?? $filters['filterSource'] }}
                    <span class="text-slate-500">&times;</span>
                </a>
            @endif
            @if ($showImported)
                <a href="{{ route('dues.index', request()->except('show_imported')) }}"
                   class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">
                    Devir Öncesi
                    <span class="text-blue-500">&times;</span>
                </a>
            @endif
            <a href="{{ route('dues.index', request()->only(['search', 'batch_id', 'sort_by', 'sort_direction'])) }}"
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

            <form id="filter-form" method="GET" action="{{ route('dues.index') }}" class="flex flex-col flex-1">
                @if ($filters['filterSearch'] ?? '')
                    <input type="hidden" name="search" value="{{ $filters['filterSearch'] }}">
                @endif
                @if ($filters['filterBatchId'])
                    <input type="hidden" name="batch_id" value="{{ $filters['filterBatchId'] }}">
                @endif
                @if ($sortBy !== 'created_at')
                    <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                @endif
                @if ($sortDirection !== 'desc')
                    <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">
                @endif

                <div class="flex-1 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Başlangıç Tarihi</label>
                        <input type="date" name="start_date" value="{{ $filters['filterStartDate'] }}"
                            class="w-full rounded-xl border border-slate-300 px-3 py-3 text-base focus:outline-none focus:ring-2 focus:ring-slate-300 sm:py-2 sm:text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Bitiş Tarihi</label>
                        <input type="date" name="end_date" value="{{ $filters['filterEndDate'] }}"
                            class="w-full rounded-xl border border-slate-300 px-3 py-3 text-base focus:outline-none focus:ring-2 focus:ring-slate-300 sm:py-2 sm:text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Dönem</label>
                        <input type="month" name="period" value="{{ $filters['filterPeriod'] }}"
                            class="w-full rounded-xl border border-slate-300 px-3 py-3 text-base focus:outline-none focus:ring-2 focus:ring-slate-300 sm:py-2 sm:text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Durum</label>
                        <select name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 bg-white">
                            <option value="">Tüm Durumlar</option>
                            <option value="unpaid"  {{ $filters['filterStatus'] === 'unpaid'  ? 'selected' : '' }}>Bekliyor</option>
                            <option value="partial" {{ $filters['filterStatus'] === 'partial' ? 'selected' : '' }}>Kısmen Ödendi</option>
                            <option value="paid"    {{ $filters['filterStatus'] === 'paid'    ? 'selected' : '' }}>Ödendi</option>
                            <option value="overdue" {{ $filters['filterStatus'] === 'overdue' ? 'selected' : '' }}>Gecikmiş</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Daire</label>
                        <select name="unit_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 bg-white">
                            <option value="">Tüm Daireler</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}" {{ $filters['filterUnitId'] == $unit->id ? 'selected' : '' }}>Daire {{ str_pad($unit->unit_no, 2, '0', STR_PAD_LEFT) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Hesap Türü</label>
                        <select name="account_type" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 bg-white">
                            <option value="">Tüm Hesaplar</option>
                            <option value="owner" {{ $filters['filterAccountType'] === 'owner' ? 'selected' : '' }}>Kat Maliki</option>
                            <option value="tenant" {{ $filters['filterAccountType'] === 'tenant' ? 'selected' : '' }}>Kiracı</option>
                            <option value="supplier" {{ $filters['filterAccountType'] === 'supplier' ? 'selected' : '' }}>Tedarikçi</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Kaynak</label>
                        <select name="source" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 bg-white">
                            <option value="">Tüm Kaynaklar</option>
                            <option value="plan"   {{ $filters['filterSource'] === 'plan'   ? 'selected' : '' }}>Aidat Planı</option>
                            <option value="batch"  {{ $filters['filterSource'] === 'batch'  ? 'selected' : '' }}>Toplu Borçlandırma</option>
                            <option value="manual" {{ $filters['filterSource'] === 'manual' ? 'selected' : '' }}>Manuel</option>
                        </select>
                    </div>

                    @if ($hasImported)
                        <label class="flex items-center justify-between cursor-pointer select-none">
                            <span class="text-sm font-medium text-slate-700">Devir Öncesini Göster</span>
                            <input type="checkbox" name="show_imported" value="1" class="peer sr-only" {{ $showImported ? 'checked' : '' }}>
                            <span class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full bg-slate-200 transition-colors peer-checked:bg-slate-950 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-slate-300">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform translate-x-1 peer-checked:translate-x-6"></span>
                            </span>
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

    @if ($filters['filterBatchId'])
        <div class="mb-3 flex items-center gap-1.5 rounded-full bg-violet-50 border border-violet-200 px-3 py-1.5 text-xs font-semibold text-violet-700 w-fit">
            Toplu kayıt filtresi aktif
            <a href="{{ route('dues.index') }}" class="ml-1 text-violet-500 hover:text-violet-800">&times;</a>
        </div>
    @endif

    {{-- Toplu Aksiyon Barı --}}
    <form id="form-bulk-destroy" method="POST" action="{{ route('dues.bulk-destroy') }}" onsubmit="return confirmBulkDelete()">
        @csrf
        @method('DELETE')
        <input type="hidden" name="ids" id="bulk-ids">
        <div id="bulk-action-bar" class="hidden mb-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
            <span id="bulk-count" class="text-sm font-semibold text-red-700"></span>
            <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Seçilileri Sil</button>
            <button type="button" onclick="clearSelection()" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Seçimi Temizle</button>
        </div>
    </form>

    {{-- Desktop Table View --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-3.5 w-10">
                        <input type="checkbox" id="check-all" class="rounded border-slate-300 text-slate-700 cursor-pointer">
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Oluşturulma</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Son Ödeme</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">
                        <a href="{{ route('dues.index', array_merge(request()->query(), ['sort_by' => 'amount', 'sort_direction' => $sortBy === 'amount' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center justify-end gap-1 hover:text-slate-700">Tutar @if ($sortBy === 'amount')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">Kalan</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($dues as $due)
                    @php
                        $isOverdue = $due->status !== 'paid' && $due->due_date && $due->due_date->isPast();
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors cursor-pointer" data-id="{{ $due->id }}" onclick="window.location.href='{{ route('dues.show', $due) }}'">
                        <td class="px-4 py-4" onclick="event.stopPropagation()">
                            <input type="checkbox" class="row-check rounded border-slate-300 text-slate-700 cursor-pointer" value="{{ $due->id }}" data-status="{{ $due->status }}">
                        </td>
                        <td class="px-5 py-4">
                            <div class="text-slate-900 font-medium">
                                {{ $due->description ?: '-' }}
                                @if ($due->is_imported)
                                    <span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>
                                @endif
                            </div>
                            @if ($due->account)
                                @php
                                    $title = match($due->account->type) {
                                        App\Models\Account::TYPE_OWNER => 'Kat Maliki',
                                        App\Models\Account::TYPE_TENANT => 'Kiracı',
                                        App\Models\Account::TYPE_SUPPLIER => 'Tedarikçi',
                                        default => ''
                                    };
                                @endphp
                                <div class="text-xs text-slate-500 mt-1">
                                    @if ($title){{ $title }} @endif{{ $due->account->name }}
                                    @if ($due->unit) - Daire {{ str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) }}@endif
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="text-slate-700 tabular-nums">{{ $due->created_at_manual ? \Carbon\Carbon::parse($due->created_at_manual)->format('d.m.Y') : $due->created_at->format('d.m.Y') }}</div>
                            <div class="text-xs text-slate-500 mt-1">
                                @if ($due->batch?->plan)
                                    Aidat Planı ile
                                @elseif ($due->batch)
                                    Toplu Borçlandırma
                                @else
                                    Manuel
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            @if ($due->due_date)
                                <div class="tabular-nums {{ $isOverdue ? 'text-red-600 font-semibold' : 'text-slate-700' }}">{{ $due->due_date->format('d.m.Y') }}</div>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                            <div class="text-xs text-slate-500 mt-1">{{ $due->due_type_label }}{{ $due->category ? ' · '.$due->category->name : '' }}</div>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="font-semibold text-slate-900 tabular-nums">{{ number_format($due->amount, 2, ',', '.') }} TL</div>
                        </td>
                        <td class="px-5 py-4 text-right tabular-nums">
                            @if ($due->remaining_amount == 0)
                                <span class="text-emerald-600 font-semibold">—</span>
                            @elseif ($due->remaining_amount != $due->amount)
                                <span class="text-amber-600 font-semibold">{{ number_format($due->remaining_amount, 2, ',', '.') }} TL</span>
                            @else
                                <span class="text-slate-700 font-semibold">{{ number_format($due->remaining_amount, 2, ',', '.') }} TL</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if ($due->computed_status === 'overdue')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                                    Gecikti
                                </span>
                            @elseif ($due->computed_status === 'paid')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                    Ödendi
                                </span>
                            @elseif ($due->computed_status === 'partial')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    Kısmi
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                    Bekliyor
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                            <div class="flex items-center justify-end gap-2">
                                @if ($due->computed_status !== 'paid')
                                    <button type="button"
                                            onclick="openDuePaymentModal({{ $due->id }}, {{ $due->remaining_amount }}, '{{ addslashes($due->description ?: 'Aidat') }}', '{{ addslashes($due->account?->name ?: '-') }}', '{{ $due->unit?->unit_no ? str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-' }}')"
                                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition-colors">
                                        Tahsil Et
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-slate-400">Henüz aidat kaydı yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Card View --}}
    <div class="md:hidden space-y-3">
        @forelse ($dues as $due)
            @php
                $isOverdue = $due->status !== 'paid' && $due->due_date && $due->due_date->isPast();
            @endphp
            <div class="rounded-xl bg-white p-3 shadow-sm border border-slate-200 hover:bg-slate-50 transition-colors">
                {{-- Parça 1: Bilgiler --}}
                <a href="{{ route('dues.show', $due) }}" class="block">
                    @if ($due->description)
                        <div class="text-base font-bold text-slate-900">{{ $due->description }}</div>
                    @endif
                    <div class="text-xs text-slate-600 mt-1">
                        <span>{{ $due->created_at_manual ? \Carbon\Carbon::parse($due->created_at_manual)->format('d.m.Y') : $due->created_at->format('d.m.Y') }}</span>
                        <span class="mx-1 text-slate-400">•</span>
                        <span>{{ $due->unit ? 'No '.str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-' }}</span>
                        <span class="mx-1 text-slate-400">•</span>
                        <span>{{ $due->account?->name }}</span>
                    </div>
                    @if ($due->due_date)
                        <div class="text-xs mt-1 {{ $isOverdue ? 'text-red-600 font-semibold' : 'text-slate-500' }}">Son Ödeme: {{ $due->due_date->format('d.m.Y') }} · {{ $due->due_type_label }}{{ $due->category ? ' / '.$due->category->name : '' }}</div>
                    @endif
                    <div class="mt-1.5 flex flex-wrap items-center gap-1">
                        @if ($due->computed_status === 'overdue')
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-red-50 px-2 py-1 text-xs font-semibold text-red-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-red-600"></span>
                                Gecikmiş
                            </span>
                        @elseif ($due->computed_status === 'paid')
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                                Ödendi
                            </span>
                        @elseif ($due->computed_status === 'partial')
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                                Kısmi
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                                Bekliyor
                            </span>
                        @endif
                        @if ($due->is_imported)
                            <span class="inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>
                        @endif
                    </div>
                </a>

                {{-- Parça 2: Tutarlar | Parça 3: Tahsil Et --}}
                <div class="mt-2 flex items-end justify-between gap-2">
                    <a href="{{ route('dues.show', $due) }}" class="block">
                        <div class="font-bold text-slate-900">{{ number_format($due->amount, 2, ',', '.') }} TL</div>
                        @if($due->remaining_amount > 0 && $due->remaining_amount != $due->amount)
                            <div class="text-xs text-amber-600 font-semibold">Kalan: {{ number_format($due->remaining_amount, 2, ',', '.') }} TL</div>
                        @endif
                    </a>
                    @if ($due->computed_status !== 'paid')
                        <button type="button"
                                onclick="openDuePaymentModal({{ $due->id }}, {{ $due->remaining_amount }}, '{{ addslashes($due->description ?: 'Aidat') }}', '{{ addslashes($due->account?->name ?: '-') }}', '{{ $due->unit?->unit_no ? str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-' }}')"
                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition-colors">
                            Tahsil Et
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm">
                Henüz aidat kaydı yok.
            </div>
        @endforelse
    </div>

    {{-- Sayfalama --}}
    @if ($dues->hasPages())
        <div class="mt-6">
            {{ $dues->links() }}
        </div>
    @endif

    @include('dues._payment_modal', ['cashBoxes' => $cashBoxes])

    <script>
        var selectedIds = new Set();

        function updateBulkBar() {
            var bar   = document.getElementById('bulk-action-bar');
            var count = document.getElementById('bulk-count');
            var input = document.getElementById('bulk-ids');
            if (selectedIds.size > 0) {
                bar.classList.remove('hidden');
                count.textContent = selectedIds.size + ' kayıt seçildi';
                input.value = Array.from(selectedIds).join(',');
            } else {
                bar.classList.add('hidden');
                input.value = '';
            }
        }

        document.getElementById('check-all').addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(function (cb) {
                cb.checked = this.checked;
                if (this.checked) selectedIds.add(cb.value);
                else selectedIds.delete(cb.value);
            }, this);
            updateBulkBar();
        });

        document.querySelectorAll('.row-check').forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (this.checked) selectedIds.add(this.value);
                else selectedIds.delete(this.value);
                document.getElementById('check-all').indeterminate =
                    selectedIds.size > 0 &&
                    selectedIds.size < document.querySelectorAll('.row-check').length;
                document.getElementById('check-all').checked =
                    selectedIds.size === document.querySelectorAll('.row-check').length;
                updateBulkBar();
            });
        });

        function clearSelection() {
            selectedIds.clear();
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
            document.getElementById('check-all').checked = false;
            document.getElementById('check-all').indeterminate = false;
            updateBulkBar();
        }

        function confirmBulkDelete() {
            var blocked = [];
            document.querySelectorAll('.row-check:checked').forEach(function (cb) {
                if (cb.dataset.status === 'paid' || cb.dataset.status === 'partial') {
                    blocked.push(cb.value);
                }
            });
            if (blocked.length > 0) {
                alert(
                    'İşlem gerçekleştirilemedi.\n\n' +
                    'Seçtiğiniz ' + blocked.length + ' kayıt ödenmiş veya kısmen ödenmiş durumda olduğu için silinemez.\n\n' +
                    'Lütfen yalnızca bekleyen ya da gecikmiş kayıtları seçin.'
                );
                return false;
            }
            return confirm(selectedIds.size + ' aidat kaydı silinecek. Devam edilsin mi?');
        }

        function openFilterModal() {
            document.getElementById('filter-modal').classList.remove('hidden');
        }

        function closeFilterModal() {
            document.getElementById('filter-modal').classList.add('hidden');
        }

        function resetFilters() {
            var form = document.getElementById('filter-form');
            form.querySelectorAll('select').forEach(function (el) { el.value = ''; });
            form.querySelectorAll('input[type="date"], input[type="month"]').forEach(function (el) { el.value = ''; });
            var cb = form.querySelector('input[name="show_imported"]');
            if (cb) cb.checked = false;
            form.submit();
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeFilterModal();
        });

        // Tekli aidat tahsilatı popup
        function openDuePaymentModal(dueId, amount, description, accountName, unitNo) {
            const modal = document.getElementById('due-payment-modal');
            const form = document.getElementById('due-payment-form');
            if (!modal || !form) return;

            form.action = (form.dataset.baseUrl || '').replace('__DUE_ID__', dueId);
            document.getElementById('due-payment-due-id').value = dueId;
            document.getElementById('due-payment-amount-input').value = amount;
            document.getElementById('due-payment-description').textContent = description || 'Aidat';
            document.getElementById('due-payment-amount').textContent = amount.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' TL';
            document.getElementById('due-payment-account').textContent = 'No:' + (unitNo || '-') + ' ' + (accountName || '-');

            const descInput = document.getElementById('due-payment-description-input');
            if (descInput) {
                descInput.value = (description ? description + ' Tahsilatı' : 'Aidat Tahsilatı');
            }

            modal.classList.remove('hidden');
        }

        function closeDuePaymentModal() {
            document.getElementById('due-payment-modal')?.classList.add('hidden');
        }

        document.getElementById('due-payment-modal')?.addEventListener('click', function(e) {
            if (e.target === this) closeDuePaymentModal();
        });
    </script>
@endsection
