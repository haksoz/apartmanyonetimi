@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Gider Düzenle</h1>
            <p class="mt-1 text-sm text-slate-500">Gider kaydının kategori, tedarikçi, tutar ve ödeme durumunu düzenleyin.</p>
        </div>
        <a href="{{ route('expenses.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Giderlere Dön</a>
    </div>

    <form method="POST" action="{{ route('expenses.update', $expense) }}" class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        <div class="space-y-5">
            <div>
                <label for="category_id" class="mb-2 block text-sm font-semibold text-slate-700">Kategori</label>
                <select id="category_id" name="category_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="">Kategori seçin</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $expense->category_id) === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="account_id" class="mb-2 block text-sm font-semibold text-slate-700">Hesap / Tedarikçi</label>
                <select id="account_id" name="account_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="">Hesap seçmeden kaydet</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('account_id', $expense->account_id) === (string) $account->id)>{{ $account->name }} ({{ $account->type_label }})</option>
                    @endforeach
                </select>
                @error('account_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="amount" class="mb-2 block text-sm font-semibold text-slate-700">Tutar</label>
                    <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $expense->amount) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="expense_date" class="mb-2 block text-sm font-semibold text-slate-700">Gider Tarihi</label>
                    <input id="expense_date" name="expense_date" type="date" value="{{ old('expense_date', $expense->expense_date?->format('Y-m-d')) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('expense_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="period_month" class="mb-2 block text-sm font-semibold text-slate-700">Dönem</label>
                    <input id="period_month" name="period_month" type="month" value="{{ old('period_month', $expense->period_month?->format('Y-m')) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('period_month')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>

            <div>
                <label for="description" class="mb-2 block text-sm font-semibold text-slate-700">Açıklama</label>
                <input id="description" name="description" value="{{ old('description', $expense->description) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
                <input type="checkbox" name="is_paid" value="1" @checked(old('is_paid', $expense->is_paid)) class="rounded border-slate-300">
                Bu gider ödendi
            </label>

            <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Gideri Güncelle</button>
        </div>
    </form>
@endsection
