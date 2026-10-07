@extends('layouts.app')

@section('title', 'Ücretli Kullanım')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('subscriber.dashboard') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Abone Paneline Dön</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Yeni Sipariş</h1>
            <p class="mt-1 text-sm text-slate-500">Bir sipariş tek apartman içindir. Her apartmanın ödemesi ayrı açılır.</p>
        </div>

        @if ($apartments->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">
                Ücretli kullanım için önce bir apartman oluşturun.
            </div>
        @else
            <div class="space-y-3">
                @foreach ($apartments as $apartment)
                    @continue($apartment->offer['action'] !== 'pending' || ! $apartment->offer['order'])
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold text-slate-900">{{ $apartment->name }}</span>
                            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Ödeme bekliyor</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $apartment->unit_count }} daire</p>
                        @if ($apartment->offer['record'])
                            <p class="mt-1 font-mono text-sm text-slate-700">{{ $apartment->offer['record']->subscription_no }}</p>
                        @endif
                        <p class="mt-3 text-sm font-semibold text-amber-800">Ödeme Bekleyen Siparişiniz Var</p>
                        <p class="mt-1 font-mono text-sm text-slate-900">{{ $apartment->offer['order']->order_number ?: '—' }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="{{ route('subscriber.subscriptions.receipt', $apartment->offer['order']) }}" class="rounded-xl bg-amber-500 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-600">Ödemeye Devam Et</a>
                            @unless ($apartment->offer['order']->hasPaymentProof())
                                <form method="POST" action="{{ route('subscriber.subscriptions.cancel', $apartment->offer['order']) }}">
                                    @csrf
                                    <button class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Siparişi İptal Et</button>
                                </form>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>

            @foreach ($apartments as $apartment)
                @continue((int) $apartment->unit_count <= 100 || $apartment->offer['action'] === 'pending')
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold text-slate-900">{{ $apartment->name }}</p>
                    <p class="mt-1">101 ve üzeri daireli apartmanlar için özel fiyatlandırma uygulanmaktadır. Talebiniz alınmıştır. Temsilcimiz sizinle iletişime geçerek size özel teklifinizi paylaşacaktır.</p>
                </div>
            @endforeach

            @if ($apartments->contains(fn ($apartment) => $apartment->offer['action'] !== 'pending' && $apartment->offer['record'] && (int) $apartment->unit_count <= 100 && ! ($apartment->monthly_quote['requires_quote'] ?? false)))
            <form method="POST" action="{{ route('subscriber.subscriptions.store') }}" enctype="multipart/form-data" class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">
                @csrf
                <div class="space-y-3">
                    @foreach ($apartments as $apartment)
                        @continue($apartment->offer['action'] === 'pending' || (int) $apartment->unit_count > 100)
                        @php
                            $monthly = $apartment->monthly_quote;
                            $yearly = $apartment->yearly_quote;
                            $offer = $apartment->offer;
                            $quoteScale = (int) $apartment->unit_count > 100;
                            $actionLabel = $quoteScale ? 'Özel Teklif' : match ($offer['action']) {
                                'renew' => 'Yenile',
                                'restart' => 'Yeniden Başlat',
                                'pending' => 'Ödeme bekliyor',
                                default => 'Ücretli Pakete Geç',
                            };
                            $selectable = (bool) $offer['record'] && ! $quoteScale && ! ($monthly['requires_quote'] ?? false);
                        @endphp
                        <div class="rounded-xl border border-slate-200 p-4">
                            <label class="flex items-start gap-3">
                                @if ($selectable)
                                    <input type="radio" name="apartment_ids[]" value="{{ $apartment->id }}" class="mt-1" @checked((int) old('apartment_ids.0') === $apartment->id)>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="font-semibold text-slate-900">{{ $apartment->name }}</span>
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $actionLabel }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">{{ $apartment->unit_count }} daire</p>
                                    @if ($offer['record'])
                                        <p class="mt-1 font-mono text-sm text-slate-700">{{ $offer['record']->subscription_no }}</p>
                                    @endif
                                    @if ($offer['period'])
                                        <p class="mt-1 text-sm text-slate-700">
                                            {{ $offer['period']->started_at?->format('d.m.Y') ?? '—' }}
                                            –
                                            {{ $offer['period']->expires_at?->format('d.m.Y') ?? 'süresiz' }}
                                        </p>
                                    @elseif ($offer['action'] === 'upgrade')
                                        <p class="mt-1 text-sm text-slate-600">Temel kullanım</p>
                                    @endif
                                    @if ($quoteScale || $monthly['requires_quote'])
                                        <p class="mt-1 text-sm text-slate-700">101 ve üzeri daire için özel teklif gerekir. Sipariş bu ekrandan açılamaz.</p>
                                    @elseif ($selectable)
                                        <p class="mt-1 text-sm text-slate-700">
                                            Aylık {{ number_format($monthly['amount'], 0, ',', '.') }} ₺
                                            · Yıllık {{ number_format($yearly['amount'], 0, ',', '.') }} ₺
                                        </p>
                                    @endif
                                </div>
                            </label>
                        </div>
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

                <div id="transfer-proof" class="mt-4 space-y-4">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Dekont referansı</label>
                        <input name="reference_code" value="{{ old('reference_code') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Dekont</label>
                        <input type="file" name="receipt" class="mt-1 w-full text-sm">
                    </div>
                </div>
                <script>
                    (function () {
                        const proof = document.getElementById('transfer-proof');
                        const sync = function () {
                            const selected = document.querySelector('input[name="payment_method"]:checked');
                            const card = selected && selected.value === 'kredi_kartı';
                            proof.hidden = card;
                            proof.querySelectorAll('input').forEach(function (input) {
                                input.disabled = card;
                            });
                        };
                        document.querySelectorAll('input[name="payment_method"]').forEach(function (input) {
                            input.addEventListener('change', sync);
                        });
                        sync();
                    })();
                </script>

                <div class="mt-6 space-y-4">
                    @include('partials.accept-sales')
                    <div class="flex justify-end">
                        <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Sipariş Oluştur</button>
                    </div>
                </div>
            </form>
            @endif
        @endif
    </div>
@endsection
