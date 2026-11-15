                            <form novalidate method="POST" action="{{ route('subscriber.subscriptions.store') }}" onsubmit="return payStep(this.querySelector('[data-pay-modal]'), 'submit')">
                                @csrf
                                <input type="hidden" name="checkout_token" value="{{ old('checkout_token', (string) \Illuminate\Support\Str::uuid()) }}">
                                <input type="hidden" name="quote_signature" value="{{ $quoteSignature }}">
                                <input type="hidden" name="apartment_ids[]" value="{{ $apartment->id }}">


                                <div id="{{ $payModalId }}" data-pay-modal data-pay-step="{{ $payStep }}" data-checkout-close-url="{{ route('subscriber.dashboard') }}" data-apartment-name="{{ $apartment->name }}" data-unit-count="{{ $apartment->unit_count }}" data-price-monthly="{{ $monthly['amount'] ?? '' }}" data-price-yearly="{{ $yearly['amount'] ?? '' }}" @class([
                                    'fixed inset-0 z-50 items-center justify-center bg-black/50 p-4',
                                    'flex' => true,
                                    'hidden' => false,
                                ])  onclick="if (event.target === this) payStep(this, 'backdrop')">
                                    <div class="flex max-h-[92dvh] min-h-0 w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-xl" role="dialog" aria-modal="true" aria-labelledby="{{ $payModalId }}-title">
                                        <header class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                                            <div class="flex items-center gap-2"><img src="{{ asset('images/logo.png') }}" alt="" class="h-8 w-auto"><span class="text-lg font-bold"><span style="color:#336633">Aidat</span><span class="text-slate-400">Cep</span></span></div>
                                            <button type="button" onclick="return payStep(this, 'close')" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Kapat</button>
                                        </header>
                                        @if ($errors->any())
                                            <ul role="alert" class="shrink-0 list-inside list-disc px-5 py-2 text-sm text-red-600">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                                        @endif
                                        <div data-pay-panel="plan" @class([
                                            'min-h-0 flex-1 flex-col overflow-hidden',
                                            'flex' => $payStep === 'plan',
                                            'hidden' => $payStep !== 'plan',
                                        ]) @if ($payStep !== 'plan') hidden @endif>
                                            <div class="min-h-0 flex-1 overflow-y-auto p-6">
                                                @include('subscriber.partials.pay-steps', ['active' => 1])
                                                <h3 id="{{ $payModalId }}-title" class="text-lg font-bold text-slate-900">Abonelik ve Dönem</h3>
                                                <p class="mt-2 text-sm text-slate-600">{{ $apartment->name }} için dönemi seçin.</p>
                                                <p class="mt-2 text-sm font-semibold text-emerald-700">{{ match ($apartment->offer['action']) { 'renew' => 'Yenile', 'restart' => 'Yeniden Başlat', default => 'Ücretliye Geç' } }}</p>
                                                @if ($apartment->offer['record'])<p class="mt-1 font-mono text-xs text-slate-500">{{ $apartment->offer['record']->subscription_no }}</p>@endif
                                                @if ($apartment->offer['period'])
                                                    <p class="mt-1 text-xs text-slate-500">Mevcut dönem: {{ $apartment->offer['period']->started_at?->format('d.m.Y') }} – {{ $apartment->offer['period']->expires_at?->format('d.m.Y') ?? 'Süresiz' }}</p>
                                                @endif
                                                <div class="mt-4 space-y-2">
                                                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-800">
                                                        <input type="radio" name="period" value="monthly" class="mt-1" @checked(old('period', 'monthly') === 'monthly')>
                                                        <span>
                                                            <span class="font-semibold">Aylık</span>
                                                            @if (isset($monthly['amount']))
                                                                <span class="mt-0.5 block text-slate-500">{{ number_format($monthly['amount'], 2, ',', '.') }} ₺ (KDV dahil)</span>
                                                            @endif
                                                        </span>
                                                    </label>
                                                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-800">
                                                        <input type="radio" name="period" value="yearly" class="mt-1" @checked(old('period') === 'yearly')>
                                                        <span>
                                                            <span class="font-semibold">Yıllık</span>
                                                            @if (isset($yearly['amount']))
                                                                <span class="mt-0.5 block text-slate-500">{{ number_format($yearly['amount'], 2, ',', '.') }} ₺ (KDV dahil)</span>
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
                                                @include('subscriber.partials.billing-profile-picker', [
                                                    'profiles' => $billingProfiles,
                                                    'selectedId' => $billingMode === 'new' ? null : $selectedBillingProfileId,
                                                    'mode' => $billingMode,
                                                    'embedded' => true,
                                                ])
                                                <p data-pay-billing-error class="mt-2 hidden text-sm text-red-600" hidden>Devam etmek için fatura alanlarını doldurun.</p>
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
                                                <h3 class="text-lg font-bold text-slate-900">Sipariş Özeti ve Ödeme</h3>
                                                <p class="mt-2 text-sm text-slate-600">Ödeme yöntemini seçin. Sipariş, seçiminizden sonra açılır.</p>
                                                <dl class="mt-3 space-y-1 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <div><dt class="inline text-slate-500">Apartman:</dt> <dd class="inline font-semibold">{{ $apartment->name }}</dd></div>
                                                    <div><dt class="inline text-slate-500">Dönem:</dt> <dd class="inline font-semibold" data-checkout-period></dd></div>
                                                    <div><dt class="inline text-slate-500">Toplam (KDV dahil):</dt> <dd class="inline font-semibold" data-checkout-total></dd></div>
                                                    <div><dt class="inline text-slate-500">Hizmet başlangıcı:</dt> <dd class="inline">{{ $coverageEnd ? $coverageEnd->format('d.m.Y').' tarihinden itibaren, ödeme onayıyla' : 'Ödeme onayından itibaren' }}</dd></div>
                                                </dl>
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
                                                        <input type="radio" name="payment_method" value="kredi_kartı" class="mt-1" @disabled(! config('payments.card_enabled'))>
                                                        <span>
                                                            <span class="font-semibold">Kredi kartı</span>
                                                            <span class="mt-0.5 block">Yakında PayTR ile kullanılabilecek</span>
                                                        </span>
                                                    </label>
                                                </fieldset>
                                                @if (config('payments.card_enabled'))
                                                    <fieldset class="mt-4 space-y-3">
                                                        <legend class="text-sm font-semibold">Kart ödemesi için ödeyen kişi bilgileri</legend>
                                                        @foreach (['payer_name' => ['Ad soyad', auth()->user()->name, 60], 'payer_email' => ['E-posta', auth()->user()->email, 100], 'payer_phone' => ['Telefon', auth()->user()->phone, 20], 'payer_address' => ['Adres', '', 400]] as $field => [$label, $default, $limit])
                                                            <label class="block text-sm text-slate-700">{{ $label }}<input name="{{ $field }}" maxlength="{{ $limit }}" value="{{ old($field, $default) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2"></label>
                                                        @endforeach
                                                    </fieldset>
                                                @endif
                                                <div class="mt-4 space-y-2 text-sm text-slate-600">
                                                    <input type="checkbox" name="accept_sales" value="1" class="hidden" tabindex="-1" @checked(! $errors->has('quote_signature') && old('accept_sales'))>
                                                    <div class="flex items-start gap-2">
                                                        <input type="checkbox" data-pay-box="distance" class="pointer-events-none mt-0.5" tabindex="-1" @checked(! $errors->has('quote_signature') && old('accept_sales'))>
                                                        <button type="button" data-pay-open="distance" onclick="payStep(this, 'read')" class="text-left"><strong class="font-bold text-slate-950">Mesafeli satış sözleşmesini</strong> okudum, kabul ediyorum.</button>
                                                    </div>
                                                    <div class="flex items-start gap-2">
                                                        <input type="checkbox" data-pay-box="pre" class="pointer-events-none mt-0.5" tabindex="-1" @checked(! $errors->has('quote_signature') && old('accept_sales'))>
                                                        <button type="button" data-pay-open="pre" onclick="payStep(this, 'read')" class="text-left"><strong class="font-bold text-slate-950">Ön bilgilendirme formunu</strong> okudum, kabul ediyorum.</button>
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
                                                    <button type="submit" data-checkout-submit class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Siparişi Onayla</button>
                                                </div>
                                            </div>
                                        </div>

                                        @php
                                            $previewPeriod = $reopenThisPay ? old('period', 'monthly') : 'monthly';
                                            $previewProfile = $billingMode !== 'new' && $selectedBillingProfileId
                                                ? $billingProfiles->firstWhere('id', (int) $selectedBillingProfileId)
                                                : null;
                                            $legalPlaceholderTokens = true;
                                            $legalPlaceholderValues = \App\Support\LegalPlaceholders::preview([
                                                'apartment_name' => $apartment->name,
                                                'unit_count' => $apartment->unit_count,
                                                'period' => $previewPeriod,
                                                'price' => $previewPeriod === 'yearly' ? ($yearly['amount'] ?? null) : ($monthly['amount'] ?? null),
                                                'payment_method' => $reopenThisPay ? old('payment_method', 'havale') : 'havale',
                                                'billing_legal_name' => $billingMode === 'new' ? old('billing_legal_name') : $previewProfile?->legal_name,
                                                'billing_identity_number' => $billingMode === 'new' ? old('billing_identity_number') : $previewProfile?->identity_number,
                                                'billing_tax_office' => $billingMode === 'new' ? old('billing_tax_office') : $previewProfile?->tax_office,
                                                'billing_email' => $billingMode === 'new' ? old('billing_email') : $previewProfile?->email,
                                                'billing_phone' => $billingMode === 'new' ? old('billing_phone') : $previewProfile?->phone,
                                                'billing_address' => $billingMode === 'new' ? old('billing_address') : $previewProfile?->address,
                                                'billing_district' => $billingMode === 'new' ? old('billing_district') : $previewProfile?->district,
                                                'billing_province' => $billingMode === 'new' ? old('billing_province') : $previewProfile?->province,
                                                'billing_postal_code' => $billingMode === 'new' ? old('billing_postal_code') : $previewProfile?->postal_code,
                                                'billing_country' => $billingMode === 'new' ? old('billing_country') : $previewProfile?->country,
                                            ]);
                                        @endphp
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
