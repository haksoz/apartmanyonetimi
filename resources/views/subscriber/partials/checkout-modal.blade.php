@php
    $cardEnabled = app(\App\Support\Paytr\Access::class)->canStart(auth()->user());
    $paymentMethods = app(\App\Support\CheckoutPaymentMethods::class)->forUser(auth()->user());
    $selectedMethod = in_array(old('payment_method'), $paymentMethods, true) ? old('payment_method') : ($paymentMethods[0] ?? null);
    $contactFieldsToComplete = \App\Support\Paytr\PayerContact::fieldsToComplete(auth()->user());
@endphp
                            <form novalidate method="POST" action="{{ route('subscriber.subscriptions.store') }}" onsubmit="return payStep(this.querySelector('[data-pay-modal]'), 'submit')">
                                @csrf
                                <input type="hidden" name="checkout_journey" value="{{ $checkoutJourney->uuid }}" data-journey-url="{{ route('subscriber.checkout-journeys.events', $checkoutJourney->uuid) }}">
                                <input type="hidden" name="checkout_return" value="{{ $checkoutReturn }}">
                                <input type="hidden" name="checkout_token" value="{{ old('checkout_token', (string) \Illuminate\Support\Str::uuid()) }}">
                                <input type="hidden" name="quote_signature" value="{{ $quoteSignature }}">
                                <input type="hidden" name="apartment_ids[]" value="{{ $apartment->id }}">


                                <div id="{{ $payModalId }}" data-pay-modal data-timeout-seconds="{{ \App\Models\CheckoutJourney::FORM_TIMEOUT_SECONDS }}" data-timeout-deadline="{{ $checkoutJourney->last_seen_at->copy()->addSeconds(\App\Models\CheckoutJourney::FORM_TIMEOUT_SECONDS)->toIso8601String() }}" style="background: rgb(15 23 42 / .18); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);" data-pay-step="{{ $payStep }}" data-checkout-close-url="{{ $checkoutCloseUrl }}" data-apartment-name="{{ $apartment->name }}" data-unit-count="{{ $apartment->unit_count }}" data-price-monthly="{{ $monthly['amount'] ?? '' }}" data-price-yearly="{{ $yearly['amount'] ?? '' }}" @class([
                                    'fixed inset-0 z-50 items-center justify-center bg-black/50 p-4',
                                    'flex' => true,
                                    'hidden' => false,
                                ]) onclick="if (event.target === this) payStep(this, 'backdrop')">
                                    <div data-pay-content class="flex max-h-[92dvh] min-h-0 w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-xl" role="dialog" aria-modal="true" aria-labelledby="{{ $payModalId }}-title">
                                        <header class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                                            <div class="flex items-center gap-2"><img src="{{ asset('images/logo.png') }}" alt="" class="h-8 w-auto"><span class="text-lg font-bold"><span style="color:#336633">Aidat</span><span class="text-slate-400">Cep</span></span></div>
                                            <button type="button" onclick="return payStep(this, 'close')" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Kapat</button>
                                        </header>
                                        @if ($errors->any())
                                            <ul data-checkout-errors role="alert" class="shrink-0 list-inside list-disc px-5 py-2 text-sm text-red-600">@foreach ($errors->messages() as $field => $messages)@foreach ($messages as $message)<li data-checkout-error-field="{{ $field }}">{{ $message }}</li>@endforeach @endforeach</ul>
                                        @endif
                                        <div data-pay-panel="plan" @class([
                                            'min-h-0 flex-1 flex-col overflow-hidden',
                                            'flex' => $payStep === 'plan',
                                            'hidden' => $payStep !== 'plan',
                                        ]) @if ($payStep !== 'plan') hidden @endif>
                                            <div class="min-h-0 flex-1 overflow-y-auto p-6">
                                                @include('subscriber.partials.pay-steps', ['active' => 1])
                                                <h3 id="{{ $payModalId }}-title" class="text-lg font-bold text-slate-900">{{ $apartment->name }} için dönemi seçin.</h3>
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
                                                <h3 class="text-lg font-bold text-slate-900">{{ $apartment->name }}</h3>
                                                <p class="mt-1 text-sm font-medium text-emerald-700">Seçiminiz: <span data-checkout-period>{{ old('period', 'monthly') === 'yearly' ? 'Yıllık (12 ay)' : 'Aylık' }}</span> abonelik · <span data-checkout-total>{{ number_format(old('period', 'monthly') === 'yearly' ? ($yearly['amount'] ?? 0) : ($monthly['amount'] ?? 0), 2, ',', '.') }} ₺</span> (KDV dahil)</p>
                                                @include('subscriber.partials.billing-profile-picker', [
                                                    'profiles' => $billingProfiles,
                                                    'selectedId' => $billingMode === 'new' ? null : $selectedBillingProfileId,
                                                    'mode' => $billingMode,
                                                    'embedded' => true,
                                                ])
                                                <p class="mt-3 text-xs text-slate-500">Yeni fatura bilgileriniz, Devam dediğinizde sonraki işlemleriniz için kaydedilir. Bu adımda sipariş oluşturulmaz.</p>
                                                <p data-pay-billing-error class="mt-2 hidden text-sm text-red-600" hidden>Devam etmek için fatura alanlarını doldurun.</p>
                                            </div>
                                            <div class="flex shrink-0 justify-end gap-3 border-t border-slate-200 px-6 py-4">
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
                                                <dl class="mt-3 space-y-1 rounded-xl bg-slate-50 p-3 text-sm">
                                                    <div><dt class="inline text-slate-500">Apartman:</dt> <dd class="inline font-semibold">{{ $apartment->name }}</dd></div>
                                                    <div><dt class="inline text-slate-500">Dönem:</dt> <dd class="inline font-semibold" data-checkout-period></dd></div>
                                                    <div><dt class="inline text-slate-500">Toplam (KDV dahil):</dt> <dd class="inline font-semibold" data-checkout-total></dd></div>
                                                    <div><dt class="inline text-slate-500">Hizmet başlangıcı:</dt> <dd class="inline">{{ $coverageEnd ? $coverageEnd->format('d.m.Y').' tarihinden itibaren, ödeme onayıyla' : 'Ödeme onayından itibaren' }}</dd></div>
                                                </dl>
                                                @if (count($paymentMethods) > 1)
                                                <fieldset class="mt-4 space-y-2">
                                                    <legend class="text-sm font-medium text-slate-700">Ödeme yöntemi</legend>
                                                    <label class="mt-2 flex items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-800">
                                                        <input type="radio" name="payment_method" value="havale" class="mt-1" @checked($selectedMethod === 'havale')>
                                                        <span>
                                                            <span class="font-semibold">Havale / EFT</span>
                                                            <span class="mt-0.5 block text-slate-500">Banka bilgileri bir sonraki adımda gösterilir.</span>
                                                        </span>
                                                    </label>
                                                    @if (! config('paytr.test_accounts_only') || $cardEnabled)
                                                    <label @class(['flex items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm', 'bg-slate-50 text-slate-400' => ! $cardEnabled, 'text-slate-800' => $cardEnabled])>
                                                        <input type="radio" name="payment_method" value="kredi_kartı" class="mt-1" @checked($selectedMethod === 'kredi_kartı')>
                                                        <span>
                                                            <span class="font-semibold">Kredi kartı</span>
                                                            <span class="mt-1 block">@include('subscriber.partials.paytr-mark')</span>
                                                        </span>
                                                    </label>
                                                    @endif
                                                </fieldset>
                                                @elseif ($selectedMethod)
                                                    <input type="hidden" name="payment_method" value="{{ $selectedMethod }}">
                                                    @if ($selectedMethod === 'kredi_kartı')
                                                        <div class="mt-4">@include('subscriber.partials.paytr-mark')</div>
                                                    @else
                                                        <p class="mt-4 text-sm text-slate-600">Ödeme yöntemi: Havale / EFT</p>
                                                    @endif
                                                @else
                                                    <p role="status" class="mt-4 text-sm text-slate-600">Kartla ödeme şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.</p>
                                                @endif
                                                @if ($cardEnabled)
                                                    <fieldset data-card-payer @class(['mt-4 space-y-2', 'hidden' => $selectedMethod !== 'kredi_kartı']) @if ($selectedMethod !== 'kredi_kartı') hidden disabled @endif>
                                                        <legend class="text-sm font-semibold">PayTR Ödeme İletişim Bilgileri</legend>
                                                        <p class="text-xs text-slate-500">Bilgiler müşteri profilinizden alınır.</p>
                                                        <dl class="flex flex-col gap-1 text-sm text-slate-700">
                                                        @foreach (['payer_name' => ['Ad soyad', auth()->user()->name], 'payer_email' => ['E-posta', auth()->user()->email], 'payer_phone' => ['Telefon', auth()->user()->phone], 'payer_address' => ['İletişim adresi', auth()->user()->address]] as $field => [$label, $value])
                                                            <div class="min-w-0 break-words">
                                                                <dt @class(['sr-only' => $field === 'payer_name', 'inline text-slate-500' => $field !== 'payer_name'])>{{ $label }}:</dt>
                                                                <dd class="inline">
                                                                    <span data-contact-value="{{ $field }}" @class(['font-semibold' => $field === 'payer_name', 'text-slate-500' => $field === 'payer_address'])>{{ filled($value) ? $value : $label.' eksik' }}</span>
                                                                @if (in_array(str_replace('payer_', '', $field), $contactFieldsToComplete, true))
                                                                    <p data-contact-missing="{{ $field }}" class="mt-1 text-xs text-amber-700">Bu bilgiyi aşağıdaki butondan tamamlayabilirsiniz.</p>
                                                                @elseif (blank($value))
                                                                    <p class="mt-1 text-xs text-amber-700">Bu bilgiyi <a href="{{ route('profile.edit') }}" class="underline">Hesabım</a> sayfasından tamamlayın.</p>
                                                                @endif
                                                                @error($field)<p data-contact-error="{{ $field }}" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                                                </dd>
                                                            </div>
                                                        @endforeach
                                                        </dl>
                                                        @if ($contactFieldsToComplete !== [])
                                                            @include('subscriber.partials.checkout-contact-editor')
                                                        @endif
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
                                            <div class="flex shrink-0 justify-end gap-3 border-t border-slate-200 px-6 py-4">
                                                <div class="flex gap-3">
                                                    <button type="button" data-pay-prev="billing" onclick="return payStep(this, 'back')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</button>
                                                    <button type="submit" data-checkout-submit @if (!$selectedMethod) data-payment-unavailable disabled @endif class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ödemeye Geç</button>
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
                                                'payment_method' => $selectedMethod,
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
                                    <style>[data-checkout-timeout]::backdrop { background: rgb(15 23 42 / .35); backdrop-filter: blur(2px); }</style>
                                    <dialog data-checkout-timeout data-response-seconds="{{ \App\Models\CheckoutJourney::RENEWAL_RESPONSE_SECONDS }}" aria-labelledby="{{ $payModalId }}-timeout-title" aria-describedby="{{ $payModalId }}-timeout-description" class="rounded-2xl bg-white p-5 shadow-xl" style="position: fixed; inset: 0; margin: auto; width: min(24rem, calc(100vw - 2rem)); height: fit-content; max-height: calc(100dvh - 2rem); overflow-y: auto; border: 0;">
                                        <h2 id="{{ $payModalId }}-timeout-title" class="text-lg font-semibold text-slate-900">Devam etmek ister misiniz?</h2>
                                        <p id="{{ $payModalId }}-timeout-description" class="mt-2 text-sm text-slate-600">Bu aşamanın süresi doldu. Bilgilerinizi kaybetmeden devam etmek için süreyi uzatın. Yanıt vermezseniz işlem kapanacak.</p>
                                        <p class="mt-3 text-sm text-slate-700">Kalan yanıt süresi: <strong data-timeout-countdown>{{ \App\Models\CheckoutJourney::RENEWAL_RESPONSE_SECONDS }}</strong> saniye</p>
                                        <p data-timeout-error role="alert" class="mt-2 text-sm text-red-600" hidden></p>
                                        <div class="mt-5 flex flex-wrap justify-end gap-3">
                                            <button type="button" data-timeout-close class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">İşlemi kapat</button>
                                            <button type="button" data-timeout-renew autofocus class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">2 dakika daha devam et</button>
                                        </div>
                                    </dialog>
                                </div>
                            </form>
