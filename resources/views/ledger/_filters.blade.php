@php
    $kindLabels = ['all' => 'Tümü', 'receipts' => 'Tahsilatlar', 'payments' => 'Ödemeler', 'charges' => 'Borçlandırmalar / Diğer'];
    $filterLabels = ['all' => 'Tümü', 'unallocated' => 'Tahsis Bekleyenler', 'orphan' => 'Hesapsız Ödemeler'];
    $activeFilters = [];
    if ($kind !== 'all') $activeFilters['kind'] = $kindLabels[$kind];
    if ($filter !== 'all') $activeFilters['filter'] = $filterLabels[$filter];
    if ($categoryId) $activeFilters['category_id'] = $categories->firstWhere('id', $categoryId)?->name ?? 'Kategori';
@endphp
<div class="mb-4 flex items-center justify-between gap-3">
    <form method="GET" action="{{ route('ledger.index') }}" class="flex min-w-0 flex-1 gap-2">
        @foreach (['kind' => $kind, 'filter' => $filter, 'category_id' => $categoryId] as $name => $value)
            @if ($value && $value !== 'all')<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
        @endforeach
        <label for="ledger-search" class="sr-only">Ara</label>
        <input type="search" id="ledger-search" name="search" value="{{ $search }}" maxlength="200" placeholder="Açıklama, hesap adı, referans veya belge no" class="min-w-0 w-full flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
        <button type="submit" class="h-9 rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Ara</button>
        @if ($search !== '')
            <a href="{{ route('ledger.index', request()->except(['search', 'page'])) }}" aria-label="Aramayı temizle" class="rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-500">✕</a>
        @endif
    </form>
    <button type="button" id="ledger-filter-open" aria-haspopup="dialog" aria-controls="ledger-filter-dialog" aria-label="Filtrele" class="flex h-9 shrink-0 items-center gap-2 rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.238 2.022l-3.158 1.579A2.25 2.25 0 018.25 20.05v-5.83a2.25 2.25 0 00-.659-1.591L2.659 7.197A2.25 2.25 0 012 5.606V4.562c0-.54.384-1.006.917-1.096A49.32 49.32 0 0112 3z"/></svg>
        <span class="hidden md:inline">Filtrele</span>
        @if (count($activeFilters))<span class="flex h-5 w-5 items-center justify-center rounded-full bg-white text-xs text-slate-950">{{ count($activeFilters) }}</span>@endif
    </button>
</div>
@error('search')<p class="mb-3 text-xs text-red-600">{{ $message }}</p>@enderror
@error('category_id')<p class="mb-3 text-xs text-red-600">{{ $message }}</p>@enderror
@if ($activeFilters)
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium text-slate-500">Aktif filtreler:</span>
        @foreach ($activeFilters as $name => $label)
            <a href="{{ route('ledger.index', request()->except([$name, 'page'])) }}" class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">{{ $label }} <span class="text-slate-500">×</span></a>
        @endforeach
        <a href="{{ route('ledger.index', request()->only('search')) }}" class="ml-1 text-xs font-medium text-slate-400 underline hover:text-slate-600">Tümünü temizle</a>
    </div>
@endif
<dialog id="ledger-filter-dialog" aria-labelledby="ledger-filter-title" class="fixed inset-y-0 left-auto right-0 m-0 h-dvh max-h-dvh w-full max-w-md border-0 bg-white p-6 text-slate-900 shadow-2xl backdrop:bg-black/50">
    <form method="GET" action="{{ route('ledger.index') }}" class="flex min-h-full flex-col">
        <div class="mb-6 flex items-center justify-between">
            <h2 id="ledger-filter-title" class="text-lg font-bold">Filtrele</h2>
            <button type="button" data-ledger-filter-close aria-label="Kapat" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">✕</button>
        </div>
        @if ($search !== '')<input type="hidden" name="search" value="{{ $search }}">@endif
        <div class="flex-1 space-y-4">
            <div>
                <label for="ledger-kind" class="mb-1.5 block text-sm font-medium text-slate-700">İşlem Türü</label>
                <select name="kind" id="ledger-kind" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-base sm:py-2 sm:text-sm">
                    @foreach ($kindLabels as $value => $label)<option value="{{ $value }}" @selected($kind === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="ledger-filter" class="mb-1.5 block text-sm font-medium text-slate-700">Tahsis Durumu</label>
                <select name="filter" id="ledger-filter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-base sm:py-2 sm:text-sm">
                    @foreach ($filterLabels as $value => $label)<option value="{{ $value }}" @selected($filter === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="ledger-category" class="mb-1.5 block text-sm font-medium text-slate-700">Kategori</label>
                <select name="category_id" id="ledger-category" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-base sm:py-2 sm:text-sm">
                    <option value="">Tümü</option>
                    @foreach ($categories as $category)<option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="mt-6 flex items-center gap-3 border-t border-slate-100 pt-4">
            <a href="{{ route('ledger.index', request()->only('search')) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Temizle</a>
            <button type="button" data-ledger-filter-close class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">İptal</button>
            <button type="submit" class="ml-auto rounded-xl bg-slate-950 px-6 py-2.5 text-sm font-semibold text-white">Uygula</button>
        </div>
    </form>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('ledger-filter-dialog');
        const form = dialog.querySelector('form');
        document.getElementById('ledger-filter-open').addEventListener('click', () => { form.reset(); dialog.showModal(); });
        dialog.querySelectorAll('[data-ledger-filter-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('click', event => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
        });
    })();
</script>
