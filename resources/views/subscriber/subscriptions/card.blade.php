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
        .paytr-order-summary { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; margin: 0 0 14px; padding-bottom: 12px; border-bottom: 1px solid #eef0f3; text-align: left; }
        .paytr-order-details { display: flex; flex: 1 1 200px; flex-wrap: wrap; align-items: baseline; gap: 5px 8px; min-width: 0; }
        .paytr-order-name { min-width: 0; font-size: 15px; line-height: 1.5; font-weight: 600; color: #202836; overflow-wrap: anywhere; }
        .paytr-order-period { font-size: 13px; line-height: 1.5; color: #647080; }
        .paytr-test-label { display: inline-block; padding: 2px 6px; border-radius: 5px; background: #eef0f3; color: #647080; font-size: 10px; line-height: 1.5; font-weight: 400; }
        .paytr-payment-brand { flex-shrink: 0; width: 88px; height: auto; }
    </style>
@endpush
@section('content')
    <form id="payment-exit-form" method="POST" action="{{ route('subscriber.subscriptions.card-abandon', $subscription) }}" hidden>@csrf</form>
    <p id="payment-exit-error" role="alert" class="mb-3 text-sm text-red-600" hidden></p>
    <section class="paytr-order-summary" aria-label="Sipariş özeti">
        <div class="paytr-order-details">
            <p class="paytr-order-name">{{ $subscription->items()->first()?->apartment_name }}</p>
            <p class="paytr-order-period">· {{ $subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }} abonelik</p>
            @if ($attempt->is_test)<span class="paytr-test-label">Test ödemesi</span>@endif
        </div>
        <img src="{{ asset('images/paytr-logo.svg') }}" alt="PayTR" width="88" height="15" class="paytr-payment-brand">
    </section>
    <iframe src="https://www.paytr.com/odeme/guvenli/{{ rawurlencode($attempt->iframe_token) }}" id="paytriframe" title="PayTR güvenli kart ödeme formu" style="width:100%;min-height:640px;border:0" scrolling="no" referrerpolicy="no-referrer"></iframe>
    <dialog id="paytr-timeout" aria-labelledby="paytr-timeout-title" aria-describedby="paytr-timeout-description"
        data-duration-seconds="180" data-response-seconds="20" data-attempt-id="{{ $attempt->id }}"
        data-provider-deadline="{{ $attempt->token_expires_at->toIso8601String() }}"
        data-event-url="{{ route('subscriber.subscriptions.card-timer', $subscription) }}"
        data-result-url="{{ route('subscriber.subscriptions.card-result', $subscription) }}"
        style="position:fixed;inset:0;margin:auto;width:min(26rem,calc(100% - 2rem));max-height:calc(100dvh - 2rem);padding:24px;border:0;border-radius:16px;box-shadow:0 20px 60px #0003">
        <h2 id="paytr-timeout-title" class="text-lg font-semibold text-slate-900">Ödemeye devam etmek istiyor musunuz?</h2>
        <p id="paytr-timeout-description" class="mt-3 text-sm text-slate-600">3 dakikalık ekran süresi doldu. Devam etmek için süreyi uzatın. Yanıt vermezseniz bu ekran kapanır. Başlamış bir ödeme iptal edilmez; sonucu ayrıca işlenir.</p>
        <p class="mt-3 text-sm text-slate-500">Kalan yanıt süresi: <strong data-card-countdown>20</strong> saniye</p>
        <p data-card-timeout-error role="alert" class="mt-3 text-sm text-red-600" hidden></p>
        <div class="mt-5 flex flex-wrap justify-end gap-3">
            <button type="button" data-card-timeout-close class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">İşlemi kapat</button>
            <button type="button" data-card-timeout-renew class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">3 dakika uzat</button>
        </div>
    </dialog>
    <style>#paytr-timeout::backdrop { background: rgb(15 23 42 / .25); backdrop-filter: blur(4px); }</style>
    <script src="{{ asset('js/paytr-screen-timeout.js') }}?v={{ filemtime(public_path('js/paytr-screen-timeout.js')) }}" defer></script>
    <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
    <script>if (typeof iFrameResize === 'function') iFrameResize({}, '#paytriframe');</script>
    <script>
        // A new checkout in another tab can retire this screen. Stop presenting its old form.
        let paymentScreenLeft = false;
        const checkPaymentScreen = async () => {
            if (paymentScreenLeft) return;
            try {
                const response = await fetch(@json(route('subscriber.subscriptions.card-status', $subscription)), {headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
                if (response.ok) {
                    const result = await response.json();
                    if (['abandoned', 'superseded', 'cancelled', 'review', 'paid', 'failed', 'waiting'].includes(result.state)) {
                        paymentScreenLeft = true;
                        document.getElementById('payment-exit-form').dataset.exitSent = '1';
                        window.dispatchEvent(new Event('payment-screen-exited'));
                        window.location.replace(@json(route('subscriber.subscriptions.card-result', $subscription)));
                        return;
                    }
                }
            } catch (_) {}
            setTimeout(checkPaymentScreen, 5000);
        };
        setTimeout(checkPaymentScreen, 5000);
        window.addEventListener('payment-screen-exited', () => {
            paymentScreenLeft = true;
            document.getElementById('paytriframe')?.remove();
        });
        window.addEventListener('pagehide', () => {
            paymentScreenLeft = true;
            const form = document.getElementById('payment-exit-form');
            if (form.dataset.exitSent !== '1') navigator.sendBeacon(form.action, new FormData(form));
        });
        window.addEventListener('pageshow', event => {
            if (event.persisted) window.location.replace(@json(route('subscriber.subscriptions.create', ['apartment_id' => $subscription->items()->value('apartment_id')])));
        });
    </script>
@endsection
