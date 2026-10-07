@extends('layouts.app')

@section('title', 'Sipariş Detayı')

@section('content')
    @php
        $payer = $order->user;
        $linkedSubscription = $order->subscription;
        $subscriptionDetailItem = null;

        if ($linkedSubscription) {
            $subscriptionDetailItem = $order->items->first(
                fn ($item) => (int) $item->apartment_subscription_id === (int) $linkedSubscription->id
            ) ?? $linkedSubscription->items->sortByDesc('id')->first();
        }

        $planLabel = function ($plan) {
            return match ($plan) {
                \App\Models\SubscriptionItem::PLAN_PAID => 'Ücretli',
                \App\Models\SubscriptionItem::PLAN_FREE => 'Ücretsiz',
                default => '—',
            };
        };
        $itemStatusLabel = function ($status) {
            return match ($status) {
                \App\Models\SubscriptionItem::STATUS_PENDING => 'Bekliyor',
                \App\Models\SubscriptionItem::STATUS_ACTIVE => 'Aktif',
                \App\Models\SubscriptionItem::STATUS_CANCELLED => 'İptal',
                default => $status ?: '—',
            };
        };
        $periodText = function ($item) {
            if (! $item?->started_at) {
                return '—';
            }

            return $item->started_at->format('d.m.Y').' – '.($item->expires_at?->format('d.m.Y') ?? 'süresiz');
        };
        $paymentMethodLabel = function ($method) {
            return match ($method) {
                'kredi_kartı' => 'Kredi kartı',
                'nakit' => 'Nakit',
                'havale' => 'Havale / EFT',
                default => $method ?: '—',
            };
        };
        $apartmentNames = $order->items
            ->map(fn ($item) => $item->apartment?->name ?? $item->apartment_name)
            ->filter()
            ->unique()
            ->values();
        $periods = $order->items->map(fn ($item) => $periodText($item))->unique()->values();
        $plans = $order->items->map(fn ($item) => $planLabel($item->plan))->unique()->filter(fn ($label) => $label !== '—')->values();
        $summaryPeriod = $order->items->isEmpty() ? '—' : ($periods->count() === 1 ? $periods->first() : 'Birden fazla dönem');
        $summaryPlan = $order->items->isEmpty() ? '—' : ($plans->count() === 1 ? $plans->first() : 'Birden fazla');
        $summaryApartment = $apartmentNames->isNotEmpty()
            ? $apartmentNames->join(', ')
            : ($linkedSubscription?->apartment?->name ?: '—');
    @endphp

    <div class="mb-6">
        <a href="{{ route('admin.orders.index') }}" class="text-sm font-semibold text-amber-700 hover:text-amber-800">← Siparişlere dön</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Sipariş Detayı</h1>
        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-amber-700">Sipariş no</p>
        <p class="mt-1 font-mono text-2xl font-bold text-slate-900">{{ $order->order_number ?: '—' }}</p>
    </div>

    <section class="mb-6 rounded-xl border border-amber-200 bg-amber-50/50 p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Sipariş bilgileri</p>
                <p class="mt-1 text-sm text-slate-600">Bu ticari işlemin durumu.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @include('admin.orders.partials.badges', ['order' => $order])
                @if ($order->status === \App\Models\UserSubscription::STATUS_CANCELLED && $order->cancelled_by === \App\Models\UserSubscription::CANCELLED_BY_CUSTOMER)
                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Müşteri iptal etti</span>
                @elseif ($order->status === \App\Models\UserSubscription::STATUS_CANCELLED && $order->cancelled_by === \App\Models\UserSubscription::CANCELLED_BY_ADMIN)
                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Admin iptal etti</span>
                @endif
            </div>
        </div>
        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-slate-500">Sipariş no</dt>
                <dd class="font-mono font-semibold text-slate-900">{{ $order->order_number ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Sipariş tarihi</dt>
                <dd class="font-medium text-slate-900">{{ $order->created_at?->format('d.m.Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Toplam tutar</dt>
                <dd class="font-medium text-slate-900">{{ number_format((float) $order->price, 2, ',', '.') }} ₺</dd>
            </div>
        </dl>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">Abonelik / apartman</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Abonelik no</dt>
                    <dd class="font-medium text-slate-900">{{ $linkedSubscription?->subscription_no ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Apartman</dt>
                    <dd class="font-medium text-slate-900">{{ $summaryApartment }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">İlgili dönem</dt>
                    <dd class="font-medium text-slate-900">{{ $summaryPeriod }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Plan</dt>
                    <dd class="font-medium text-slate-900">{{ $summaryPlan }}</dd>
                </div>
            </dl>
            <div class="mt-4 flex flex-wrap gap-2">
                @if ($subscriptionDetailItem)
                    <a href="{{ route('admin.subscription-items.show', $subscriptionDetailItem) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Abonelik Detayına Git</a>
                @endif
                @if ($order->items->count() <= 1 && ($linkedSubscription?->apartment || $order->items->first()?->apartment))
                    <a href="{{ route('apartments.show', $linkedSubscription?->apartment ?? $order->items->first()->apartment) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apartman Detayına Git</a>
                @endif
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">Ödeyen</h2>
            <dl class="mt-4 grid gap-3 text-sm">
                <div>
                    <dt class="text-slate-500">Kullanıcı</dt>
                    <dd class="font-medium text-slate-900">{{ $payer?->name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">E-posta</dt>
                    <dd class="font-medium text-slate-900">{{ $payer?->email ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Telefon</dt>
                    <dd class="font-medium text-slate-900">{{ $payer?->phone ?: '—' }}</dd>
                </div>
            </dl>
        </section>
    </div>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Sipariş kalemleri</h2>
        @if ($order->items->isEmpty())
            <p class="mt-3 text-sm text-slate-500">Bu kayıtta sipariş kalemi yok.</p>
        @else
            <div class="mt-4 space-y-3">
                @foreach ($order->items as $item)
                    <article class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $item->apartment?->name ?? $item->apartment_name ?: '—' }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $item->apartmentSubscription?->subscription_no ?: '—' }}</p>
                            </div>
                            <p class="font-semibold text-slate-900">{{ number_format((float) $item->amount, 2, ',', '.') }} ₺</p>
                        </div>
                        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="text-slate-500">Plan</dt>
                                <dd class="font-medium text-slate-900">{{ $planLabel($item->plan) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Dönem</dt>
                                <dd class="font-medium text-slate-900">{{ $periodText($item) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Kalem durumu</dt>
                                <dd class="font-medium text-slate-900">{{ $itemStatusLabel($item->status) }}</dd>
                            </div>
                        </dl>
                        @if ($order->items->count() > 1)
                            <div class="mt-3 flex flex-wrap gap-3">
                                @if ($item->apartmentSubscription)
                                    <a href="{{ route('admin.subscription-items.show', $item) }}" class="text-sm font-semibold text-slate-700 hover:text-slate-900">Abonelik Detayına Git</a>
                                @endif
                                @if ($item->apartment)
                                    <a href="{{ route('apartments.show', $item->apartment) }}" class="text-sm font-semibold text-slate-700 hover:text-slate-900">Apartman Detayına Git</a>
                                @endif
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Ödemeler</h2>
        @if ($order->receipt_reference || $order->receipt_path)
            <p class="mt-3 text-sm text-slate-700">
                Dekont: {{ $order->receipt_reference ?: '—' }}
                @if ($order->receipt_path)
                    <a href="{{ Storage::url($order->receipt_path) }}" target="_blank" class="ml-2 font-semibold text-amber-700">Dekontu aç</a>
                @endif
            </p>
        @endif

        @if ($order->payments->isEmpty())
            <p class="mt-3 text-sm text-slate-500">Bu siparişe bağlı tahsilat yok.</p>
        @else
            <div class="mt-4 space-y-3">
                @foreach ($order->payments as $payment)
                    <article class="rounded-xl border border-slate-200 p-4">
                        <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <dt class="text-slate-500">Ödeme tarihi</dt>
                                <dd class="font-medium text-slate-900">{{ $payment->payment_date?->format('d.m.Y') ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Tutar</dt>
                                <dd class="font-medium text-slate-900">{{ number_format((float) $payment->amount, 2, ',', '.') }} ₺</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Yöntem</dt>
                                <dd class="font-medium text-slate-900">{{ $paymentMethodLabel($payment->payment_method) }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-slate-500">Referans</dt>
                                <dd class="font-medium text-slate-900">{{ $payment->reference_code ?: '—' }}</dd>
                            </div>
                        </dl>
                        @if ($payment->notes)
                            <p class="mt-3 text-sm text-slate-600">{{ $payment->notes }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    @if ($order->isPending() && $payer)
        <section class="mt-6">
            @include('admin.managers.partials.approve', ['manager' => $payer, 'subscription' => $order])
        </section>
    @endif
@endsection
