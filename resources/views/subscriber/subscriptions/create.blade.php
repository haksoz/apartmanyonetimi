@extends('layouts.app')

@section('title', 'Ücretli Kullanım')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('subscriber.dashboard') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Abone Paneline Dön</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Ücretli Kullanım</h1>
            <p class="mt-1 text-sm text-slate-500">Her apartman kendi daire sayısına göre fiyatlanır. Toplam, seçilen apartmanların tutarıdır.</p>
        </div>

        @if ($apartments->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">
                Ücretli kullanım için önce bir apartman oluşturun.
            </div>
        @else
            <form method="POST" action="{{ route('subscriber.subscriptions.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-6">
                @csrf
                <div class="space-y-3">
                    @foreach ($apartments as $apartment)
                        @php
                            $monthly = $apartment->monthly_quote;
                            $yearly = $apartment->yearly_quote;
                        @endphp
                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
                            <input type="checkbox" name="apartment_ids[]" value="{{ $apartment->id }}" class="mt-1" {{ in_array($apartment->id, old('apartment_ids', [])) ? 'checked' : '' }}>
                            <span class="flex-1">
                                <span class="block font-semibold text-slate-900">{{ $apartment->name }}</span>
                                <span class="block text-sm text-slate-500">{{ $apartment->unit_count }} daire · {{ $apartment->commercial_covered ? 'Ücretli' : 'Ücretsiz' }}</span>
                                <span class="mt-1 block text-sm text-slate-700">
                                    @if ($monthly['requires_quote'])
                                        Teklif gerekir
                                    @else
                                        Aylık {{ number_format($monthly['amount'], 0, ',', '.') }} ₺
                                        · Yıllık {{ number_format($yearly['amount'], 0, ',', '.') }} ₺
                                    @endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('apartment_ids') <div class="mt-2 text-sm text-red-600">{{ $message }}</div> @enderror

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-medium text-slate-700">
                        Dönem
                        <select name="period" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                            <option value="monthly" {{ old('period', 'monthly') === 'monthly' ? 'selected' : '' }}>Aylık</option>
                            <option value="yearly" {{ old('period') === 'yearly' ? 'selected' : '' }}>Yıllık</option>
                        </select>
                    </label>
                    <div class="text-sm font-medium text-slate-700">
                        Ödeme
                        <label class="mt-2 flex items-center gap-2 font-normal">
                            <input type="radio" name="payment_method" value="havale" {{ old('payment_method', 'havale') === 'havale' ? 'checked' : '' }}> Havale / EFT
                        </label>
                        <label class="mt-2 flex items-center gap-2 font-normal">
                            <input type="radio" name="payment_method" value="kredi_kartı" {{ old('payment_method') === 'kredi_kartı' ? 'checked' : '' }}> Kredi Kartı
                        </label>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="text-sm font-medium text-slate-700">Dekont referansı</label>
                    <input name="reference_code" value="{{ old('reference_code') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                </div>
                <div class="mt-4">
                    <label class="text-sm font-medium text-slate-700">Dekont</label>
                    <input type="file" name="receipt" class="mt-1 w-full text-sm">
                </div>

                <div class="mt-6 flex justify-end">
                    <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Sipariş Oluştur</button>
                </div>
            </form>
        @endif
    </div>
@endsection
