@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Kategoriler</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }} için gelir, gider ve paylaştırma kategorilerini yönetin.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('categories.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Kategori Ekle</a>
        </div>
    </div>

    @if ($categories->count() > 1)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500">Satırları sürükleyerek veya oklarla sıralayın. Sıralama otomatik kaydedilir ve kategori seçimlerinde de kullanılır.</p>
            <form method="POST" action="{{ route('categories.reorder') }}" id="category-order-form">
                @csrf
                <div id="category-order-inputs"></div>
                <button type="submit" hidden class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Sıralamayı Kaydet</button>
            </form>
        </div>
        <p id="category-order-status" aria-live="polite" class="mb-3 text-sm text-slate-500"></p>
        @error('category_ids')<p class="mb-3 text-sm text-red-600">{{ $message }}</p>@enderror
        @error('category_ids.*')<p class="mb-3 text-sm text-red-600">{{ $message }}</p>@enderror
    @endif

    {{-- Desktop Table --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="w-24 px-3 py-3">Sıra</th><th class="px-5 py-3">Kategori</th><th class="px-5 py-3">Kullanım Yetkileri</th><th class="px-5 py-3">Durum</th><th class="px-5 py-3 text-right">İşlemler</th></tr></thead>
            <tbody data-category-list class="divide-y divide-slate-100">
                @forelse ($categories as $category)
                    @php($locked = $category->isDemirbasCategory() || $category->isCommonExpenseCategory() || (int) $apartment->default_aidat_category_id === (int) $category->id)
                    <tr draggable="true" data-category-id="{{ $category->id }}">
                        <td class="px-3 py-2">@include('categories.order-controls')</td>
                        <td class="px-5 py-4 font-semibold text-slate-900">{{ $category->name }}</td>
                        <td class="px-5 py-4">{{ implode(', ', $category->usage_labels) ?: 'Yetki yok' }}</td>
                        <td class="px-5 py-4">{{ $category->is_active ? 'Aktif' : 'Pasif' }}</td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('categories.edit', $category) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
                                @unless($locked)<form method="POST" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('Kategori silinsin mi?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Sil</button>
                                </form>@endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">Henüz kategori yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Cards --}}
    <div data-category-list class="md:hidden space-y-3">
        @forelse ($categories as $category)
            @php($locked = $category->isDemirbasCategory() || $category->isCommonExpenseCategory() || (int) $apartment->default_aidat_category_id === (int) $category->id)
            <div draggable="true" data-category-id="{{ $category->id }}" class="rounded-xl bg-white p-4 shadow-sm border border-slate-200">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">@include('categories.order-controls')<span class="font-semibold text-slate-900">{{ $category->name }}</span></div>
                    <span class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold {{ $category->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                        {{ $category->is_active ? 'Aktif' : 'Pasif' }}
                    </span>
                </div>
                <div class="mt-1 text-xs text-slate-500">{{ implode(', ', $category->usage_labels) ?: 'Yetki yok' }}</div>
                <div class="mt-3 flex flex-wrap justify-end gap-2">
                    <a href="{{ route('categories.edit', $category) }}" class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200">Düzenle</a>
                    @unless($locked)<form method="POST" action="{{ route('categories.destroy', $category) }}" class="inline" onsubmit="return confirm('Kategori silinsin mi?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">Sil</button>
                    </form>@endunless
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm">
                Henüz kategori yok.
            </div>
        @endforelse
    </div>
    @if ($categories->count() > 1)
        <script>
            (() => {
                const form = document.getElementById('category-order-form');
                const lists = Array.from(document.querySelectorAll('[data-category-list]'));
                const status = document.getElementById('category-order-status');
                const save = form.querySelector('[type="submit"]');
                const rows = list => Array.from(list.querySelectorAll(':scope > [data-category-id]'));
                let savedOrder = rows(lists[0]).map(row => row.dataset.categoryId).join(',');
                let saveTimer;
                let saving = false;
                let dragged = null;
                const refresh = (source, scheduleSave = false) => {
                    const ids = rows(source).map(row => row.dataset.categoryId);
                    lists.forEach(list => {
                        if (list !== source) {
                            const items = new Map(rows(list).map(row => [row.dataset.categoryId, row]));
                            ids.forEach(id => list.append(items.get(id)));
                        }
                        rows(list).forEach((row, index, items) => {
                            row.querySelector('[data-move="up"]').disabled = index === 0;
                            row.querySelector('[data-move="down"]').disabled = index === items.length - 1;
                        });
                    });
                    const changed = ids.join(',') !== savedOrder;
                    save.hidden = !changed;
                    if (!saving) status.textContent = changed ? 'Sıralama kaydedilecek…' : '';
                    document.getElementById('category-order-inputs').replaceChildren(...ids.map(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden'; input.name = 'category_ids[]'; input.value = id;
                        return input;
                    }));
                    if (scheduleSave && changed) {
                        clearTimeout(saveTimer);
                        saveTimer = setTimeout(saveOrder, 400);
                    }
                };
                const saveOrder = async () => {
                    clearTimeout(saveTimer);
                    if (saving) return;
                    const order = rows(lists[0]).map(row => row.dataset.categoryId).join(',');
                    if (order === savedOrder) return;
                    const payload = new FormData(form);
                    saving = true;
                    save.disabled = true;
                    status.textContent = 'Sıralama kaydediliyor…';
                    let success = false;
                    try {
                        const response = await fetch(form.action, {
                            method: 'POST', body: payload, credentials: 'same-origin',
                            headers: { Accept: 'application/json' },
                        });
                        if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) {
                            throw new Error('save failed');
                        }
                        const result = await response.json();
                        if (result.saved !== true) throw new Error('save failed');
                        savedOrder = order;
                        success = true;
                    } catch {
                        status.textContent = 'Sıralama kaydedilemedi. Sıralamayı Kaydet düğmesiyle tekrar deneyin.';
                    } finally {
                        saving = false;
                        save.disabled = false;
                        const pending = rows(lists[0]).map(row => row.dataset.categoryId).join(',') !== savedOrder;
                        save.hidden = !pending;
                        if (success) {
                            status.textContent = pending ? 'Sıralama kaydediliyor…' : 'Sıralama kaydedildi.';
                            if (pending) saveTimer = setTimeout(saveOrder, 0);
                        }
                    }
                };
                lists.forEach(list => {
                    list.addEventListener('click', event => {
                        const button = event.target.closest('[data-move]');
                        if (!button) return;
                        const row = button.closest('[data-category-id]');
                        const neighbour = button.dataset.move === 'up' ? row.previousElementSibling : row.nextElementSibling;
                        if (!neighbour) return;
                        if (button.dataset.move === 'up') list.insertBefore(row, neighbour);
                        else list.insertBefore(neighbour, row);
                        refresh(list, true);
                        (button.disabled ? row.querySelector('[data-move]:not(:disabled)') : button)?.focus();
                    });
                    list.addEventListener('dragstart', event => {
                        if (event.target.closest('a, button, input, form')) { event.preventDefault(); return; }
                        dragged = event.target.closest('[data-category-id]');
                        if (!dragged) return;
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', dragged.dataset.categoryId);
                        dragged.classList.add('opacity-50');
                    });
                    list.addEventListener('dragover', event => {
                        if (!dragged || dragged.parentElement !== list) return;
                        event.preventDefault();
                        const target = event.target.closest('[data-category-id]');
                        if (!target || target === dragged) return;
                        const bounds = target.getBoundingClientRect();
                        list.insertBefore(dragged, event.clientY < bounds.top + bounds.height / 2 ? target : target.nextElementSibling);
                        refresh(list, true);
                    });
                    list.addEventListener('drop', event => event.preventDefault());
                    list.addEventListener('dragend', () => {
                        dragged?.classList.remove('opacity-50'); dragged = null;
                    });
                });
                form.addEventListener('submit', event => {
                    event.preventDefault();
                    saveOrder();
                });
                window.addEventListener('pageshow', () => {
                    if (!saving) { save.disabled = false; refresh(lists[0]); }
                });
                window.addEventListener('beforeunload', event => {
                    if (rows(lists[0]).map(row => row.dataset.categoryId).join(',') !== savedOrder) {
                        event.preventDefault(); event.returnValue = '';
                    }
                });
                refresh(lists[0]);
            })();
        </script>
    @endif
@endsection
