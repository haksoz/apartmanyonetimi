@extends('layouts.apartment-setup')
@section('setup_title', 'Güvenli Kart Ödemesi')
@section('setup_description', 'Kart bilgilerinizi PayTR güvenli ödeme ekranına girin.')
@section('setup_width', '56rem')
@section('setup_confirm_close', 'true')
@section('setup_close_message', 'Ödeme ekranından çıkmak istiyor musunuz? Çıkış yapmak, başlamış bir ödemeyi iptal etmez.')
@section('setup_close_url', route(session('subscription_return.'.$subscription->id) === 'dashboard' ? 'dashboard' : 'subscriber.dashboard'))
@push('setup_styles')
    <style>
        #apartment-setup-dialog { background: #fff; }
        #apartment-setup-dialog > header { border-color: #e7e7eb; }
        /* Keep the dialog's accessible name without a visible heading above the payment form. */
        #apartment-setup-title { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        #apartment-setup-description { display: none; }
        #apartment-setup-dialog > header { align-items: center; padding-top: 12px; padding-bottom: 12px; }
        #apartment-setup-dialog > header > div > div { margin-bottom: 0; }
        #apartment-setup-dialog > header img { height: 24px; }
        #apartment-setup-dialog > header > div > div > span { font-size: 18px; }
        #apartment-setup-close { padding: 7px 12px; border-color: #dce1e8; border-radius: 10px; font-size: 14px; font-weight: 400; }
        #apartment-setup-dialog > main { padding-top: 12px; }
        .paytr-order-summary { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; max-width: 680px; margin: 0 auto 8px; }
        .paytr-order-name { min-width: 0; font-size: 13px; line-height: 1.5; font-weight: 400; color: #647080; overflow-wrap: anywhere; }
        .paytr-test-label { display: inline-block; padding: 2px 6px; border-radius: 5px; background: #eef0f3; color: #647080; font-size: 10px; line-height: 1.5; font-weight: 400; }
    </style>
@endpush
@section('content')
    <form id="payment-exit-form" method="POST" action="{{ route('subscriber.subscriptions.card-abandon', $subscription) }}" hidden>@csrf</form>
    <p id="payment-exit-error" role="alert" class="mb-3 text-sm text-red-600" hidden></p>
    <section class="paytr-order-summary" aria-label="Sipariş özeti">
        <p class="paytr-order-name">{{ $subscription->items()->first()?->apartment_name }} · {{ $subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }} abonelik</p>
        @if ($attempt->is_test)<span class="paytr-test-label">Test ödemesi</span>@endif
    </section>
    <iframe src="https://www.paytr.com/odeme/guvenli/{{ rawurlencode($attempt->iframe_token) }}" id="paytriframe" title="PayTR güvenli kart ödeme formu" style="width:100%;min-height:640px;border:0" scrolling="no" referrerpolicy="no-referrer"></iframe>
    <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
    <script>if (typeof iFrameResize === 'function') iFrameResize({}, '#paytriframe');</script>
    <script>
        window.addEventListener('pagehide', () => {
            const form = document.getElementById('payment-exit-form');
            if (form.dataset.exitSent !== '1') navigator.sendBeacon(form.action, new FormData(form));
        });
        window.addEventListener('pageshow', event => {
            if (event.persisted) window.location.replace(@json(route('subscriber.subscriptions.create', ['apartment_id' => $subscription->items()->value('apartment_id')])));
        });
    </script>
@endsection
