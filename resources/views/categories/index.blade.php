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

    {{-- Desktop Table --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="px-5 py-3">Kategori</th><th class="px-5 py-3">Tip</th><th class="px-5 py-3">Durum</th><th class="px-5 py-3 text-right">İşlemler</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($categories as $category)
                    <tr>
                        <td class="px-5 py-4 font-semibold text-slate-900">{{ $category->name }}</td>
                        <td class="px-5 py-4">{{ $category->type_label }}</td>
                        <td class="px-5 py-4">{{ $category->is_active ? 'Aktif' : 'Pasif' }}</td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">
                                @if (! $category->is_system)
                                    <a href="{{ route('categories.edit', $category) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
                                    <form method="POST" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('Kategori silinsin mi?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Sil</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">Sistem kategorisi</span>
                                @endif
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
            <div class="rounded-xl bg-white p-4 shadow-sm border border-slate-200">
                <div class="flex items-start justify-between gap-3">
                    <div class="font-semibold text-slate-900">{{ $category->name }}</div>
                    <span class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold {{ $category->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                        {{ $category->is_active ? 'Aktif' : 'Pasif' }}
                    </span>
                </div>
                <div class="mt-1 text-xs text-slate-500">{{ $category->type_label }}</div>
                <div class="mt-3 flex flex-wrap justify-end gap-2">
                    @if (! $category->is_system)
                        <a href="{{ route('categories.edit', $category) }}" class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200">Düzenle</a>
                        <form method="POST" action="{{ route('categories.destroy', $category) }}" class="inline" onsubmit="return confirm('Kategori silinsin mi?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">Sil</button>
                        </form>
                    @else
                        <span class="text-xs text-slate-400">Sistem kategorisi</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm">
                Henüz kategori yok.
            </div>
        @endforelse
    </div>
@endsection
