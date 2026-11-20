@extends('layouts.app')
@section('title', 'Kredi Kartı İşlem Detayı')
@section('content')
    <a href="{{ route('admin.card-transactions.index') }}" class="text-sm font-semibold text-emerald-700">← Kredi Kartı İşlemleri</a>
    <h1 class="mt-3 text-2xl font-bold text-slate-900">Kredi Kartı İşlem Detayı</h1>
    @if ($journey)<a href="{{ route('admin.checkout-journeys.show', $journey) }}" class="mt-2 inline-block text-sm font-semibold text-emerald-700">{{ $journey->reference }} · Geçiş geçmişi</a>@endif
    <p class="mt-1 break-all font-mono text-sm text-slate-500">{{ $attempt->transaction_number }}</p>
    <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap gap-3">@include('admin.card-transactions.status')</div>
        <p class="mt-3 break-all text-xs text-slate-500">PayTR referansı: <span class="font-mono">{{ $attempt->merchant_oid }}</span></p>
        <dl class="mt-4 grid gap-4 break-words text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-slate-500">Müşteri</dt><dd>{{ $attempt->subscription?->user?->name ?? '—' }}</dd></div>
            <div>
                <dt class="text-slate-500">Sipariş</dt>
                <dd>
                    @if ($attempt->subscription?->order_number)
                        <a class="font-semibold text-emerald-700" href="{{ route('admin.orders.show', $attempt->subscription) }}">{{ $attempt->subscription->order_number }}</a>
                    @else
                        Sipariş oluşmadı
                    @endif
                    @if ($attempt->subscription?->trashed()) (Pasif silinen) @endif
                </dd>
            </div>
            <div><dt class="text-slate-500">Tutar</dt><dd>{{ number_format($attempt->amount_minor / 100, 2, ',', '.') }} {{ $attempt->currency }}</dd></div>
            <div><dt class="text-slate-500">Oluşturuldu</dt><dd>{{ $attempt->created_at->format('d.m.Y H:i:s') }}</dd></div>
            <div><dt class="text-slate-500">Ödeme kuruluşuna gönderildi</dt><dd>{{ $attempt->submitted_at?->format('d.m.Y H:i:s') ?? 'Gönderilmedi' }}</dd></div>
            <div><dt class="text-slate-500">Sonuçlandı</dt><dd>{{ $attempt->settled_at?->format('d.m.Y H:i:s') ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Ödeme ekranından çıkıldı</dt><dd>{{ $attempt->subscription?->checkout_abandoned_at?->format('d.m.Y H:i:s') ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Ödeme ekranı hata kodu</dt><dd>{{ $attempt->token_failure_code ?? '—' }}</dd></div>
        </dl>
    </section>
    <h2 class="mt-6 text-lg font-semibold text-slate-900">Bildirim geçmişi</h2>
    <div class="mt-3 space-y-3">
        @forelse ($attempt->notifications as $notification)
            <article class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                <div class="flex flex-wrap justify-between gap-2"><strong>{{ match ($notification->outcome) { 'succeeded' => 'Ödeme işlendi', 'failed' => 'Başarısız ödeme', 'duplicate' => 'Tekrarlanan bildirim', 'review' => 'İnceleme gerekli', default => $notification->outcome } }}</strong><span class="text-slate-500">{{ $notification->created_at->format('d.m.Y H:i:s') }}</span></div>
                <p class="mt-2">Bildirilen sonuç: {{ $notification->provider_status === 'success' ? 'Başarılı' : 'Başarısız' }} · Tutar: {{ number_format($notification->total_amount_minor / 100, 2, ',', '.') }} · Teslim sayısı: {{ $notification->deliveries }}</p>
                @if ($notification->failed_reason_code)<p class="mt-1">Hata kodu: {{ $notification->failed_reason_code }} · {{ \App\Support\Paytr\FailureMessage::forCode($notification->failed_reason_code)['title'] }}</p>@endif
                @if ($notification->reason)<p class="mt-1 break-all text-red-700">İnceleme nedeni: {{ $notification->reason }}</p>@endif
                <p class="mt-1 text-xs text-slate-500">Son teslim: {{ $notification->updated_at->format('d.m.Y H:i:s') }}</p>
            </article>
        @empty
            <p class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500">Henüz ödeme bildirimi ulaşmadı.</p>
        @endforelse
    </div>
@endsection
