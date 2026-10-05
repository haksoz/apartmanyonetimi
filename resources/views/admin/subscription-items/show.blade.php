@extends('layouts.app')

@section('title', 'Abonelik Detayı')

@section('content')
    @php
        $subscription = $item->subscription;
        $payer = $period->subscription?->user ?? $subscription?->user;
        $owners = $item->apartment?->members ?? collect();
        $itemPlanLabel = match ($item->plan) {
            \App\Models\SubscriptionItem::PLAN_PAID => 'Ücretli',
            \App\Models\SubscriptionItem::PLAN_FREE => 'Ücretsiz',
            default => '—',
        };
        $planLabel = match ($period->plan) {
            \App\Models\SubscriptionItem::PLAN_PAID => 'Ücretli',
            \App\Models\SubscriptionItem::PLAN_FREE => 'Ücretsiz',
            default => '—',
        };
        $statusLabel = match ($item->status) {
            \App\Models\SubscriptionItem::STATUS_PENDING => 'Bekliyor',
            \App\Models\SubscriptionItem::STATUS_ACTIVE => 'Aktif',
            \App\Models\SubscriptionItem::STATUS_CANCELLED => 'İptal',
            default => '—',
        };
        $orderStatus = match ($subscription?->status) {
            \App\Models\UserSubscription::STATUS_PENDING => 'Bekliyor',
            \App\Models\UserSubscription::STATUS_ACTIVE => 'Aktif',
            \App\Models\UserSubscription::STATUS_CANCELLED => 'İptal',
            default => $subscription?->status ?: '—',
        };
        $paymentMethod = match ($subscription?->payment_method) {
            'kredi_kartı' => 'Kredi kartı',
            'nakit' => 'Nakit',
            'havale' => 'Havale / EFT',
            default => $subscription?->payment_method ?: '—',
        };
        $subscriptionStatus = match ($record?->status) {
            \App\Models\Subscription::STATUS_ACTIVE => 'Aktif',
            \App\Models\Subscription::STATUS_ENDED => 'Sona erdi',
            default => $record ? '—' : $statusLabel,
        };
        $periodText = $period->started_at
            ? $period->started_at->format('d.m.Y').' - '.($period->expires_at?->format('d.m.Y') ?? 'süresiz')
            : '—';
    @endphp

    <div class="mb-6">
        <a href="{{ route('admin.managers.index', ['view' => 'items']) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Aboneliklere dön</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Abonelik Detayı</h1>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Abonelik No</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $record?->subscription_no ?? '—' }}</p>
            </div>
            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">{{ $subscriptionStatus }}</span>
        </div>
        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-slate-500">Apartman</dt>
                <dd class="font-medium text-slate-900">{{ $item->apartment?->name ?? $item->apartment_name }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Güncel yönetici</dt>
                <dd class="font-medium text-slate-900">{{ $owners->pluck('name')->join(', ') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Ödeyen</dt>
                <dd class="font-medium text-slate-900">{{ $payer?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Abonelik başlangıcı</dt>
                <dd class="font-medium text-slate-900">{{ $record?->started_at?->format('d.m.Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Mevcut dönem</dt>
                <dd class="font-medium text-slate-900">{{ $periodText }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Plan</dt>
                <dd class="font-medium text-slate-900">{{ $planLabel }}</dd>
            </div>
        </dl>

        <div class="mt-6 flex flex-wrap items-center gap-2">
            @if ($item->apartment)
                <a href="{{ route('apartments.show', $item->apartment) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apartman detayına git</a>
            @endif
            @if ($payer)
                <a href="{{ route('admin.managers.show', $payer) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Sipariş detayına git</a>
            @endif
        </div>

        @if ($subscription && $payer && $subscription->isPending())
            <div class="mt-4">
                @include('admin.managers.partials.approve', ['manager' => $payer, 'subscription' => $subscription])
            </div>
        @endif
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">Dönem</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Apartman</dt>
                    <dd class="font-medium text-slate-900">{{ $item->apartment?->name ?? $item->apartment_name }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Plan</dt>
                    <dd class="font-medium text-slate-900">{{ $itemPlanLabel }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Durum</dt>
                    <dd class="font-medium text-slate-900">{{ $statusLabel }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Tutar</dt>
                    <dd class="font-medium text-slate-900">{{ number_format($item->amount, 0, ',', '.') }} ₺</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Başlangıç</dt>
                    <dd class="font-medium text-slate-900">{{ $item->started_at?->format('d.m.Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Bitiş</dt>
                    <dd class="font-medium text-slate-900">{{ $item->expires_at?->format('d.m.Y') ?? '—' }}</dd>
                </div>
                @if ($item->ended_at)
                    <div>
                        <dt class="text-slate-500">Kapanış</dt>
                        <dd class="font-medium text-slate-900">{{ $item->ended_at->format('d.m.Y H:i') }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">Apartman</h2>
            <dl class="mt-4 grid gap-3 text-sm">
                <div>
                    <dt class="text-slate-500">Apartman adı</dt>
                    <dd class="font-medium text-slate-900">{{ $item->apartment?->name ?? $item->apartment_name }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Güncel yönetici</dt>
                    <dd class="font-medium text-slate-900">{{ $owners->pluck('name')->join(', ') ?: '—' }}</dd>
                </div>
                @if ($item->apartment)
                    <div>
                        <dt class="text-slate-500">Apartman no</dt>
                        <dd class="font-medium text-slate-900">{{ $item->apartment->id }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">Ödeyen</h2>
            <dl class="mt-4 grid gap-3 text-sm">
                <div>
                    <dt class="text-slate-500">Ad soyad</dt>
                    <dd class="font-medium text-slate-900">{{ $payer?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">E-posta</dt>
                    <dd class="font-medium text-slate-900">{{ $payer?->email ?? '—' }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2">
            <h2 class="text-lg font-semibold text-slate-900">Sipariş / Ödeme</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-slate-500">Sipariş numarası</dt>
                    <dd class="font-medium text-slate-900">{{ $subscription?->order_number ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Sipariş tarihi</dt>
                    <dd class="font-medium text-slate-900">{{ $subscription?->created_at?->format('d.m.Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Sipariş toplamı</dt>
                    <dd class="font-medium text-slate-900">{{ $subscription ? number_format($subscription->price, 2, ',', '.').' ₺' : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Sipariş durumu</dt>
                    <dd class="font-medium text-slate-900">{{ $orderStatus }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Ödeme yöntemi</dt>
                    <dd class="font-medium text-slate-900">{{ $paymentMethod }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Referans</dt>
                    <dd class="font-medium text-slate-900">
                        {{ $subscription?->receipt_reference ?: '—' }}
                        @if ($subscription?->receipt_path)
                            <a href="{{ Storage::url($subscription->receipt_path) }}" target="_blank" class="ml-2 font-semibold text-emerald-700">Dekontu aç</a>
                        @endif
                    </dd>
                </div>
            </dl>

            <h3 class="mt-6 text-sm font-semibold text-slate-900">Tahsilatlar</h3>
            @if (! $subscription || $subscription->payments->isEmpty())
                <p class="mt-2 text-sm text-slate-500">Bu siparişe bağlı tahsilat yok.</p>
            @else
                <table class="mt-3 min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Tarih</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-700">Tutar</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Yöntem</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Referans</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($subscription->payments as $payment)
                            <tr>
                                <td class="px-3 py-2">{{ $payment->payment_date?->format('d.m.Y') ?? '—' }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($payment->amount, 2, ',', '.') }} ₺</td>
                                <td class="px-3 py-2">{{ $payment->payment_method ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $payment->reference_code ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>
@endsection
