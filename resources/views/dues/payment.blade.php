@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Ödeme Al / Tahsilat Ekle</h1>
            <p class="mt-1 text-sm text-slate-500">Borçlu aidat için ödeme kaydı oluşturun.</p>
        </div>
        <a href="{{ route('accounts.show', $due->account_id) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 text-center">Hesaba Dön</a>
    </div>

    <form method="POST" action="{{ route('dues.payment.store', $due) }}" class="rounded-2xl bg-white p-6 shadow-sm">
        @csrf

        @php
            $payAmount = $due->remaining_amount ?? $due->amount;
        @endphp

        <div class="grid gap-5 lg:grid-cols-2">
            <div class="lg:col-span-2 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                <div class="font-semibold text-slate-950">{{ $due->description ?? '-' }}</div>
                <div class="mt-1">Tutar: <span class="font-medium text-slate-900">{{ number_format($due->amount, 2, ',', '.') }} TL</span></div>
                <div class="mt-1">Hesap: <span class="font-medium text-slate-900">No:{{ $due->unit?->unit_no ?? '-' }} {{ $due->account?->name ?? '-' }}</span></div>
            </div>

            <div>
                <label for="cash_box_id" class="mb-2 block text-sm font-semibold text-slate-700">Ödeme Kasası</label>
                <select id="cash_box_id" name="cash_box_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="">Kasa seçin</option>
                    @foreach ($cashBoxes as $cashBox)
                        <option value="{{ $cashBox->id }}" @selected((string) old('cash_box_id') === (string) $cashBox->id)>{{ $cashBox->name }}</option>
                    @endforeach
                </select>
                @error('cash_box_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Ödenecek Tutar</label>
                <input type="hidden" name="amount" value="{{ $payAmount }}">
                <div class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-700 cursor-not-allowed">
                    {{ number_format($payAmount, 2, ',', '.') }} TL
                    <span class="text-xs text-slate-400 ml-1">(kalan tutarla sabit)</span>
                </div>
                @error('amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="payment_date" class="mb-2 block text-sm font-semibold text-slate-700">Ödeme Tarihi</label>
                <input id="payment_date" name="payment_date" type="date" value="{{ old('payment_date', now()->toDateString()) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('payment_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="description" class="mb-2 block text-sm font-semibold text-slate-700">Açıklama</label>
                <input id="description" name="description" type="text" value="{{ old('description', ($due->description ? $due->description . ' Tahsilatı' : 'Aidat Tahsilatı')) }}" placeholder="Opsiyonel açıklama" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="lg:col-span-2">
                <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Ödemeyi Kaydet</button>
            </div>
        </div>
    </form>
@endsection
