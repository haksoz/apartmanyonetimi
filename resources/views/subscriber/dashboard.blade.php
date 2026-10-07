@extends('layouts.app')

@section('title', 'Abone Paneli')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-950 sm:text-2xl">Abone Paneli</h1>
        <p class="mt-1 text-sm text-slate-500">Apartmanlarınızın kullanım durumu ve şu anda yapmanız gereken işlem burada.</p>
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
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($apartments as $apartment)
                @php
                    $active = $apartment->commercial_active;
                    $pending = $apartment->commercial_pending;
                    $expired = $apartment->commercial_expired;
                    $monthly = $apartment->monthly_quote;
                    $yearly = $apartment->yearly_quote;
                    $ownActive = $active && (int) $active->subscription->user_id === (int) auth()->id();
                    $ownPending = $pending && (int) $pending->subscription->user_id === (int) auth()->id();
                    $needsQuote = ($monthly['requires_quote'] ?? false) && ! $active;
                    $quoteScale = (int) $apartment->unit_count > 100;
                    $canPurchase = (bool) $apartment->can_purchase;
                    $renewSoon = $active && $active->expires_at && $active->expires_at->lessThanOrEqualTo(now()->addDays(3));
                    $daysLeft = $renewSoon ? (int) now()->startOfDay()->diffInDays($active->expires_at->copy()->startOfDay()) : null;
                    $proofSent = $ownPending && $pending->subscription?->hasPaymentProof();
                    $showPay = $canPurchase && ! $quoteScale && ! $needsQuote && ! ($ownPending && ! $active);
                    $payLabel = ($active || $expired) ? 'Yenile' : 'Ücretliye Geç';
                    $place = collect([$apartment->district, $apartment->province])->filter()->implode(' / ');
                    if ($place === '' && $apartment->address) {
                        $place = \Illuminate\Support\Str::limit($apartment->address, 48);
                    }
                    $periodLabel = $active?->subscription?->notes === 'Tanımlı süre'
                        ? 'Tanımlı süre'
                        : ($active?->subscription?->period === 'yearly' ? 'Yıllık' : 'Aylık');
                    if ($active) {
                        $statusLabel = 'AidatCep Ücretli Kullanım';
                        $statusClass = 'bg-emerald-50 text-emerald-800';
                    } elseif ($quoteScale) {
                        $statusLabel = 'Özel Teklif';
                        $statusClass = 'bg-slate-100 text-slate-700';
                    } elseif ($expired) {
                        $statusLabel = 'Ücretli Kullanım Sona Erdi';
                        $statusClass = 'bg-red-50 text-red-700';
                    } else {
                        $statusLabel = 'AidatCep Ücretsiz Kullanım';
                        $statusClass = 'bg-slate-100 text-slate-700';
                    }
                @endphp
                <article class="flex flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-lg font-bold text-slate-950">{{ $apartment->name }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $apartment->unit_count }} daire{{ $place !== '' ? ' · '.$place : '' }}</p>
                        </div>
                        <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>

                    <div class="mt-4 flex-1 space-y-1 text-sm text-slate-600">
                        @if ($quoteScale && ! $active && ! $pending)
                            <p class="font-medium text-slate-800">Özel Teklif</p>
                            <p>{{ \App\Support\ApartmentCommercial::QUOTE_MESSAGE }}</p>
                        @elseif ($pending && ! $active && $ownPending && $proofSent)
                            <p>AidatCep Temel yönetim açık.</p>
                            <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
                                Ödeme bilgileriniz alınmıştır, admin onayı bekleniyor.
                                <a href="{{ route('subscriber.subscriptions.receipt', $pending->subscription) }}" class="mt-1 block underline">Sipariş Detayları</a>
                            </p>
                        @elseif ($pending && ! $active && $ownPending)
                            <p>AidatCep Temel yönetim açık.</p>
                            <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
                                Ücretli kullanım siparişiniz oluşturuldu. Ödemeniz bekleniyor.
                                <a href="{{ route('subscriber.subscriptions.receipt', $pending->subscription) }}" class="mt-1 block underline">Sipariş Detayları</a>
                            </p>
                        @elseif ($pending && ! $active)
                            <p>AidatCep Temel yönetim açık.</p>
                            <p>Ödeme {{ $pending->subscription->user?->name }} siparişinde bekliyor.</p>
                        @elseif ($active && ! $ownActive)
                            <p>Bu hizmet {{ $apartment->name }} için {{ $active->subscription->user?->name }} tarafından satın alınmıştır.</p>
                            <p>Paket: {{ $periodLabel }}</p>
                            @if ($active->started_at)
                                <p>Başlangıç {{ $active->started_at->format('d.m.Y') }}</p>
                            @endif
                            <p>Bitiş {{ $active->expires_at?->format('d.m.Y') ?? 'süresiz' }}</p>
                        @elseif ($active)
                            <p>Paket: {{ $periodLabel }}</p>
                            @if ($active->started_at)
                                <p>Başlangıç {{ $active->started_at->format('d.m.Y') }}</p>
                            @endif
                            <p>Bitiş {{ $active->expires_at?->format('d.m.Y') ?? 'süresiz' }}</p>
                        @elseif ($expired)
                            <p>Ücretli özelliklerin kullanımı sona erdi. Devam etmek için yeni bir dönem başlatabilirsiniz.</p>
                            @if ($expired->expires_at)
                                <p>Son bitiş {{ $expired->expires_at->format('d.m.Y') }}</p>
                            @endif
                        @elseif ($needsQuote)
                            <p class="font-medium text-slate-800">Teklif gerekir</p>
                            <p>{{ $monthly['message'] }}</p>
                        @else
                            <p>AidatCep Temel yönetim açık.</p>
                        @endif

                        @if ($renewSoon)
                            <p class="pt-1 font-medium text-amber-700">
                                @if ($daysLeft < 1)
                                    Paketinizin süresi bugün doluyor.
                                @else
                                    Paketinizin bitmesine {{ $daysLeft }} gün kaldı.
                                @endif
                            </p>
                        @endif

                        @if ($canPurchase && $ownPending && $active)
                            @if ($proofSent)
                                <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
                                    Ödeme bilgileriniz alınmıştır, admin onayı bekleniyor.
                                    <a href="{{ route('subscriber.subscriptions.receipt', $pending->subscription) }}" class="mt-1 block underline">Sipariş Detayları</a>
                                </p>
                            @else
                                <a href="{{ route('subscriber.subscriptions.receipt', $pending->subscription) }}" class="mt-2 inline-flex text-sm font-semibold text-amber-700 hover:text-amber-800">Yenileme ödemesi bekliyor</a>
                            @endif
                        @endif
                    </div>

                    @if (! $active && ! $pending && ! $quoteScale && ! $needsQuote && isset($monthly['amount'], $yearly['amount']) && $monthly['amount'] !== null && $yearly['amount'] !== null)
                        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2">
                            <p class="text-xs font-semibold text-emerald-800">AidatCep Ücretli Kullanım avantajlarından yararlanın.</p>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <div class="rounded-lg bg-white px-2 py-1 text-center">
                                    <p class="text-xs text-slate-500">Aylık</p>
                                    <p class="text-sm font-semibold text-slate-950">{{ number_format($monthly['amount'], 0, ',', '.') }} ₺</p>
                                </div>
                                <div class="rounded-lg bg-white px-2 py-1 text-center">
                                    <p class="text-xs text-slate-500">Yıllık</p>
                                    <p class="text-sm font-semibold text-slate-950">{{ number_format($yearly['amount'], 0, ',', '.') }} ₺</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('subscriber.apartment.update') }}">
                            @csrf
                            <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">
                            <button class="rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Apartmanı Yönet</button>
                        </form>

                        @if ($showPay)
                            @php
                                $payModalId = 'pay-method-modal-'.$apartment->id;
                                $reopenPayModal = $errors->any() && in_array($apartment->id, array_map('intval', (array) old('apartment_ids', [])), true);
                            @endphp
                            <form method="POST" action="{{ route('subscriber.subscriptions.store') }}">
                                @csrf
                                <input type="hidden" name="apartment_ids[]" value="{{ $apartment->id }}">
                                <button type="button" onclick="document.getElementById('{{ $payModalId }}').classList.remove('hidden')" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">{{ $payLabel }}</button>

                                <div id="{{ $payModalId }}" @class([
                                    'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4',
                                    'hidden' => ! $reopenPayModal,
                                ])>
                                    <div class="flex max-h-[85vh] w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-xl" role="dialog" aria-modal="true" aria-labelledby="{{ $payModalId }}-title">
                                        <div data-pay-panel="order" class="overflow-y-auto p-6">
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

                                            <div class="mt-4 space-y-2 text-sm text-slate-600">
                                                <input type="checkbox" name="accept_sales" value="1" class="hidden" tabindex="-1" @checked(old('accept_sales'))>
                                                <div class="flex cursor-pointer items-start gap-2" data-pay-open="distance">
                                                    <input type="checkbox" data-pay-box="distance" class="pointer-events-none mt-0.5" tabindex="-1" @checked(old('accept_sales'))>
                                                    <span><strong class="font-bold text-slate-950">Mesafeli satış sözleşmesini</strong> okudum, kabul ediyorum.</span>
                                                </div>
                                                <div class="flex cursor-pointer items-start gap-2" data-pay-open="pre">
                                                    <input type="checkbox" data-pay-box="pre" class="pointer-events-none mt-0.5" tabindex="-1" @checked(old('accept_sales'))>
                                                    <span><strong class="font-bold text-slate-950">Ön bilgilendirme formunu</strong> okudum, kabul ediyorum.</span>
                                                </div>
                                            </div>
                                            <p data-pay-error class="mt-1 hidden text-sm text-red-600">Ücretli abonelik için mesafeli satış sözleşmesini ve ön bilgilendirme formunu kabul edin.</p>
                                            @error('accept_sales')
                                                <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                            @enderror

                                            <div class="mt-5 flex justify-end gap-3">
                                                <button type="button" data-pay-close class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                                                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Devam</button>
                                            </div>
                                        </div>

                                        <div data-pay-panel="distance" class="hidden min-h-0 flex-1 flex-col overflow-hidden p-6" role="region" aria-labelledby="{{ $payModalId }}-distance-title">
                                            <h3 id="{{ $payModalId }}-distance-title" class="text-lg font-bold text-slate-900">Mesafeli Satış Sözleşmesi</h3>
                                            <div class="mt-3 min-h-0 flex-1 overflow-y-auto text-sm leading-relaxed text-slate-600">
                                                @include('legal.content.distance-sales')
                                            </div>
                                            <div class="mt-4 flex justify-end gap-2">
                                                <button type="button" data-pay-back class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</button>
                                                <button type="button" data-pay-accept="distance" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Okudum, kabul ediyorum</button>
                                            </div>
                                        </div>

                                        <div data-pay-panel="pre" class="hidden min-h-0 flex-1 flex-col overflow-hidden p-6" role="region" aria-labelledby="{{ $payModalId }}-pre-title">
                                            <h3 id="{{ $payModalId }}-pre-title" class="text-lg font-bold text-slate-900">Ön Bilgilendirme Formu</h3>
                                            <div class="mt-3 min-h-0 flex-1 overflow-y-auto text-sm leading-relaxed text-slate-600">
                                                @include('legal.content.pre-information')
                                            </div>
                                            <div class="mt-4 flex justify-end gap-2">
                                                <button type="button" data-pay-back class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</button>
                                                <button type="button" data-pay-accept="pre" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Okudum, kabul ediyorum</button>
                                            </div>
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
                const box = modal.querySelector('input[name="accept_sales"]');
                const boxes = {
                    distance: modal.querySelector('[data-pay-box="distance"]'),
                    pre: modal.querySelector('[data-pay-box="pre"]'),
                };
                const error = modal.querySelector('[data-pay-error]');
                const accepted = { distance: box.checked, pre: box.checked };
                const show = function (name) {
                    modal.querySelectorAll('[data-pay-panel]').forEach(function (panel) {
                        const on = panel.getAttribute('data-pay-panel') === name;
                        panel.classList.toggle('hidden', !on);
                        if (panel.getAttribute('data-pay-panel') !== 'order') {
                            panel.classList.toggle('flex', on);
                        }
                    });
                };
                const sync = function () {
                    boxes.distance.checked = accepted.distance;
                    boxes.pre.checked = accepted.pre;
                    box.checked = accepted.distance && accepted.pre;
                    if (box.checked) error.classList.add('hidden');
                };
                sync();
                modal.querySelectorAll('[data-pay-open]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        show(button.getAttribute('data-pay-open'));
                    });
                });
                modal.querySelectorAll('[data-pay-back]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        show('order');
                    });
                });
                modal.querySelectorAll('[data-pay-accept]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        accepted[button.getAttribute('data-pay-accept')] = true;
                        sync();
                        show('order');
                    });
                });
                modal.querySelector('[data-pay-close]').addEventListener('click', function () {
                    show('order');
                    modal.classList.add('hidden');
                });
                modal.addEventListener('click', function (event) {
                    if (event.target !== modal) return;
                    const reading = modal.querySelector('[data-pay-panel="distance"]:not(.hidden), [data-pay-panel="pre"]:not(.hidden)');
                    if (reading) {
                        show('order');
                        return;
                    }
                    modal.classList.add('hidden');
                });
                modal.closest('form').addEventListener('submit', function (event) {
                    if (!box.checked) {
                        event.preventDefault();
                        show('order');
                        error.classList.remove('hidden');
                    }
                });
            });
        </script>
    @endif
@endsection
