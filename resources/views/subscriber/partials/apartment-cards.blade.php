        @php
            $billingProfiles = auth()->user()->billingProfiles()->where('is_active', true)->orderBy('label')->orderBy('id')->get();
            $suggestedBillingProfileIds = collect();
            if ($billingProfiles->isNotEmpty()) {
                $suggestedBillingProfileIds = \App\Models\Subscription::query()
                    ->whereIn('apartment_id', $apartments->pluck('id'))
                    ->where('status', \App\Models\Subscription::STATUS_ACTIVE)
                    ->whereIn('billing_profile_id', $billingProfiles->pluck('id'))
                    ->orderByDesc('id')
                    ->get()
                    ->unique('apartment_id')
                    ->mapWithKeys(fn ($subscription) => [$subscription->apartment_id => $subscription->billing_profile_id]);
            }
        @endphp
        <script>
            function payStep(node, action) {
                const modal = node && node.hasAttribute && node.hasAttribute('data-pay-modal')
                    ? node
                    : (node && node.closest ? node.closest('[data-pay-modal]') : null);
                if (!modal) return false;
                const reveal = function (element, on) {
                    if (!element) return;
                    element.hidden = !on;
                    element.classList.toggle('hidden', !on);
                    if (element.hasAttribute('data-pay-panel') || element.hasAttribute('data-pay-modal')) {
                        element.classList.toggle('flex', on);
                    }
                };
                const show = function (name) {
                    modal.querySelectorAll('[data-pay-panel]').forEach(function (panel) {
                        reveal(panel, panel.getAttribute('data-pay-panel') === name);
                    });
                };
                const close = function () {
                    show('plan');
                    modal.hidden = true;
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                };
                if (action === 'open') {
                    modal.hidden = false;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    show(modal.getAttribute('data-pay-step') || 'plan');
                    return false;
                }
                if (action === 'close') {
                    close();
                    return false;
                }
                if (action === 'backdrop') {
                    const reading = modal.querySelector('[data-pay-panel="distance"]:not([hidden]), [data-pay-panel="pre"]:not([hidden])');
                    if (reading) show('payment');
                    else close();
                    return false;
                }
                if (action === 'next') {
                    const next = node.getAttribute('data-pay-next');
                    const billingError = modal.querySelector('[data-pay-billing-error]');
                    if (next === 'payment' && ! payStepReady(modal)) {
                        reveal(billingError, true);
                        return false;
                    }
                    reveal(billingError, false);
                    show(next);
                    return false;
                }
                if (action === 'back') {
                    show(node.getAttribute('data-pay-back') || node.getAttribute('data-pay-prev') || 'payment');
                    return false;
                }
                if (action === 'read') {
                    show(node.getAttribute('data-pay-open'));
                    return false;
                }
                if (action === 'accept') {
                    const visual = modal.querySelector('[data-pay-box="' + node.getAttribute('data-pay-accept') + '"]');
                    if (visual) visual.checked = true;
                    const distance = modal.querySelector('[data-pay-box="distance"]');
                    const pre = modal.querySelector('[data-pay-box="pre"]');
                    const box = modal.querySelector('input[name="accept_sales"]');
                    if (box && distance && pre) box.checked = distance.checked && pre.checked;
                    if (box && box.checked) reveal(modal.querySelector('[data-pay-error]'), false);
                    show('payment');
                    return false;
                }
                if (action === 'submit') {
                    const box = modal.querySelector('input[name="accept_sales"]');
                    if (!box || box.checked) return true;
                    show('payment');
                    reveal(modal.querySelector('[data-pay-error]'), true);
                    return false;
                }
                return false;
            }
            function payStepReady(modal) {
                const panel = modal.querySelector('[data-pay-panel="billing"]');
                if (!panel) return false;
                const hidden = panel.querySelector('input[type="hidden"][name="billing_mode"]');
                const selected = panel.querySelector('input[name="billing_mode"]:checked');
                const isNew = hidden ? hidden.value === 'new' : !!(selected && selected.value === 'new');
                if (isNew) {
                    const label = panel.querySelector('[name="billing_label"]');
                    const type = panel.querySelector('[name="billing_party_type"]');
                    return !!(label && label.value.trim() !== '' && type && type.value !== '');
                }
                const profile = panel.querySelector('[name="billing_profile_id"]');
                return !!(profile && profile.value !== '');
            }
        </script>
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
                    $reopenThisPay = in_array($apartment->id, array_map('intval', (array) old('apartment_ids', [])), true);
                    $suggestedBillingProfileId = $suggestedBillingProfileIds[$apartment->id] ?? null;
                    $selectedBillingProfileId = $reopenThisPay ? old('billing_profile_id', $suggestedBillingProfileId) : $suggestedBillingProfileId;
                    $billingMode = $reopenThisPay && old('billing_mode') ? old('billing_mode') : ($billingProfiles->isEmpty() ? 'new' : 'existing');
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
                        @if ($showDetails ?? false)
                            <a href="{{ route('subscriber.apartments.show', $apartment) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ayrıntılar</a>
                        @endif

                        @if ($showPay)
                            @php
                                $payModalId = 'pay-method-modal-'.$apartment->id;
                                $reopenPayModal = $errors->any() && in_array($apartment->id, array_map('intval', (array) old('apartment_ids', [])), true);
                                $payStep = 'plan';
                                if ($reopenPayModal) {
                                    $payStep = $errors->hasAny([
                                        'billing_mode',
                                        'billing_profile_id',
                                        'billing_label',
                                        'billing_party_type',
                                        'billing_legal_name',
                                        'billing_email',
                                    ]) ? 'billing' : 'payment';
                                }
                            @endphp
                            <form method="POST" action="{{ route('subscriber.subscriptions.store') }}" onsubmit="return payStep(this.querySelector('[data-pay-modal]'), 'submit')">
                                @csrf
                                <input type="hidden" name="apartment_ids[]" value="{{ $apartment->id }}">
                                <button type="button" onclick="return payStep(document.getElementById('{{ $payModalId }}'), 'open')" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">{{ $payLabel }}</button>

                                <div id="{{ $payModalId }}" data-pay-modal data-pay-step="{{ $payStep }}" @class([
                                    'fixed inset-0 z-50 items-center justify-center bg-black/50 p-4',
                                    'flex' => $reopenPayModal,
                                    'hidden' => ! $reopenPayModal,
                                ]) @unless ($reopenPayModal) hidden @endunless onclick="if (event.target === this) payStep(this, 'backdrop')">
                                    <div class="flex max-h-[85vh] min-h-0 w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-xl" role="dialog" aria-modal="true" aria-labelledby="{{ $payModalId }}-title">
                                        <div data-pay-panel="plan" @class([
                                            'min-h-0 flex-1 flex-col overflow-hidden',
                                            'flex' => $payStep === 'plan',
                                            'hidden' => $payStep !== 'plan',
                                        ]) @if ($payStep !== 'plan') hidden @endif>
                                            <div class="min-h-0 flex-1 overflow-y-auto p-6">
                                                @include('subscriber.partials.pay-steps', ['active' => 1])
                                                <h3 id="{{ $payModalId }}-title" class="text-lg font-bold text-slate-900">Abonelik türü</h3>
                                                <p class="mt-2 text-sm text-slate-600">{{ $apartment->name }} için dönemi seçin.</p>
                                                <div class="mt-4 space-y-2">
                                                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-800">
                                                        <input type="radio" name="period" value="monthly" class="mt-1" @checked(old('period', 'monthly') === 'monthly')>
                                                        <span>
                                                            <span class="font-semibold">Aylık</span>
                                                            @if (isset($monthly['amount']))
                                                                <span class="mt-0.5 block text-slate-500">{{ number_format($monthly['amount'], 0, ',', '.') }} ₺</span>
                                                            @endif
                                                        </span>
                                                    </label>
                                                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-800">
                                                        <input type="radio" name="period" value="yearly" class="mt-1" @checked(old('period') === 'yearly')>
                                                        <span>
                                                            <span class="font-semibold">Yıllık</span>
                                                            @if (isset($yearly['amount']))
                                                                <span class="mt-0.5 block text-slate-500">{{ number_format($yearly['amount'], 0, ',', '.') }} ₺</span>
                                                            @endif
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="flex shrink-0 justify-end gap-3 border-t border-slate-200 px-6 py-4">
                                                <button type="button" onclick="return payStep(this, 'close')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                                                <button type="button" data-pay-next="billing" onclick="return payStep(this, 'next')" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Devam</button>
                                            </div>
                                        </div>

                                        <div data-pay-panel="billing" @class([
                                            'min-h-0 flex-1 flex-col overflow-hidden',
                                            'flex' => $payStep === 'billing',
                                            'hidden' => $payStep !== 'billing',
                                        ]) @if ($payStep !== 'billing') hidden @endif>
                                            <div class="min-h-0 flex-1 overflow-y-auto p-6">
                                                @include('subscriber.partials.pay-steps', ['active' => 2])
                                                <h3 class="text-lg font-bold text-slate-900">Fatura bilgileri</h3>
                                                <p class="mt-2 text-sm text-slate-600">Bu siparişte kullanılacak fatura profilini seçin veya yeni bir profil oluşturun.</p>
                                                @include('subscriber.partials.billing-profile-picker', [
                                                    'profiles' => $billingProfiles,
                                                    'selectedId' => $billingMode === 'new' ? null : $selectedBillingProfileId,
                                                    'mode' => $billingMode,
                                                    'embedded' => true,
                                                ])
                                                <p data-pay-billing-error class="mt-2 hidden text-sm text-red-600" hidden>Devam etmek için bir fatura profili seçin veya yeni profilin adını ve türünü girin.</p>
                                            </div>
                                            <div class="flex shrink-0 justify-between gap-3 border-t border-slate-200 px-6 py-4">
                                                <button type="button" onclick="return payStep(this, 'close')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                                                <div class="flex gap-3">
                                                    <button type="button" data-pay-prev="plan" onclick="return payStep(this, 'back')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</button>
                                                    <button type="button" data-pay-next="payment" onclick="return payStep(this, 'next')" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Devam</button>
                                                </div>
                                            </div>
                                        </div>

                                        <div data-pay-panel="payment" @class([
                                            'min-h-0 flex-1 flex-col overflow-hidden',
                                            'flex' => $payStep === 'payment',
                                            'hidden' => $payStep !== 'payment',
                                        ]) @if ($payStep !== 'payment') hidden @endif>
                                            <div class="min-h-0 flex-1 overflow-y-auto p-6">
                                                @include('subscriber.partials.pay-steps', ['active' => 3])
                                                <h3 class="text-lg font-bold text-slate-900">Ödeme şekli</h3>
                                                <p class="mt-2 text-sm text-slate-600">Ödeme yöntemini seçin. Sipariş, seçiminizden sonra açılır.</p>
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
                                                    <div class="flex cursor-pointer items-start gap-2" data-pay-open="distance" onclick="payStep(this, 'read')">
                                                        <input type="checkbox" data-pay-box="distance" class="pointer-events-none mt-0.5" tabindex="-1" @checked(old('accept_sales'))>
                                                        <span><strong class="font-bold text-slate-950">Mesafeli satış sözleşmesini</strong> okudum, kabul ediyorum.</span>
                                                    </div>
                                                    <div class="flex cursor-pointer items-start gap-2" data-pay-open="pre" onclick="payStep(this, 'read')">
                                                        <input type="checkbox" data-pay-box="pre" class="pointer-events-none mt-0.5" tabindex="-1" @checked(old('accept_sales'))>
                                                        <span><strong class="font-bold text-slate-950">Ön bilgilendirme formunu</strong> okudum, kabul ediyorum.</span>
                                                    </div>
                                                </div>
                                                <p data-pay-error class="mt-1 hidden text-sm text-red-600" hidden>Ücretli abonelik için mesafeli satış sözleşmesini ve ön bilgilendirme formunu kabul edin.</p>
                                                @error('accept_sales')
                                                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="flex shrink-0 justify-between gap-3 border-t border-slate-200 px-6 py-4">
                                                <button type="button" onclick="return payStep(this, 'close')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                                                <div class="flex gap-3">
                                                    <button type="button" data-pay-prev="billing" onclick="return payStep(this, 'back')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</button>
                                                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Devam</button>
                                                </div>
                                            </div>
                                        </div>

                                        <div data-pay-panel="distance" class="hidden min-h-0 flex-1 flex-col overflow-hidden p-6" hidden role="region" aria-labelledby="{{ $payModalId }}-distance-title">
                                            <h3 id="{{ $payModalId }}-distance-title" class="text-lg font-bold text-slate-900">Mesafeli Satış Sözleşmesi</h3>
                                            <div class="mt-3 min-h-0 flex-1 overflow-y-auto text-sm leading-relaxed text-slate-600">
                                                @include('legal.content.distance-sales')
                                            </div>
                                            <div class="mt-4 flex justify-end gap-2">
                                                <button type="button" data-pay-back="payment" onclick="return payStep(this, 'back')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</button>
                                                <button type="button" data-pay-accept="distance" onclick="return payStep(this, 'accept')" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Okudum, kabul ediyorum</button>
                                            </div>
                                        </div>

                                        <div data-pay-panel="pre" class="hidden min-h-0 flex-1 flex-col overflow-hidden p-6" hidden role="region" aria-labelledby="{{ $payModalId }}-pre-title">
                                            <h3 id="{{ $payModalId }}-pre-title" class="text-lg font-bold text-slate-900">Ön Bilgilendirme Formu</h3>
                                            <div class="mt-3 min-h-0 flex-1 overflow-y-auto text-sm leading-relaxed text-slate-600">
                                                @include('legal.content.pre-information')
                                            </div>
                                            <div class="mt-4 flex justify-end gap-2">
                                                <button type="button" data-pay-back="payment" onclick="return payStep(this, 'back')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</button>
                                                <button type="button" data-pay-accept="pre" onclick="return payStep(this, 'accept')" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Okudum, kabul ediyorum</button>
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
