@extends('layouts.app')

@section('title', 'Sipariş Detayı')

@section('content')
    @php
        $payer = $order->user;
        $linkedSubscription = $order->subscription;

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
        $summaryPeriod = $order->items->isEmpty() ? '—' : ($periods->count() === 1 ? $periods->first() : 'Birden fazla dönem');
        $summaryApartment = $apartmentNames->isNotEmpty()
            ? $apartmentNames->join(', ')
            : ($linkedSubscription?->apartment?->name ?: '—');
        $cycleLabel = match ($order->period) {
            \App\Models\UserSubscription::PERIOD_MONTHLY => 'Aylık',
            'yearly' => 'Yıllık',
            default => null,
        };
    @endphp

    <div class="mb-6">
        <a href="{{ route('admin.orders.index') }}" class="text-sm font-semibold text-amber-700 hover:text-amber-800">← Siparişlere dön</a>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sipariş no</p>
                <p class="mt-1 font-mono text-2xl font-bold text-slate-900">{{ $order->order_number ?: '—' }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ $order->created_at?->format('d.m.Y') ?? '—' }} · {{ number_format((float) $order->price, 2, ',', '.') }} ₺</p>
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
        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-slate-500">Apartman</dt>
                <dd class="font-medium text-slate-900">
                    @php
                        $cardApartment = $linkedSubscription?->apartment ?? ($order->items->count() === 1 ? $order->items->first()?->apartment : null);
                    @endphp
                    @if ($cardApartment)
                        <a href="{{ route('apartments.show', $cardApartment) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">{{ $cardApartment->name }}</a>
                    @else
                        {{ $summaryApartment }}
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Abonelik</dt>
                <dd class="font-medium text-slate-900">
                    @if ($linkedSubscription)
                        <a href="{{ route('admin.subscriptions.show', $linkedSubscription) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">{{ $linkedSubscription->subscription_no ?: 'Abonelik' }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Dönem</dt>
                <dd class="font-medium text-slate-900">{{ $summaryPeriod }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Plan</dt>
                <dd class="font-medium text-slate-900">
                    @if ($order->items->isEmpty())
                        Bu kayıtta sipariş kalemi yok.
                    @else
                        <div class="space-y-3">
                            @foreach ($order->items as $item)
                                @php
                                    $band = $item->band_label
                                        ?: (($item->band_min_units || $item->band_max_units)
                                            ? trim(($item->band_min_units ?: '1').'–'.($item->band_max_units ?: '∞')).' daire'
                                            : null);
                                @endphp
                                <div>
                                    @if ($order->items->count() > 1)
                                        <div>
                                            @if ($item->apartment)
                                                <a href="{{ route('apartments.show', $item->apartment) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">{{ $item->apartment->name }}</a>
                                            @else
                                                {{ $item->apartment_name ?: '—' }}
                                            @endif
                                            @if ($item->apartmentSubscription)
                                                · <a href="{{ route('admin.subscriptions.show', $item->apartmentSubscription) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">{{ $item->apartmentSubscription->subscription_no ?: 'Abonelik' }}</a>
                                            @endif
                                        </div>
                                    @endif
                                    <div>
                                        {{ $planLabel($item->plan) }}
                                        @if ($cycleLabel)
                                            · {{ $cycleLabel }}
                                        @endif
                                        @if ($item->unit_count)
                                            · {{ $item->unit_count }} daire
                                        @endif
                                    </div>
                                    @if ($band)
                                        <div class="font-normal text-slate-500">{{ $band }}</div>
                                    @endif
                                    <div class="font-normal text-slate-600">
                                        {{ number_format((float) $item->amount, 2, ',', '.') }} ₺
                                        · {{ $itemStatusLabel($item->status) }}
                                        · {{ $periodText($item) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Ödeyen</dt>
                <dd class="font-medium text-slate-900">
                    {{ $payer?->name ?: '—' }}
                    @if ($payer?->email)
                        <div class="font-normal text-slate-500">{{ $payer->email }}</div>
                    @endif
                    @if ($payer?->phone)
                        <div class="font-normal text-slate-500">{{ $payer->phone }}</div>
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    @if ($order->isPending() && $payer)
        <section class="mt-6">
            <h2 class="mb-3 text-lg font-semibold text-slate-900">Sipariş kararı</h2>
            @include('admin.managers.partials.approve', ['manager' => $payer, 'subscription' => $order, 'open' => true])
        </section>
    @endif

    <details class="mt-6 rounded-xl border border-slate-200 bg-white">
        <summary class="cursor-pointer px-6 py-4 text-sm font-semibold text-slate-900">Fatura bilgileri</summary>
        <div class="border-t border-slate-100 px-6 pb-2">
            @include('partials.billing-snapshot', ['subscription' => $order])
        </div>
    </details>

    <details class="mt-3 rounded-xl border border-slate-200 bg-white">
        <summary class="cursor-pointer px-6 py-4 text-sm font-semibold text-slate-900">Sözleşme kabulleri</summary>
        <div class="border-t border-slate-100 px-6 pb-2">
            @include('partials.order-legal-acceptances', ['subscription' => $order])
        </div>
    </details>

    <details class="mt-3 rounded-xl border border-slate-200 bg-white">
        <summary class="cursor-pointer px-6 py-4 text-sm font-semibold text-slate-900">Tahsilatlar</summary>
        <div class="border-t border-slate-100 px-6 pb-6">
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
        </div>
    </details>
@endsection
