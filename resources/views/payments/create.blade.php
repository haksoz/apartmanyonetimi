@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Ödeme Al / Tahsilat Ekle</h1>
            <p class="mt-1 text-sm text-slate-500">Yeni ödeme kaydı oluşturun. İsterseniz hemen borçlara tahsis edebilir ya da sonra yapabilirsiniz.</p>
        </div>
        <a href="{{ route('accounts.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Hesaplara Dön</a>
    </div>

    <form method="POST" action="{{ route('payments.store') }}" class="rounded-2xl bg-white p-6 shadow-sm">
        @csrf

        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <label for="account_id" class="mb-2 block text-sm font-semibold text-slate-700">Cari/Hesap</label>
                <select id="account_id" name="{{ $selectedAccountId ? null : 'account_id' }}" required
                    {{ $selectedAccountId ? 'disabled' : '' }}
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none {{ $selectedAccountId ? 'bg-slate-100 cursor-not-allowed' : '' }}">
                    <option value="">Cari seçin</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected(old('account_id', $selectedAccountId) == $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
                @if ($selectedAccountId)
                    <input type="hidden" name="account_id" value="{{ $selectedAccountId }}">
                @endif
                @error('account_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="amount" class="mb-2 block text-sm font-semibold text-slate-700">Tutar</label>
                <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="payment_date" class="mb-2 block text-sm font-semibold text-slate-700">Ödeme Tarihi</label>
                <input id="payment_date" name="payment_date" type="date" value="{{ old('payment_date', now()->toDateString()) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('payment_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="cash_box_id" class="mb-2 block text-sm font-semibold text-slate-700">Kasa</label>
                <select id="cash_box_id" name="cash_box_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="">Kasa seçin</option>
                    @foreach ($cashBoxes as $cashBox)
                        <option value="{{ $cashBox->id }}" @selected(old('cash_box_id') == $cashBox->id)>{{ $cashBox->name }}</option>
                    @endforeach
                </select>
                @error('cash_box_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="description" class="mb-2 block text-sm font-semibold text-slate-700">Açıklama</label>
                <input id="description" name="description" type="text" value="{{ old('description') }}" placeholder="Opsiyonel açıklama (örn: kısmi ödeme, avans)" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" name="action" value="save" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                Tahsis Etmeden Kaydet
            </button>
            <button type="submit" name="action" value="allocate" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700">
                Kaydet ve Şimdi Tahsis Et
            </button>
        </div>
    </form>
@endsection
