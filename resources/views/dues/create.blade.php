@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Borçlandır</h1>
            <p class="mt-1 text-sm text-slate-500">Tek hesaba birebir borçlandırma oluşturun.</p>
        </div>
        <a href="{{ route('dues.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Aidatlara Dön</a>
    </div>

    <form method="POST" action="{{ route('dues.store') }}" class="rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        <input type="hidden" name="source_type" value="individual">
        <input type="hidden" name="distribution_type" value="individual">

        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <label for="account_id" class="mb-2 block text-sm font-semibold text-slate-700">Borçlandırılacak Hesap</label>
                <select id="account_id" name="account_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="">Hesap seçin</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>{{ $account->name }} ({{ $account->type_label }})</option>
                    @endforeach
                </select>
                @error('account_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="category_id" class="mb-2 block text-sm font-semibold text-slate-700">Borç Kategorisi</label>
                <select id="category_id" name="category_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="">Kategori seçin</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="individual_amount" class="mb-2 block text-sm font-semibold text-slate-700">Borç Tutarı</label>
                <input id="individual_amount" name="individual_amount" type="number" min="0.01" step="0.01" value="{{ old('individual_amount') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('individual_amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="period" class="mb-2 block text-sm font-semibold text-slate-700">Borç Dönemi</label>
                <input id="period" name="period" type="month" value="{{ old('period', now()->format('Y-m')) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('period')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="due_date" class="mb-2 block text-sm font-semibold text-slate-700">Son Ödeme Tarihi</label>
                <input id="due_date" name="due_date" type="date" value="{{ old('due_date', now()->endOfMonth()->toDateString()) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('due_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="description" class="mb-2 block text-sm font-semibold text-slate-700">Açıklama</label>
                <input id="description" name="description" value="{{ old('description') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none" placeholder="Örn. Hasar tazminatı, ceza vb.">
                @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <button type="submit" class="mt-6 rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Borçlandır</button>
    </form>
@endsection
