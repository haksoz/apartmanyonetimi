@extends('layouts.apartment-setup')
@section('setup_title', 'Güvenli Kart Ödemesi')
@section('setup_description', 'Kart bilgilerinizi PayTR güvenli ödeme ekranına girin.')
@section('setup_width', '56rem')
@section('setup_close_url', route(session('subscription_return.'.$subscription->id) === 'dashboard' ? 'dashboard' : 'subscriber.dashboard'))
@section('content')
    <div class="mb-4 text-sm text-slate-600">
        <p class="font-semibold text-slate-900">{{ $subscription->items()->first()?->apartment_name }} · {{ $subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }} abonelik</p>
        <p class="mt-1">{{ number_format((float) $subscription->price, 2, ',', '.') }} ₺ (KDV dahil) · Tek çekim</p>
        @if ($attempt->is_test)<p class="mt-2 font-semibold text-amber-700">Test ödemesi</p>@endif
    </div>
    <iframe src="https://www.paytr.com/odeme/guvenli/{{ $attempt->iframe_token }}" id="paytriframe" title="PayTR güvenli kart ödeme formu" style="width:100%;min-height:640px;border:0" scrolling="no" referrerpolicy="no-referrer"></iframe>
    <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
    <script>if (typeof iFrameResize === 'function') iFrameResize({}, '#paytriframe');</script>
    <a href="{{ route('subscriber.subscriptions.card-result', $subscription) }}" class="mt-4 inline-block text-sm font-semibold text-emerald-700 underline">Ödeme durumunu kontrol et</a>
@endsection
