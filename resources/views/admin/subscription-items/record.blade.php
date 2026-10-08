@php
    $currentOrder = $period?->subscription;
    $payer = $currentOrder?->user;
    $owners = $record->apartment?->members ?? $period?->apartment?->members ?? collect();
    $apartmentName = $record->apartment?->name
        ?? $period?->apartment?->name
        ?? $period?->apartment_name
        ?? '—';
    $planLabel = match ($period?->plan) {
        \App\Models\SubscriptionItem::PLAN_PAID => 'Ücretli',
        \App\Models\SubscriptionItem::PLAN_FREE => 'Ücretsiz',
        default => '—',
    };
    $subscriptionStatus = match ($record->status) {
        \App\Models\Subscription::STATUS_ACTIVE => 'Aktif',
        \App\Models\Subscription::STATUS_ENDED => 'Sona erdi',
        default => '—',
    };
    $planOf = function ($plan) {
        return match ($plan) {
            \App\Models\SubscriptionItem::PLAN_PAID => 'Ücretli',
            \App\Models\SubscriptionItem::PLAN_FREE => 'Ücretsiz',
            default => '—',
        };
    };
    $statusOf = function ($status) {
        return match ($status) {
            \App\Models\SubscriptionItem::STATUS_PENDING => 'Ödeme bekliyor',
            \App\Models\SubscriptionItem::STATUS_ACTIVE => 'Aktif',
            \App\Models\SubscriptionItem::STATUS_CANCELLED => 'İptal',
            default => $status ?: '—',
        };
    };
    $periodText = function ($row) {
        if (! $row?->started_at) {
            return '—';
        }

        return $row->started_at->format('d.m.Y').' - '.($row->expires_at?->format('d.m.Y') ?? 'süresiz');
    };
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
    <div class="hidden md:block">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Abonelik No</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $record->subscription_no ?: '—' }}</p>
            </div>
            @include('admin.managers.partials.status-badges', ['status' => $subscriptionStatus, 'pending' => false])
        </div>
        <dl class="mt-6 grid grid-cols-2 gap-x-8 gap-y-4 text-sm lg:grid-cols-3">
            <div>
                <dt class="text-slate-500">Apartman</dt>
                <dd class="mt-1 font-medium text-slate-900">{{ $apartmentName }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Güncel yönetici</dt>
                <dd class="mt-1 font-medium text-slate-900">{{ $owners->pluck('name')->join(', ') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Güncel ödeyen</dt>
                <dd class="mt-1 font-medium text-slate-900">
                    {{ $payer?->name ?? '—' }}
                    @if ($payer?->email)
                        <div class="font-normal text-slate-500">{{ $payer->email }}</div>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Başlangıç</dt>
                <dd class="mt-1 font-medium text-slate-900">{{ $record->started_at?->format('d.m.Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Mevcut dönem</dt>
                <dd class="mt-1 font-medium text-slate-900">{{ $periodText($period) }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Plan</dt>
                <dd class="mt-1">
                    @include('admin.managers.partials.plan-badge', ['plan' => $planLabel])
                </dd>
            </div>
        </dl>
        <div class="mt-6 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
            @if ($record->apartment ?? $period?->apartment)
                <a href="{{ route('apartments.show', $record->apartment ?? $period->apartment) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apartman detayına git</a>
            @endif
            @if ($currentOrder)
                <a href="{{ route('admin.orders.show', $currentOrder) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Sipariş detayına git</a>
            @endif
        </div>
    </div>

    <div class="md:hidden">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Abonelik No</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $record->subscription_no ?: '—' }}</p>
            </div>
            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">{{ $subscriptionStatus }}</span>
        </div>
        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-slate-500">Apartman</dt>
                <dd class="font-medium text-slate-900">{{ $apartmentName }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Güncel yönetici</dt>
                <dd class="font-medium text-slate-900">{{ $owners->pluck('name')->join(', ') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Güncel ödeyen</dt>
                <dd class="font-medium text-slate-900">
                    {{ $payer?->name ?? '—' }}
                    @if ($payer?->email)
                        <div class="text-slate-500">{{ $payer->email }}</div>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Abonelik başlangıcı</dt>
                <dd class="font-medium text-slate-900">{{ $record->started_at?->format('d.m.Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Mevcut dönem</dt>
                <dd class="font-medium text-slate-900">{{ $periodText($period) }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Plan</dt>
                <dd class="font-medium text-slate-900">{{ $planLabel }}</dd>
            </div>
        </dl>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            @if ($record->apartment ?? $period?->apartment)
                <a href="{{ route('apartments.show', $record->apartment ?? $period->apartment) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apartman detayına git</a>
            @endif
            @if ($currentOrder)
                <a href="{{ route('admin.orders.show', $currentOrder) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Sipariş detayına git</a>
            @endif
        </div>
    </div>

    @if ($currentOrder && $payer && $currentOrder->isPending())
        <div class="mt-4">
            @include('admin.managers.partials.approve', ['manager' => $payer, 'subscription' => $currentOrder])
        </div>
    @endif
</section>

<section class="rounded-xl border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-semibold text-slate-900">Dönem geçmişi</h2>
    @if ($history->isEmpty())
        <p class="mt-3 text-sm text-slate-500">Bu aboneliğin dönemi yok.</p>
    @else
        <div class="mt-4 hidden overflow-x-auto md:block">
            <table class="min-w-full text-sm">
                <thead class="text-left text-slate-500">
                    <tr>
                        <th class="px-3 py-2 font-medium">Dönem</th>
                        <th class="px-3 py-2 font-medium">Plan</th>
                        <th class="px-3 py-2 font-medium">Tutar</th>
                        <th class="px-3 py-2 font-medium">Durum</th>
                        <th class="px-3 py-2 font-medium">Sipariş</th>
                        <th class="px-3 py-2 font-medium">Ödeyen</th>
                        <th class="px-3 py-2 font-medium">Tahsilat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($history as $row)
                        @php
                            $order = $row->subscription;
                            $rowPayer = $order?->user;
                            $rowStatus = $statusOf($row->status);
                        @endphp
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-900">
                                {{ $periodText($row) }}
                                @if ($row->ended_at)
                                    <span class="font-normal text-slate-500">· Kapanış {{ $row->ended_at->format('d.m.Y H:i') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-slate-700">{{ $planOf($row->plan) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-slate-900">{{ number_format($row->amount, 0, ',', '.') }} ₺</td>
                            <td class="whitespace-nowrap px-3 py-2">
                                @if ($rowStatus === 'Aktif')
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ $rowStatus }}</span>
                                @elseif ($rowStatus === 'Ödeme bekliyor')
                                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $rowStatus }}</span>
                                @elseif ($rowStatus === 'İptal')
                                    <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">{{ $rowStatus }}</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $rowStatus }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                @if ($order)
                                    <a href="{{ route('admin.orders.show', $order) }}" class="font-mono font-medium text-emerald-700 hover:text-emerald-800">{{ $order->order_number ?: 'Sipariş' }}</a>
                                    @if ($order->billing_label)
                                        <div class="text-xs font-normal text-slate-500">{{ $order->billing_label }}</div>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-slate-700">{{ $rowPayer?->name ?: '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">
                                @if (! $order || $order->payments->isEmpty())
                                    —
                                @else
                                    @foreach ($order->payments as $payment)
                                        <div class="whitespace-nowrap">{{ $payment->payment_date?->format('d.m.Y') ?? '—' }} · {{ number_format($payment->amount, 2, ',', '.') }} ₺ · {{ $payment->payment_method ?: '—' }} · {{ $payment->reference_code ?: '—' }}</div>
                                    @endforeach
                                @endif
                            </td>
                        </tr>
                        @if ($order && $order->isPending() && $rowPayer && ! $currentOrder?->is($order))
                            <tr>
                                <td colspan="7" class="px-3 py-2">
                                    @include('admin.managers.partials.approve', ['manager' => $rowPayer, 'subscription' => $order])
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 space-y-3 md:hidden">
            @foreach ($history as $row)
                @php
                    $order = $row->subscription;
                    $rowPayer = $order?->user;
                @endphp
                <article class="rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-slate-900">{{ $periodText($row) }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $planOf($row->plan) }} · {{ number_format($row->amount, 0, ',', '.') }} ₺</p>
                        </div>
                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $statusOf($row->status) }}</span>
                    </div>
                    <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="text-slate-500">Sipariş no</dt>
                            <dd class="font-medium text-slate-900">
                                {{ $order?->order_number ?: '—' }}
                                @if ($order?->billing_label)
                                    <div class="text-xs font-normal text-slate-500">{{ $order->billing_label }}</div>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Ödeyen</dt>
                            <dd class="font-medium text-slate-900">{{ $rowPayer?->name ?: '—' }}</dd>
                        </div>
                        @if ($row->ended_at)
                            <div>
                                <dt class="text-slate-500">Kapanış</dt>
                                <dd class="font-medium text-slate-900">{{ $row->ended_at->format('d.m.Y H:i') }}</dd>
                            </div>
                        @endif
                    </dl>
                    @if ($order)
                        <div class="mt-3">
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">Sipariş detayına git</a>
                        </div>
                        @if ($order->payments->isEmpty())
                            <p class="mt-3 text-sm text-slate-500">Bu siparişe bağlı tahsilat yok.</p>
                        @else
                            <div class="mt-3 space-y-2">
                                @foreach ($order->payments as $payment)
                                    <p class="text-sm text-slate-700">
                                        {{ $payment->payment_date?->format('d.m.Y') ?? '—' }}
                                        · {{ number_format($payment->amount, 2, ',', '.') }} ₺
                                        · {{ $payment->payment_method ?: '—' }}
                                        · {{ $payment->reference_code ?: '—' }}
                                    </p>
                                @endforeach
                            </div>
                        @endif
                        @if ($order->isPending() && $rowPayer && ! $currentOrder?->is($order))
                            <div class="mt-3">
                                @include('admin.managers.partials.approve', ['manager' => $rowPayer, 'subscription' => $order])
                            </div>
                        @endif
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</section>
