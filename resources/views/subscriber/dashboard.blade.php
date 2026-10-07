@extends('layouts.app')

@section('title', 'Abone Paneli')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Abone Paneli</h1>
        <p class="mt-1 text-sm text-slate-500">Her apartmanın kullanımı ayrıdır. Temel yönetim ücretsizdir; ücretli özellikler apartman kartından açılır.</p>
    </div>

    @if ($apartments->count() === 0)
        <div id="no-apartment-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
                        <svg class="h-8 w-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900">Apartman Ekleyin</h3>
                    <p class="mt-2 text-sm text-slate-600">Sistemi kullanmaya başlamak için lütfen bir apartman oluşturun.</p>
                    <div class="mt-6 flex justify-center gap-3">
                        <a href="{{ route('subscriber.apartments.create') }}" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Apartman Oluştur</a>
                        <button onclick="document.getElementById('no-apartment-modal').remove()" class="rounded-xl border border-slate-300 px-6 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Daha Sonra</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">Henüz apartman yok.</div>
    @else
        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($apartments as $apartment)
                @php
                    $active = $apartment->commercial_active;
                    $pending = $apartment->commercial_pending;
                    $monthly = $apartment->monthly_quote;
                    $yearly = $apartment->yearly_quote;
                    $ownActive = $active && (int) $active->subscription->user_id === (int) auth()->id();
                    $ownPending = $pending && (int) $pending->subscription->user_id === (int) auth()->id();
                    $needsQuote = ($monthly['requires_quote'] ?? false) && ! $active;
                    $quoteScale = (int) $apartment->unit_count > 100;
                    $canPurchase = (bool) $apartment->can_purchase;
                    $renewSoon = $active && $active->subscription?->expires_at && $active->subscription->expires_at->lessThanOrEqualTo(now()->addDays(3));
                @endphp
                <article class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">{{ $apartment->name }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $apartment->unit_count }} daire</p>
                        </div>
                        @if ($active)
                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Ücretli</span>
                        @elseif ($pending)
                            <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Ödeme bekliyor</span>
                        @else
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Ücretsiz</span>
                        @endif
                    </div>

                    <div class="mt-4 flex-1 text-sm text-slate-600">
                        @if (! $canPurchase)
                            <p>{{ $active ? 'Ücretli kullanım açık.' : ($pending ? 'Ödeme bekleniyor.' : 'Temel kullanım açık.') }}</p>
                        @elseif ($active && ! $ownActive)
                            <p>Bu hizmet {{ $apartment->name }} için {{ $active->subscription->user?->name }} tarafından satın alınmıştır.</p>
                            <p class="mt-1">Bitiş {{ $active->subscription->expires_at?->format('d.m.Y') ?? 'süresiz' }}</p>
                            @if ($renewSoon)
                                <p class="mt-2 font-medium text-amber-700">Yenileme yaklaşıyor</p>
                            @endif
                        @elseif ($active && $active->subscription?->notes === 'Tanımlı süre')
                            <p>Tanımlı süre</p>
                            <p class="mt-1">Bitiş {{ $active->subscription->expires_at?->format('d.m.Y') ?? 'süresiz' }}</p>
                        @elseif ($active)
                            <p>{{ $active->subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }} · {{ number_format($active->amount, 0, ',', '.') }} ₺</p>
                            <p class="mt-1">Bitiş {{ $active->subscription->expires_at?->format('d.m.Y') ?? 'süresiz' }}</p>
                            @if ($renewSoon)
                                <p class="mt-2 font-medium text-amber-700">Yenileme yaklaşıyor</p>
                            @endif
                        @elseif ($pending && ! $ownPending)
                            <p>Ödeme {{ $pending->subscription->user?->name }} siparişinde bekliyor.</p>
                        @elseif ($pending)
                            <p>{{ $pending->subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }} sipariş · {{ number_format($pending->amount, 0, ',', '.') }} ₺</p>
                            <p class="mt-1">Havale onaylanınca ücretli özellikler açılır.</p>
                        @elseif ($quoteScale && ! $active && ! $pending)
                            <p class="font-medium text-slate-800">Özel Teklif</p>
                            <p class="mt-1">101 ve üzeri daireli apartmanlar için özel fiyatlandırma uygulanmaktadır. Talebiniz alınmıştır. Temsilcimiz sizinle iletişime geçerek size özel teklifinizi paylaşacaktır.</p>
                        @elseif ($needsQuote)
                            <p class="font-medium text-slate-800">Teklif gerekir</p>
                            <p class="mt-1">{{ $monthly['message'] }}</p>
                        @else
                            <p>Temel kullanım açık.</p>
                            <p class="mt-1">Aylık {{ number_format($monthly['amount'], 0, ',', '.') }} ₺ · Yıllık {{ number_format($yearly['amount'], 0, ',', '.') }} ₺</p>
                        @endif

                        @if ($canPurchase && $ownPending && $active)
                            <a href="{{ route('subscriber.subscriptions.receipt', $pending->subscription) }}" class="mt-2 inline-flex text-sm font-semibold text-amber-700 hover:text-amber-800">Yenileme ödemesi bekliyor</a>
                        @endif
                    </div>

                    @if ($currentApartmentModel && $currentApartmentModel->id === $apartment->id)
                        <p class="mt-3 text-xs font-semibold text-emerald-700">Seçili</p>
                    @endif

                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('subscriber.apartment.update') }}">
                            @csrf
                            <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">
                            <button class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Yönet</button>
                        </form>

                        @if ($canPurchase && $ownPending && ! $active)
                            <a href="{{ route('subscriber.subscriptions.receipt', $pending->subscription) }}" class="rounded-xl bg-amber-500 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-600">Ödemeyi tamamla</a>
                        @elseif ($canPurchase && ! $quoteScale && ! $needsQuote && ! ($pending && ! $active && $ownPending))
                            @php
                                $payModalId = 'pay-method-modal-'.$apartment->id;
                                $reopenPayModal = $errors->any() && in_array($apartment->id, array_map('intval', (array) old('apartment_ids', [])), true);
                            @endphp
                            <form method="POST" action="{{ route('subscriber.subscriptions.store') }}">
                                @csrf
                                <input type="hidden" name="apartment_ids[]" value="{{ $apartment->id }}">
                                <button type="button" onclick="document.getElementById('{{ $payModalId }}').classList.remove('hidden')" class="rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">{{ $active ? 'Yenile' : 'Ücretliye geç' }}</button>

                                <div id="{{ $payModalId }}" @class([
                                    'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4',
                                    'hidden' => ! $reopenPayModal,
                                ])>
                                    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="{{ $payModalId }}-title">
                                        <h3 id="{{ $payModalId }}-title" class="text-lg font-bold text-slate-900">Nasıl ödemek istersiniz?</h3>
                                        <p class="mt-2 text-sm text-slate-600">{{ $apartment->name }} için dönem ve ödeme yöntemini seçin. Sipariş, seçiminizden sonra açılır.</p>

                                        <label class="mt-4 block text-sm font-medium text-slate-700">
                                            Dönem
                                            <select name="period" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal">
                                                <option value="monthly" @selected(old('period', 'monthly') === 'monthly')>Aylık{{ isset($monthly['amount']) ? ' · '.number_format($monthly['amount'], 0, ',', '.').' ₺' : '' }}</option>
                                                <option value="yearly" @selected(old('period') === 'yearly')>Yıllık{{ isset($yearly['amount']) ? ' · '.number_format($yearly['amount'], 0, ',', '.').' ₺' : '' }}</option>
                                            </select>
                                        </label>

                                        <fieldset class="mt-4 space-y-2">
                                            <legend class="text-sm font-medium text-slate-700">Ödeme yöntemi</legend>
                                            <label class="mt-2 flex items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-800">
                                                <input type="radio" name="payment_method" value="havale" class="mt-1" @checked(old('payment_method', 'havale') === 'havale')>
                                                <span>
                                                    <span class="font-semibold">Havale / EFT</span>
                                                    <span class="mt-0.5 block text-slate-500">Banka bilgileri bir sonraki adımda gösterilir.</span>
                                                </span>
                                            </label>
                                            <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-400">
                                                <input type="radio" name="payment_method" value="kredi_kartı" class="mt-1" disabled>
                                                <span>
                                                    <span class="font-semibold">Kredi kartı</span>
                                                    <span class="mt-0.5 block">Henüz aktif değil</span>
                                                </span>
                                            </label>
                                        </fieldset>

                                        <div class="mt-4">
                                            @include('partials.accept-sales')
                                        </div>

                                        <div class="mt-5 flex justify-end gap-3">
                                            <button type="button" onclick="document.getElementById('{{ $payModalId }}').classList.add('hidden')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                                            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Devam</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        <script>
            document.querySelectorAll('[id^="pay-method-modal-"]').forEach(function (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        modal.classList.add('hidden');
                    }
                });
            });
        </script>
    @endif
@endsection
