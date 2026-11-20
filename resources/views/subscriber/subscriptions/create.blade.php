@extends('layouts.checkout')

@section('content')
    @php
        $checkoutReturn = old('checkout_return', request('from')) === 'dashboard' ? 'dashboard' : 'subscriber';
        $checkoutCloseUrl = route($checkoutReturn === 'dashboard' ? 'dashboard' : 'subscriber.dashboard');
    @endphp
    @include('subscriber.partials.checkout-script')
    @if ($checkoutApartment)
        @php
            $apartment = $checkoutApartment;
            $monthly = $apartment->monthly_quote;
            $yearly = $apartment->yearly_quote;
            $billingMode = old('billing_mode', $billingProfiles->isEmpty() ? 'new' : 'existing');
            $reopenThisPay = $errors->any();
            $payModalId = 'subscription-checkout';
            $reopenPayModal = true;
            $payStep = $errors->hasAny(['period', 'quote_signature', 'apartment_ids']) ? 'plan'
                : ($errors->hasAny(['billing_mode', 'billing_profile_id', 'billing_label', 'billing_party_type', 'billing_legal_name', 'billing_address', 'billing_identity_number', 'billing_tax_office']) ? 'billing'
                : ($errors->any() ? 'payment' : 'plan'));
        @endphp
        @include('subscriber.partials.checkout-modal')
        <script>
            const checkout = document.querySelector('[data-pay-modal]');
            payLegalFill(checkout);
            payTrack(checkout, 'viewed');
            checkout.addEventListener('change', function (event) {
                if (['period', 'payment_method', 'billing_mode', 'billing_profile_id', 'billing_party_type'].includes(event.target.name)) {
                    payTrack(checkout, 'changed', null, event.target.name);
                }
            });
            checkout.querySelector('[data-pay-panel]:not([hidden]) input, [data-pay-panel]:not([hidden]) button')?.focus();
        </script>
    @else
        <div class="fixed inset-0 flex items-center justify-center p-3 sm:p-5" style="background: rgb(15 23 42 / .18); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);">
            <main role="dialog" aria-modal="true" aria-labelledby="checkout-picker-title" class="max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-xl">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2"><img src="{{ asset('images/logo.png') }}" alt="" class="h-8 w-auto"><span class="text-xl font-bold"><span style="color:#336633">Aidat</span><span class="text-slate-400">Cep</span></span></div>
                    <a href="{{ $checkoutCloseUrl }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Kapat</a>
                </div>
                <h1 id="checkout-picker-title" class="text-lg font-bold">Abonelik ve Ödeme</h1>
                <p class="mt-1 text-sm text-slate-500">İşlem yapmak istediğiniz apartmanı seçin. Her sipariş tek apartman içindir.</p>
                @if (session('status'))<p class="mt-3 text-sm text-emerald-700">{{ session('status') }}</p>@endif
                @if ($errors->any())<ul role="alert" class="mt-3 text-sm text-red-600">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
                <div class="mt-4 space-y-3">
                    @forelse ($apartments as $apartment)
                        <article class="rounded-xl border border-slate-200 p-4">
                            <h2 class="font-semibold">{{ $apartment->name }}</h2>
                            @if ($apartment->offer['action'] === 'pending')
                                <p class="mt-1 text-sm text-amber-800">Ödeme Bekleyen Siparişiniz Var</p>
                                @if ((int) $apartment->offer['order']->user_id === (int) auth()->id())
                                    <a href="{{ route('subscriber.subscriptions.receipt', $apartment->offer['order']) }}" class="mt-2 inline-block font-semibold text-emerald-700 underline">Ödemeye Devam Et</a>
                                @else
                                    <p class="mt-1 text-sm text-slate-500">Bu apartmanın başka bir kullanıcı tarafından açılmış siparişi bulunuyor.</p>
                                @endif
                            @elseif ($apartment->unit_count > 100 || $apartment->monthly_quote['requires_quote'])
                                <p class="mt-1 text-sm text-slate-500">{{ \App\Support\ApartmentCommercial::QUOTE_MESSAGE }}</p>
                            @elseif ($apartment->offer['record'])
                                <a href="{{ route('subscriber.subscriptions.create', ['apartment_id' => $apartment->id, 'from' => $checkoutReturn]) }}" class="mt-2 inline-block rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">{{ $apartment->offer['action'] === 'upgrade' ? 'Ücretliye Geç' : 'Yenile' }}</a>
                            @else
                                <p class="mt-1 text-sm text-slate-500">Apartmanın abonelik kurulumu tamamlanmalıdır.</p>
                            @endif
                        </article>
                    @empty
                        <p class="text-sm text-slate-500">Ücretli kullanım için önce bir apartman oluşturun.</p>
                    @endforelse
                </div>
            </main>
        </div>
    @endif
@endsection
