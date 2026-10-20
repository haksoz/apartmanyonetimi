@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Gider Planı Oluştur</h1>
        <p class="mt-1 text-sm text-slate-500">Tedarikçi zorunludur. Dönemi gelince ödenmemiş gider otomatik oluşur.</p>
    </div>

    <form method="POST" action="{{ route('expense-plans.store') }}" class="max-w-2xl space-y-5 rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-600">Plan adı</label>
            <input name="name" value="{{ old('name') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-600">Açıklama</label>
            <input name="description" value="{{ old('description') }}" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Gider kategorisi</label>
                <select name="category_id" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
                    <option value="">Seçin</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('category_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Tedarikçi</label>
                <select name="account_id" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
                    <option value="">Seçin</option>
                    @foreach ($accounts as $acc)
                        <option value="{{ $acc->id }}" @selected(old('account_id') == $acc->id)>{{ $acc->name }}</option>
                    @endforeach
                </select>
                @error('account_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Aylık tutar</label>
                <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Başlangıç</label>
                <input type="date" name="start_date" value="{{ old('start_date', now()->startOfMonth()->format('Y-m-d')) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Bitiş (opsiyonel)</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Oluşturma günü</label>
                <input type="number" min="1" max="28" name="generate_day" value="{{ old('generate_day', 1) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                    Aktif
                </label>
            </div>
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
            <a href="{{ route('financial-plans.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">İptal</a>
            <button class="rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white">Kaydet</button>
        </div>
    </form>
@endsection
