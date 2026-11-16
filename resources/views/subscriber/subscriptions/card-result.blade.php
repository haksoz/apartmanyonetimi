@extends('layouts.apartment-setup')
@section('setup_title', $paymentState['title'])
@section('setup_description', in_array($paymentState['state'], ['screen_failed', 'screen_unavailable'], true) ? 'Güvenli ödeme ekranına geçiş tamamlanamadı.' : 'Ödemenizin doğrulanmış sonucunu buradan takip edebilirsiniz.')
@section('setup_width', '40rem')
@section('setup_close_url', route(session('subscription_return.'.$subscription->id) === 'dashboard' ? 'dashboard' : 'subscriber.dashboard'))
@push('setup_styles')
    <style>
        .payment-verification-spinner { display: inline-block; width: 1.25rem; height: 1.25rem; flex-shrink: 0; border: 2px solid #cbd5e1; border-top-color: #336633; border-radius: 50%; animation: payment-verification-spin .8s linear infinite; }
        .payment-verification-spinner[hidden] { display: none; }
        @keyframes payment-verification-spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .payment-verification-spinner { animation: none; } }
    </style>
@endpush
@section('content')
    @php($verifying = in_array($paymentState['state'], ['waiting', 'ready'], true))
    <p class="text-sm text-slate-500">{{ $subscription->order_number }} · {{ number_format((float) $subscription->price, 2, ',', '.') }} ₺ (KDV dahil)</p>
    <div class="mt-4 flex items-center gap-3">
        <span id="card-payment-spinner" class="payment-verification-spinner" aria-hidden="true" @if (! $verifying) hidden @endif></span>
        <p id="card-payment-message" role="status" aria-live="polite" class="text-sm font-semibold text-slate-900">{{ $verifying ? 'Ödeme sonucunuz doğrulanıyor.' : $paymentState['message'] }}</p>
    </div>
    <form id="card-payment-retry" method="POST" action="{{ route('subscriber.subscriptions.card.start', $subscription) }}" class="mt-4" @if ($verifying || ! $paymentState['can_start']) hidden @endif>
        @csrf
        <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Ödemeye Geç</button>
    </form>
    <div class="mt-5 flex flex-wrap gap-4 text-sm font-semibold">
        <a id="card-payment-new-checkout" href="{{ route('subscriber.subscriptions.create', ['apartment_id' => $subscription->items()->value('apartment_id'), 'from' => session('subscription_return.'.$subscription->id) === 'dashboard' ? 'dashboard' : 'subscriber']) }}" class="text-emerald-700 underline" target="_top" @if (! $paymentState['can_restart']) hidden @endif>Yeniden başla</a>
        @if (!in_array($paymentState['state'], ['abandoned', 'screen_failed', 'screen_unavailable'], true))
        <a href="{{ route('subscriber.subscriptions.receipt', $subscription) }}" class="text-emerald-700 underline" target="_top">Sipariş detayı</a>
        @else
        <a href="{{ route(session('subscription_return.'.$subscription->id) === 'dashboard' ? 'dashboard' : 'subscriber.dashboard') }}" class="text-slate-700 underline" target="_top">Panele dön</a>
        @endif
        <a id="card-payment-refresh" href="{{ route('subscriber.subscriptions.card-result', $subscription) }}" class="text-slate-700 underline" target="_top" hidden>Durumu yenile</a>
    </div>
    <script>
        (() => {
            if (window.top !== window.self) {
                try {
                    const exitForm = window.top.document.getElementById('payment-exit-form');
                    if (exitForm) exitForm.dataset.exitSent = '1';
                    window.top.location.replace(window.location.href); return;
                } catch (_) {}
            }
            let count = 0;
            let state = @json($paymentState['state']);
            const stopWaiting = () => {
                document.getElementById('card-payment-spinner').hidden = true;
                document.getElementById('card-payment-refresh').hidden = false;
                document.getElementById('card-payment-message').textContent = 'Ödeme sonucu henüz doğrulanamadı. Tekrar ödeme yapmayın; durumu yenileyebilir veya daha sonra kontrol edebilirsiniz.';
            };
            const poll = async () => {
                if (!['waiting', 'ready', 'abandoned', 'screen_unavailable'].includes(state)) return;
                if (count++ >= 60) { stopWaiting(); return; }
                try {
                    const response = await fetch(@json(route('subscriber.subscriptions.card-status', $subscription)), {headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
                    if (!response.ok) { stopWaiting(); return; }
                    const result = await response.json();
                    state = result.state;
                    document.getElementById('apartment-setup-title').textContent = result.title;
                    document.title = result.title + ' — AidatCep';
                    const verifying = ['waiting', 'ready'].includes(state);
                    document.getElementById('card-payment-spinner').hidden = !verifying;
                    document.getElementById('card-payment-message').textContent = verifying ? 'Ödeme sonucunuz doğrulanıyor.' : result.message;
                    document.getElementById('card-payment-retry').hidden = verifying || !result.can_start;
                    document.getElementById('card-payment-new-checkout').hidden = !result.can_restart;
                } catch (_) { stopWaiting(); return; }
                setTimeout(poll, 5000);
            };
            setTimeout(poll, 5000);
        })();
    </script>
@endsection
