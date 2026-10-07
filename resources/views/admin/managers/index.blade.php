@extends('layouts.app')

@section('title', 'Abonelikler')

@section('content')
    @php
        $subscriptionRows = $subscriptions->getCollection()->map(function ($subscription) {
            $current = $subscription->currentItem();
            $payer = $current?->subscription?->user;
            $owners = $subscription->apartment?->members ?? collect();
            $planLabel = match ($current?->plan) {
                \App\Models\SubscriptionItem::PLAN_PAID => 'Ücretli',
                \App\Models\SubscriptionItem::PLAN_FREE => 'Ücretsiz',
                default => '—',
            };
            $statusLabel = match (true) {
                $current?->status === \App\Models\SubscriptionItem::STATUS_PENDING => 'Ödeme bekliyor',
                $subscription->status === \App\Models\Subscription::STATUS_ENDED => 'Sona erdi',
                $current?->status === \App\Models\SubscriptionItem::STATUS_ACTIVE => 'Aktif',
                $subscription->status === \App\Models\Subscription::STATUS_ACTIVE => 'Aktif',
                default => '—',
            };
            $pendingOrderItem = $subscription->items
                ->filter(fn ($item) => $item->status === \App\Models\SubscriptionItem::STATUS_PENDING
                    && $item->ended_at === null
                    && $item->subscription?->status === \App\Models\UserSubscription::STATUS_PENDING)
                ->sortByDesc('id')
                ->first();
            $started = $current?->started_at?->format('d.m.Y') ?? '—';
            $expires = $current?->expires_at?->format('d.m.Y') ?? '—';
            $period = match (true) {
                $started === '—' && $expires === '—' => '—',
                $expires === '—' => $started,
                $started === '—' => $expires,
                default => $started.' - '.$expires,
            };

            return [
                'apartment_name' => $subscription->apartment?->name
                    ?? $current?->apartment?->name
                    ?? $current?->apartment_name
                    ?? '—',
                'subscription_no' => $subscription->subscription_no ?: '—',
                'managers' => $owners->pluck('name')->join(', ') ?: '—',
                'payer_name' => $payer?->name,
                'payer_email' => $payer?->email,
                'payer_url' => $payer ? route('admin.managers.show', $payer) : null,
                'plan' => $planLabel,
                'period' => $period,
                'status' => $statusLabel,
                'pending' => (bool) ($pendingOrderItem && $statusLabel !== 'Ödeme bekliyor'),
                'detail_url' => route('admin.subscriptions.show', $subscription),
            ];
        });

        $legacyRows = $legacyItems->getCollection()->map(function ($item) {
            $payer = $item->subscription?->user;
            $owners = $item->apartment?->members ?? collect();
            $planLabel = match ($item->plan) {
                \App\Models\SubscriptionItem::PLAN_PAID => 'Ücretli',
                \App\Models\SubscriptionItem::PLAN_FREE => 'Ücretsiz',
                default => '—',
            };
            $statusLabel = match ($item->status) {
                \App\Models\SubscriptionItem::STATUS_PENDING => 'Ödeme bekliyor',
                \App\Models\SubscriptionItem::STATUS_ACTIVE => 'Aktif',
                \App\Models\SubscriptionItem::STATUS_CANCELLED => 'İptal',
                default => '—',
            };
            $started = $item->started_at?->format('d.m.Y') ?? '—';
            $expires = $item->expires_at?->format('d.m.Y') ?? '—';
            $period = match (true) {
                $started === '—' && $expires === '—' => '—',
                $expires === '—' => $started,
                $started === '—' => $expires,
                default => $started.' - '.$expires,
            };

            return [
                'apartment_name' => $item->apartment?->name ?? $item->apartment_name,
                'subscription_no' => '—',
                'managers' => $owners->pluck('name')->join(', ') ?: '—',
                'payer_name' => $payer?->name,
                'payer_email' => $payer?->email,
                'payer_url' => $payer ? route('admin.managers.show', $payer) : null,
                'plan' => $planLabel,
                'period' => $period,
                'status' => $statusLabel,
                'pending' => false,
                'order_number' => $item->subscription?->order_number ?? '—',
                'detail_url' => route('admin.subscription-items.show', $item),
            ];
        });
    @endphp

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Abonelikler</h1>
            <p class="text-sm text-slate-500">Her apartmanın kalıcı aboneliği. Dönem geçmişi detayda tutulur.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4">
        <form method="GET" action="{{ route('admin.managers.index') }}" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="view" value="items">
            <input type="text" name="search" value="{{ $search }}" placeholder="Apartman, abonelik no, yönetici veya ödeyen" class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm sm:w-80">
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ara</button>
            @if (filled($search))
                <a href="{{ route('admin.managers.index', ['view' => 'items']) }}" class="text-sm font-semibold text-slate-600 hover:text-slate-800">Temizle</a>
            @endif
        </form>
    </div>

    <div class="space-y-2 md:hidden">
        @forelse ($subscriptionRows as $row)
            <article class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                <div class="flex flex-wrap items-baseline gap-2">
                    <p class="font-semibold text-slate-900">{{ $row['apartment_name'] }}</p>
                    <p class="text-sm text-slate-600">{{ $row['subscription_no'] }}</p>
                    <p class="text-sm text-slate-600">{{ $row['managers'] }}</p>
                </div>
                <p class="mt-1 text-sm text-slate-600">
                    {{ $row['payer_name'] ?: '—' }}
                    <span class="text-slate-300"> · </span>
                    {{ $row['period'] }}
                    <span class="text-slate-300"> · </span>
                    <a href="{{ $row['detail_url'] }}" class="font-semibold text-emerald-700 hover:text-emerald-800">Detay</a>
                </p>
                <div class="mt-1 flex flex-wrap items-center gap-1">
                    @include('admin.managers.partials.plan-badge', ['plan' => $row['plan']])
                    @include('admin.managers.partials.status-badges', ['status' => $row['status'], 'pending' => $row['pending']])
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">Abonelik yok.</div>
        @endforelse
    </div>

    <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Apartman / Abonelik No</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Güncel yönetici</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Güncel ödeyen</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Plan</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Başlangıç / Bitiş</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($subscriptionRows as $row)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $row['apartment_name'] }}</div>
                            <div class="text-xs text-slate-500">{{ $row['subscription_no'] }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $row['managers'] }}</td>
                        <td class="px-4 py-3">
                            @if ($row['payer_name'])
                                <a href="{{ $row['payer_url'] }}" class="font-medium text-slate-900 hover:text-emerald-700">{{ $row['payer_name'] }}</a>
                                <div class="text-xs text-slate-500">{{ $row['payer_email'] }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @include('admin.managers.partials.plan-badge', ['plan' => $row['plan']])
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $row['period'] }}</td>
                        <td class="px-4 py-3">
                            @include('admin.managers.partials.status-badges', ['status' => $row['status'], 'pending' => $row['pending']])
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ $row['detail_url'] }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Detay</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">Abonelik yok.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $subscriptions->links() }}</div>

    @if ($legacyItems->isNotEmpty())
        <h2 class="mt-10 text-lg font-semibold text-slate-900">Eski kayıtlar</h2>
        <p class="mt-1 text-sm text-slate-500">Kalıcı aboneliğe bağlı olmayan eski dönemler. Yeni abonelik gibi değerlendirilmez.</p>

        <div class="mt-4 space-y-2 md:hidden">
            @foreach ($legacyRows as $row)
                <article class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <p class="font-semibold text-slate-900">{{ $row['apartment_name'] }}</p>
                        <p class="text-sm text-slate-600">{{ $row['subscription_no'] }}</p>
                        <p class="text-sm text-slate-600">{{ $row['managers'] }}</p>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $row['payer_name'] ?: '—' }}
                        <span class="text-slate-300"> · </span>
                        {{ $row['period'] }}
                        <span class="text-slate-300"> · </span>
                        {{ $row['order_number'] }}
                        <span class="text-slate-300"> · </span>
                        <a href="{{ $row['detail_url'] }}" class="font-semibold text-emerald-700 hover:text-emerald-800">Detay</a>
                    </p>
                    <div class="mt-1 flex flex-wrap items-center gap-1">
                        @include('admin.managers.partials.plan-badge', ['plan' => $row['plan']])
                        @include('admin.managers.partials.status-badges', ['status' => $row['status'], 'pending' => false])
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-4 hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Apartman / Abonelik No</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Güncel yönetici</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Ödeyen</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Plan</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Başlangıç / Bitiş</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Sipariş no</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($legacyRows as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $row['apartment_name'] }}</div>
                                <div class="text-xs text-slate-500">{{ $row['subscription_no'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $row['managers'] }}</td>
                            <td class="px-4 py-3">
                                @if ($row['payer_name'])
                                    <a href="{{ $row['payer_url'] }}" class="font-medium text-slate-900 hover:text-emerald-700">{{ $row['payer_name'] }}</a>
                                    <div class="text-xs text-slate-500">{{ $row['payer_email'] }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @include('admin.managers.partials.plan-badge', ['plan' => $row['plan']])
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $row['period'] }}</td>
                            <td class="px-4 py-3">
                                @include('admin.managers.partials.status-badges', ['status' => $row['status'], 'pending' => false])
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $row['order_number'] }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ $row['detail_url'] }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Detay</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $legacyItems->links() }}</div>
    @endif
@endsection
