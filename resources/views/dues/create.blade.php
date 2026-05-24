@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Borçlandır</h1>
            <p class="mt-1 text-sm text-slate-500">Tek hesaba birebir borçlandırma oluşturun.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('dues.index') }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Aidatlara Dön</a>
        </div>
    </div>

    <form method="POST" action="{{ route('dues.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="source_type" value="individual">
        <input type="hidden" name="distribution_type" value="individual">

        {{-- Main Fields --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Hesap &amp; Borç Bilgisi</h3>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="account_id" class="mb-2 block text-sm font-medium text-slate-600">Borçlandırılacak Hesap</label>
                    <select id="account_id" name="account_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        <option value="">Hesap seçin</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('account_id', $selectedAccountId ?? '') === (string) $account->id)>
                                {{ $account->unit ? 'No:'.str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT).' ' : '' }}{{ $account->name }} ({{ $account->type_label }}){{ !$account->is_active ? ' - Pasif' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('account_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="category_id" class="mb-2 block text-sm font-medium text-slate-600">Borç Kategorisi</label>
                    <select id="category_id" name="category_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        <option value="">Kategori seçin</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="individual_amount" class="mb-2 block text-sm font-medium text-slate-600">Borç Tutarı</label>
                    <input id="individual_amount" name="individual_amount" type="number" min="0.01" step="0.01" value="{{ old('individual_amount') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('individual_amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="period" class="mb-2 block text-sm font-medium text-slate-600">Borç Dönemi</label>
                    <input id="period" name="period" type="month" value="{{ old('period', now()->format('Y-m')) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('period')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="mb-2 block text-sm font-medium text-slate-600">Açıklama</label>
                    <input id="description" name="description" value="{{ old('description') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none" placeholder="Örn. Hasar tazminatı, ceza vb.">
                    @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Date Fields --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Tarih Bilgileri</h3>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="created_at_manual" class="mb-2 block text-sm font-medium text-slate-600">Oluşturulma Tarihi</label>
                    <input id="created_at_manual" name="created_at_manual" type="date" value="{{ old('created_at_manual', now()->toDateString()) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('created_at_manual')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="due_date" class="mb-2 block text-sm font-medium text-slate-600">Son Ödeme Tarihi</label>
                    <input id="due_date" name="due_date" type="date" value="{{ old('due_date', now()->endOfMonth()->toDateString()) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('due_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="w-full md:w-auto rounded-xl bg-slate-950 px-8 py-3 text-sm font-semibold text-white hover:bg-slate-800">Borçlandır</button>
        </div>
    </form>
@endsection
