@extends('layouts.apartment-setup')
@section('setup_title', 'Ödeme Durumu')
@section('setup_description', 'Ödemenizin doğrulanmış sonucunu buradan takip edebilirsiniz.')
@section('setup_width', '40rem')
@section('setup_close_url', route(session('subscription_return.'.$subscription->id) === 'dashboard' ? 'dashboard' : 'subscriber.dashboard'))
@section('content')
    <p class="text-sm text-slate-500">{{ $subscription->order_number }} · {{ number_format((float) $subscription->price, 2, ',', '.') }} ₺ (KDV dahil)</p>
    <p id="card-payment-message" role="status" aria-live="polite" class="mt-4 text-sm font-semibold text-slate-900">{{ $paymentState['message'] }}</p>
    <form id="card-payment-retry" method="POST" action="{{ route('subscriber.subscriptions.card.start', $subscription) }}" class="mt-4" @if (! $paymentState['can_start']) hidden @endif>
        @csrf
        <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Kartla ödemeye devam et</button>
    </form>
    <div class="mt-5 flex flex-wrap gap-4 text-sm font-semibold">
        <a href="{{ route('subscriber.subscriptions.receipt', $subscription) }}" class="text-emerald-700 underline" target="_top">Sipariş detayı</a>
        <a href="{{ route('subscriber.subscriptions.card-result', $subscription) }}" class="text-slate-700 underline" target="_top">Durumu yenile</a>
    </div>
    <script>
        (() => {
            if (window.top !== window.self) {
                try { window.top.location.replace(window.location.href); return; } catch (_) {}
            }
            let count = 0;
            let state = @json($paymentState['state']);
            const poll = async () => {
                if (!['waiting', 'ready'].includes(state) || count++ >= 60) return;
                try {
                    const response = await fetch(@json(route('subscriber.subscriptions.card-status', $subscription)), {headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
                    if (!response.ok) return;
                    const result = await response.json();
                    state = result.state;
                    document.getElementById('card-payment-message').textContent = result.message;
                    document.getElementById('card-payment-retry').hidden = !result.can_start;
                } catch (_) {}
                setTimeout(poll, 5000);
            };
            setTimeout(poll, 5000);
        })();
    </script>
@endsection
