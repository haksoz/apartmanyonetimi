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
                    $awaitingOwnPayment = $canPurchase && $ownPending && ! $active;
                    $showPay = $canPurchase && ! $quoteScale && ! $needsQuote && ! $awaitingOwnPayment;
                    $payLabel = ($active || $expired) ? 'Yenile' : 'Ücretliye Geç';
                    $place = collect([$apartment->district, $apartment->province])->filter()->implode(' / ');
                    if ($place === '' && $apartment->address) {
                        $place = \Illuminate\Support\Str::limit($apartment->address, 48);
                    }
                    $periodLabel = $active?->subscription?->notes === 'Tanımlı süre'
                        ? 'Tanımlı süre'
                        : ($active?->subscription?->period === 'yearly' ? 'Yıllık' : 'Aylık');
                    if ($quoteScale && ! $active && ! $pending) {
                        $statusLabel = 'Özel Teklif';
                        $statusClass = 'bg-slate-100 text-slate-700';
                    } elseif ($pending && ! $active) {
                        $statusLabel = 'Ödeme Bekliyor';
                        $statusClass = 'bg-amber-50 text-amber-800';
                    } elseif ($active) {
                        $statusLabel = 'Ücretli Kullanım';
                        $statusClass = 'bg-emerald-50 text-emerald-800';
                    } elseif ($expired) {
                        $statusLabel = 'Ücretli Kullanım Sona Erdi';
                        $statusClass = 'bg-red-50 text-red-700';
                    } else {
                        $statusLabel = 'Ücretsiz Kullanım';
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
                        @elseif ($pending && ! $active && $ownPending)
                            <p>Ücretli kullanım siparişiniz oluşturuldu. Ödeme bildiriminizi tamamlayın.</p>
                        @elseif ($pending && ! $active)
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
                            <a href="{{ route('subscriber.subscriptions.receipt', $pending->subscription) }}" class="mt-2 inline-flex text-sm font-semibold text-amber-700 hover:text-amber-800">Yenileme ödemesi bekliyor</a>
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
                        @if ($awaitingOwnPayment)
                            <a href="{{ route('subscriber.subscriptions.receipt', $pending->subscription) }}" class="rounded-xl bg-amber-500 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-600">Ödemeyi Tamamla</a>
                        @endif

                        <form method="POST" action="{{ route('subscriber.apartment.update') }}">
                            @csrf
                            <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">
                            <button class="rounded-xl {{ $awaitingOwnPayment ? 'border border-slate-300 text-slate-800 hover:bg-slate-50' : 'bg-slate-900 text-white hover:bg-slate-800' }} px-3 py-2 text-sm font-semibold">Apartmanı Yönet</button>
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
