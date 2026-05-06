@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Kategoriler</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }} için gelir, gider ve paylaştırma kategorilerini yönetin.</p>
        </div>
        <a href="{{ route('categories.create') }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Kategori Ekle</a>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
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
                                <a href="{{ route('categories.edit', $category) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
                                <form method="POST" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('Kategori silinsin mi?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Sil</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">Henüz kategori yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
