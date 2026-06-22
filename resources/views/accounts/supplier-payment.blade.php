@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('accounts.index') }}" class="hover:text-slate-600">Hesaplar</a>
                <span>/</span>
                <a href="{{ route('accounts.show', $account) }}" class="hover:text-slate-600">{{ $account->name }}</a>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Tedarikçiye Ödeme Yap</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $account->name }}</p>
        </div>
        <a href="{{ route('accounts.show', $account) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri Dön</a>
    </div>

    <form method="POST" action="{{ route('accounts.supplier-payment.store', $account) }}" class="rounded-2xl bg-white p-6 shadow-sm">
        @csrf

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Tutar <span class="text-red-500">*</span></label>
                <input type="number" name="amount" step="0.01" min="0.01" required value="{{ old('amount') }}" placeholder="0,00"
                    class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-950 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Kasa <span class="text-red-500">*</span></label>
                <select name="cash_box_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="">Seçin...</option>
                    @foreach ($cashBoxes as $box)
                        <option value="{{ $box->id }}" {{ old('cash_box_id') == $box->id ? 'selected' : '' }}>{{ $box->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Ödeme Tarihi <span class="text-red-500">*</span></label>
                <input type="date" name="payment_date" required value="{{ old('payment_date', date('Y-m-d')) }}"
                    class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-950 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Açıklama</label>
                <input type="text" name="description" value="{{ old('description') }}" placeholder="İsteğe bağlı"
                    class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-950 focus:outline-none">
            </div>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                Ödemeyi Kaydet
            </button>
            <a href="{{ route('accounts.show', $account) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</a>
        </div>

        <p class="mt-4 text-xs text-slate-400">Ödeme kaydedildikten sonra giderlere bağlanabilir.</p>
    </form>
@endsection
