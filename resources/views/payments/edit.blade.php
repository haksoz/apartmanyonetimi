@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Ödemeyi Düzenle</h1>
            <p class="mt-1 text-sm text-slate-500">Ödeme kaydını güncelleyin. Tahsisler korunur.</p>
        </div>
        <a href="{{ route('payments.show', $payment) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">İptal</a>
    </div>

    <form method="POST" action="{{ route('payments.update', $payment) }}" class="rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        <div class="grid gap-5 lg:grid-cols-2">
            <div class="lg:col-span-2">
                <label class="mb-2 block text-sm font-semibold text-slate-700">Cari/Hesap</label>
                <div class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    {{ $payment->account->name }}
                </div>
            </div>

            <div>
                <label for="amount" class="mb-2 block text-sm font-semibold text-slate-700">Tutar</label>
                <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $payment->amount) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                @if ($payment->allocated_amount > 0)
                    <div class="mt-1 text-xs text-slate-500">Tahsis edilen: {{ number_format($payment->allocated_amount, 2, ',', '.') }} TL</div>
                @endif
            </div>

            <div>
                <label for="payment_date" class="mb-2 block text-sm font-semibold text-slate-700">Ödeme Tarihi</label>
                <input id="payment_date" name="payment_date" type="date" value="{{ old('payment_date', $payment->payment_date?->toDateString()) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('payment_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="cash_box_id" class="mb-2 block text-sm font-semibold text-slate-700">Kasa</label>
                <select id="cash_box_id" name="cash_box_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="">Kasa seçin</option>
                    @foreach ($cashBoxes as $cashBox)
                        <option value="{{ $cashBox->id }}" @selected(old('cash_box_id', $selectedCashBoxId) == $cashBox->id)>{{ $cashBox->name }}</option>
                    @endforeach
                </select>
                @error('cash_box_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="description" class="mb-2 block text-sm font-semibold text-slate-700">Açıklama</label>
                <input id="description" name="description" type="text" value="{{ old('description', $payment->description) }}" placeholder="Opsiyonel açıklama" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                Kaydet
            </button>
            <a href="{{ route('payments.show', $payment) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                İptal
            </a>
        </div>
    </form>
@endsection
