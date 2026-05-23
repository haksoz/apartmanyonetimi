@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Yeni Apartman</h1>
        <p class="mt-1 text-sm text-slate-500">Daire sayısına göre daire ve hesap kayıtları otomatik oluşturulur.</p>
    </div>

    <form method="POST" action="{{ route('apartments.store') }}" class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        <div class="space-y-5">
            <div>
                <label class="text-sm font-medium text-slate-700">Apartman Adı</label>
                <input name="name" value="{{ old('name') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" required>
                @error('name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Adres</label>
                <textarea name="address" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" rows="3">{{ old('address') }}</textarea>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-slate-700">Daire Sayısı</label>
                    <input type="number" name="unit_count" value="{{ old('unit_count', 12) }}" min="1" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" required>
                    @error('unit_count') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Yönetici Daire No</label>
                    <input type="number" name="manager_unit_no" value="{{ old('manager_unit_no') }}" min="1" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                </div>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Daire Hesaplarının Açılış Tarihi</label>
                <input type="date" name="account_opening_date" value="{{ old('account_opening_date', now()->toDateString()) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" required>
                @error('account_opening_date') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route('apartments.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Vazgeç</a>
            <button class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Oluştur</button>
        </div>
    </form>
@endsection
