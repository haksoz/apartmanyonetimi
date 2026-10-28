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
        <details class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" @if($errors->has('category_ids') || $errors->has('category_ids.*')) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-slate-800">Sıralamayı düzenle</summary>
            <p class="mt-3 text-sm text-slate-500">Kategorileri sürükleyerek veya yukarı/aşağı düğmeleriyle taşıyın. Kaydettiğiniz sıra kategori seçim alanlarında da kullanılır. Kaydedilen sıralamada yeni kategoriler listenin sonuna eklenir.</p>
            @error('category_ids')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            <form method="POST" action="{{ route('categories.reorder') }}" id="category-order-form" class="mt-4">
                @csrf
                <ol id="category-order-list" class="space-y-2">
                    @foreach ($categories as $category)
                        <li draggable="true" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2" data-category-id="{{ $category->id }}">
                            <input type="hidden" name="category_ids[]" value="{{ $category->id }}">
                            <span class="min-w-0 text-sm font-medium text-slate-800"><span aria-hidden="true" class="mr-2 text-slate-400">☰</span>{{ $category->name }}</span>
                            <div class="flex shrink-0 gap-2">
                                <button type="button" data-move="up" aria-label="{{ $category->name }} yukarı taşı" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-700 disabled:opacity-30">↑</button>
                                <button type="button" data-move="down" aria-label="{{ $category->name }} aşağı taşı" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-700 disabled:opacity-30">↓</button>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <p id="category-order-status" aria-live="polite" class="mt-3 text-sm text-slate-500"></p>
                <button type="submit" class="mt-3 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Sıralamayı Kaydet</button>
            </form>
        </details>
        <script>
            (() => {
                const form = document.getElementById('category-order-form');
                const list = document.getElementById('category-order-list');
                const status = document.getElementById('category-order-status');
                let dragged = null;
                const refresh = (changed = false) => {
                    Array.from(list.children).forEach((item, index, items) => {
                        item.querySelector('[data-move="up"]').disabled = index === 0;
                        item.querySelector('[data-move="down"]').disabled = index === items.length - 1;
                    });
                    if (changed) status.textContent = 'Sıralama değişti. Uygulamak için kaydedin.';
                };
                list.addEventListener('click', event => {
                    const button = event.target.closest('[data-move]');
                    if (!button) return;
                    const item = button.closest('li');
                    const neighbour = button.dataset.move === 'up' ? item.previousElementSibling : item.nextElementSibling;
                    if (!neighbour) return;
                    if (button.dataset.move === 'up') list.insertBefore(item, neighbour);
                    else list.insertBefore(neighbour, item);
                    refresh(true);
                    const focusButton = button.disabled ? item.querySelector('[data-move]:not(:disabled)') : button;
                    focusButton?.focus();
                });
                list.addEventListener('dragstart', event => {
                    dragged = event.target.closest('li');
                    if (!dragged) return;
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', dragged.dataset.categoryId);
                    dragged.classList.add('opacity-50');
                });
                list.addEventListener('dragover', event => {
                    if (!dragged) return;
                    event.preventDefault();
                    const target = event.target.closest('li');
                    if (!target || target === dragged) return;
                    const bounds = target.getBoundingClientRect();
                    list.insertBefore(dragged, event.clientY < bounds.top + bounds.height / 2 ? target : target.nextElementSibling);
                    refresh(true);
                });
                list.addEventListener('drop', event => event.preventDefault());
                list.addEventListener('dragend', () => {
                    dragged?.classList.remove('opacity-50');
                    dragged = null;
                });
                form.addEventListener('submit', event => {
                    if (form.dataset.submitting) { event.preventDefault(); return; }
                    form.dataset.submitting = '1';
                    form.querySelector('button[type="submit"]').disabled = true;
                    status.textContent = 'Sıralama kaydediliyor…';
                });
                window.addEventListener('pageshow', () => {
                    delete form.dataset.submitting;
                    form.querySelector('button[type="submit"]').disabled = false;
                    status.textContent = '';
                });
                refresh();
            })();
        </script>
    @endif

    {{-- Desktop Table --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="px-5 py-3">Kategori</th><th class="px-5 py-3">Kullanım Yetkileri</th><th class="px-5 py-3">Durum</th><th class="px-5 py-3 text-right">İşlemler</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($categories as $category)
                    @php($locked = (int) $apartment->default_aidat_category_id === (int) $category->id)
                    <tr>
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
                    <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">Henüz kategori yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Cards --}}
    <div class="md:hidden space-y-3">
        @forelse ($categories as $category)
            @php($locked = (int) $apartment->default_aidat_category_id === (int) $category->id)
            <div class="rounded-xl bg-white p-4 shadow-sm border border-slate-200">
                <div class="flex items-start justify-between gap-3">
                    <div class="font-semibold text-slate-900">{{ $category->name }}</div>
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
@endsection
