@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Aidat Düzenle</h1>
            <p class="mt-1 text-sm text-slate-500">Mevcut aidat kaydındaki tutarı, vade tarihini veya durumu güncelleyin.</p>
        </div>
        <a href="{{ route('dues.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Aidatlara Dön</a>
    </div>

    <form method="POST" action="{{ route('dues.update', $due) }}" class="rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        @method('PATCH')

        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <label for="due_date" class="mb-2 block text-sm font-semibold text-slate-700">Son Ödeme Tarihi</label>
                <input id="due_date" name="due_date" type="date" value="{{ old('due_date', $due->due_date?->toDateString()) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('due_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="amount" class="mb-2 block text-sm font-semibold text-slate-700">Tutar</label>
                <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $due->amount) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="status" class="mb-2 block text-sm font-semibold text-slate-700">Durum</label>
                <select id="status" name="status" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="unpaid" @selected(old('status', $due->status) === 'unpaid')>Bekliyor</option>
                    <option value="paid" @selected(old('status', $due->status) === 'paid')>Ödendi</option>
                </select>
                @error('status')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="description" class="mb-2 block text-sm font-semibold text-slate-700">Açıklama</label>
                <input id="description" name="description" value="{{ old('description', $due->description) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none" placeholder="Açıklama girin">
                @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <button type="submit" class="mt-6 rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Kaydet</button>
    </form>
@endsection
