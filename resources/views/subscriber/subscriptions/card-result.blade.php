@extends('layouts.apartment-setup')
@section('setup_title', in_array($paymentState['state'], ['screen_failed', 'screen_unavailable'], true) ? 'Ödeme ekranı açılamadı' : 'Ödeme Durumu')
@section('setup_description', in_array($paymentState['state'], ['screen_failed', 'screen_unavailable'], true) ? 'Güvenli ödeme ekranına geçiş tamamlanamadı.' : 'Ödemenizin doğrulanmış sonucunu buradan takip edebilirsiniz.')
@section('setup_width', '40rem')
@section('setup_close_url', route(session('subscription_return.'.$subscription->id) === 'dashboard' ? 'dashboard' : 'subscriber.dashboard'))
@section('content')
    <p class="text-sm text-slate-500">{{ $subscription->order_number }} · {{ number_format((float) $subscription->price, 2, ',', '.') }} ₺ (KDV dahil)</p>
    <p id="card-payment-message" role="status" aria-live="polite" class="mt-4 text-sm font-semibold text-slate-900">{{ $paymentState['message'] }}</p>
    <form id="card-payment-retry" method="POST" action="{{ route('subscriber.subscriptions.card.start', $subscription) }}" class="mt-4" @if (! $paymentState['can_start']) hidden @endif>
        @csrf
        <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Ödemeye Geç</button>
    </form>
    <div class="mt-5 flex flex-wrap gap-4 text-sm font-semibold">
        <a id="card-payment-new-checkout" href="{{ route('subscriber.subscriptions.create', ['apartment_id' => $subscription->items()->value('apartment_id')]) }}" class="text-emerald-700 underline" target="_top" @if ($paymentState['state'] !== 'abandoned' && ! $paymentState['can_restart']) hidden @endif>Yeniden başla</a>
        @if (!in_array($paymentState['state'], ['abandoned', 'screen_failed', 'screen_unavailable'], true))
        <a href="{{ route('subscriber.subscriptions.receipt', $subscription) }}" class="text-emerald-700 underline" target="_top">Sipariş detayı</a>
        @else
        <a href="{{ route(session('subscription_return.'.$subscription->id) === 'dashboard' ? 'dashboard' : 'subscriber.dashboard') }}" class="text-slate-700 underline" target="_top">Panele dön</a>
        @endif
        <a href="{{ route('subscriber.subscriptions.card-result', $subscription) }}" class="text-slate-700 underline" target="_top">Durumu yenile</a>
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
            const poll = async () => {
                if (!['waiting', 'ready', 'abandoned', 'screen_unavailable'].includes(state) || count++ >= 60) return;
                try {
                    const response = await fetch(@json(route('subscriber.subscriptions.card-status', $subscription)), {headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
                    if (!response.ok) return;
                    const result = await response.json();
                    state = result.state;
                    document.getElementById('card-payment-message').textContent = result.message;
                    document.getElementById('card-payment-retry').hidden = !result.can_start;
                    document.getElementById('card-payment-new-checkout').hidden = !result.can_restart && state !== 'abandoned';
                } catch (_) {}
                setTimeout(poll, 5000);
            };
            setTimeout(poll, 5000);
        })();
    </script>
@endsection
