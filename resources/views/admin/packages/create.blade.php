@extends('layouts.app')

@section('title', 'Yeni Paket')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.packages.index') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Paketlere dön</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Yeni Paket</h1>
    </div>

    <div class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6">
        <form method="POST" action="{{ route('admin.packages.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700">Paket Adı</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                @error('name')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Slug</label>
                <input type="text" name="slug" value="{{ old('slug') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                @error('slug')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Açıklama</label>
                <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">{{ old('description') }}</textarea>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Apartman Limiti</label>
                    <input type="number" name="apartment_limit" value="{{ old('apartment_limit', 1) }}" min="0" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Sıra</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Aylık Fiyat</label>
                    <input type="number" step="0.01" name="monthly_price" value="{{ old('monthly_price', 0) }}" min="0" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Yıllık Fiyat</label>
                    <input type="number" step="0.01" name="yearly_price" value="{{ old('yearly_price', 0) }}" min="0" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} id="is_active" class="rounded border-slate-300">
                <label for="is_active" class="text-sm font-medium text-slate-700">Aktif</label>
            </div>

            <div class="pt-4">
                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Kaydet</button>
            </div>
        </form>
    </div>
@endsection
