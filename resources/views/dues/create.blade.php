@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Aidat / Borçlandırma Ekle</h1>
            <p class="mt-1 text-sm text-slate-500">Giderlerden, manuel toplamdan veya birebir borçlandırma ile tahakkuk oluşturun.</p>
        </div>
        <a href="{{ route('dues.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Aidatlara Dön</a>
    </div>

    <form method="POST" action="{{ route('dues.store') }}" class="rounded-2xl bg-white p-6 shadow-sm">
        @csrf

        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <label for="source_type" class="mb-2 block text-sm font-semibold text-slate-700">Borçlandırma Kaynağı</label>
                <select id="source_type" name="source_type" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="expenses" @selected(old('source_type') === 'expenses')>Dönem giderlerinden hesapla</option>
                    <option value="manual" @selected(old('source_type') === 'manual')>Manuel toplam tutar</option>
                    <option value="individual" @selected(old('source_type') === 'individual')>Birebir borçlandırma</option>
                </select>
                @error('source_type')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
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
                <label for="period" class="mb-2 block text-sm font-semibold text-slate-700">Borç Dönemi</label>
                <input id="period" name="period" type="month" value="{{ old('period', now()->format('Y-m')) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('period')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="due_date" class="mb-2 block text-sm font-semibold text-slate-700">Son Ödeme Tarihi</label>
                <input id="due_date" name="due_date" type="date" value="{{ old('due_date', now()->endOfMonth()->toDateString()) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('due_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mt-6 grid gap-5 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 p-5">
                <h2 class="text-base font-semibold text-slate-950">Dönem Giderlerinden Hesapla</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label for="source_period" class="mb-2 block text-sm font-semibold text-slate-700">Gider Dönemi</label>
                        <input id="source_period" name="source_period" type="month" value="{{ old('source_period', now()->format('Y-m')) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        @error('source_period')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="category_filter_ids" class="mb-2 block text-sm font-semibold text-slate-700">Gider Kategori Filtresi</label>
                        <select id="category_filter_ids" name="category_filter_ids[]" multiple class="h-40 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                            @foreach ($expenseCategories as $category)
                                <option value="{{ $category->id }}" @selected(in_array((string) $category->id, old('category_filter_ids', []), true))>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-slate-500">Boş bırakırsanız seçilen dönemdeki tüm giderler dağıtılır. Birden fazla kategori seçebilirsiniz.</p>
                        @error('category_filter_ids')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 p-5">
                <h2 class="text-base font-semibold text-slate-950">Manuel veya Birebir</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label for="source_amount" class="mb-2 block text-sm font-semibold text-slate-700">Manuel Toplam Tutar</label>
                        <input id="source_amount" name="source_amount" type="number" min="0.01" step="0.01" value="{{ old('source_amount') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        @error('source_amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="account_id" class="mb-2 block text-sm font-semibold text-slate-700">Birebir Borçlandırılacak Hesap</label>
                        <select id="account_id" name="account_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                            <option value="">Hesap seçin</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>{{ $account->name }} ({{ $account->type_label }})</option>
                            @endforeach
                        </select>
                        @error('account_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="individual_amount" class="mb-2 block text-sm font-semibold text-slate-700">Birebir Tutar</label>
                        <input id="individual_amount" name="individual_amount" type="number" min="0.01" step="0.01" value="{{ old('individual_amount') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        @error('individual_amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-5 lg:grid-cols-2">
            <div>
                <label for="distribution_type" class="mb-2 block text-sm font-semibold text-slate-700">Dağıtım Yöntemi</label>
                <select id="distribution_type" name="distribution_type" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="equal" @selected(old('distribution_type') === 'equal')>Eşit böl</option>
                    <option value="individual" @selected(old('distribution_type') === 'individual')>Birebir</option>
                </select>
                @error('distribution_type')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="description" class="mb-2 block text-sm font-semibold text-slate-700">Açıklama</label>
                <input id="description" name="description" value="{{ old('description') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none" placeholder="Örn. Nisan demirbaş giderleri">
                @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <button type="submit" class="mt-6 rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Borçlandırmayı Oluştur</button>
    </form>
@endsection
