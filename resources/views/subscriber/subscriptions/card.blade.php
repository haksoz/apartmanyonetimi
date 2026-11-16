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
        #apartment-setup-title { font-size: 20px; line-height: 1.4; font-weight: 500; letter-spacing: -.02em; }
        #apartment-setup-description { margin-top: 6px; font-size: 13px; line-height: 1.6; font-weight: 400; color: #748092; }
        #apartment-setup-close { padding: 10px 16px; border-color: #dce1e8; border-radius: 12px; font-size: 14px; font-weight: 400; }
        .paytr-order-summary { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; gap: 12px 20px; max-width: 680px; margin: 0 auto 16px; padding: 18px 20px; border: 1px solid #e7e7eb; border-radius: 18px; background: #fafafa; }
        .paytr-order-name { font-size: 16px; line-height: 1.5; font-weight: 500; color: #202836; overflow-wrap: anywhere; }
        .paytr-order-meta { margin-top: 4px; font-size: 13px; line-height: 1.5; font-weight: 400; color: #748092; }
        .paytr-order-amount { text-align: right; white-space: nowrap; }
        .paytr-order-amount > p:first-child { font-size: 18px; line-height: 1.4; font-weight: 600; color: #202836; }
        .paytr-test-label { display: inline-block; margin-top: 9px; padding: 3px 9px; border-radius: 7px; background: #eef0f3; color: #647080; font-size: 11px; line-height: 1.5; font-weight: 400; }
        @media (max-width: 400px) {
            .paytr-order-summary { padding: 14px; gap: 10px; }
            .paytr-order-name { font-size: 14px; }
            .paytr-order-amount > p:first-child { font-size: 16px; }
        }
    </style>
@endpush
@section('content')
    <form id="payment-exit-form" method="POST" action="{{ route('subscriber.subscriptions.card-abandon', $subscription) }}" hidden>@csrf</form>
    <p id="payment-exit-error" role="alert" class="mb-3 text-sm text-red-600" hidden></p>
    <section class="paytr-order-summary" aria-label="Sipariş özeti">
        <div>
            <p class="paytr-order-name">{{ $subscription->items()->first()?->apartment_name }}</p>
            <p class="paytr-order-meta">{{ $subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }} abonelik · Tek çekim</p>
            @if ($attempt->is_test)<span class="paytr-test-label">Test ödemesi</span>@endif
        </div>
        <div class="paytr-order-amount">
            <p>{{ number_format((float) $subscription->price, 2, ',', '.') }} ₺</p>
            <p class="paytr-order-meta">KDV dahil</p>
        </div>
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
