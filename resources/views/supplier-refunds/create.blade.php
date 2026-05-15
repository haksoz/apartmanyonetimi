@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Tedarikçi İadesi Al</h1>
            <p class="mt-1 text-sm text-slate-500">Tedarikçiden fazla ödeme iadesi kaydedin.</p>
        </div>
        <a href="{{ route('accounts.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Hesaplara Dön</a>
    </div>

    <form method="POST" action="{{ route('supplier-refunds.store') }}" class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm">
        @csrf

        <div class="space-y-5">
            <div>
                <label for="account_id" class="mb-2 block text-sm font-semibold text-slate-700">Tedarikçi</label>
                <select id="account_id" name="account_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="">Tedarikçi seçin</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('account_id', $selectedAccountId ?? '') === (string) $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
                @error('account_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="amount" class="mb-2 block text-sm font-semibold text-slate-700">İade Tutarı</label>
                    <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="transaction_date" class="mb-2 block text-sm font-semibold text-slate-700">İade Tarihi</label>
                    <input id="transaction_date" name="transaction_date" type="date" value="{{ old('transaction_date', now()->toDateString()) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('transaction_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>

            <div>
                <label for="cash_box_id" class="mb-2 block text-sm font-semibold text-slate-700">Kasa</label>
                <select id="cash_box_id" name="cash_box_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="">Kasa seçin</option>
                    @foreach ($cashBoxes as $cashBox)
                        <option value="{{ $cashBox->id }}" @selected((string) old('cash_box_id') === (string) $cashBox->id)>{{ $cashBox->name }}</option>
                    @endforeach
                </select>
                @error('cash_box_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="description" class="mb-2 block text-sm font-semibold text-slate-700">Açıklama</label>
                <input id="description" name="description" value="{{ old('description') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none" placeholder="Örn. Asansör fazla ödeme iadesi">
                @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <button type="submit" class="mt-6 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-700">İadeyi Kaydet</button>
    </form>
@endsection
