@extends('layouts.app')

@section('title', 'Apartman Detayı')

@section('content')
    <a href="{{ route('admin.apartments.index') }}" class="text-sm font-semibold text-emerald-700">← Apartmanlara dön</a>
    <div class="mb-5 mt-2 flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-slate-900">{{ $apartment->name }}</h1><p class="mt-1 text-sm text-slate-500">Apartman kodu {{ $apartment->code }} · ID {{ $apartment->id }}</p></div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">{{ $apartment->trashed() ? 'Silinmiş kayıt' : ($apartment->is_active ? 'Aktif' : 'Pasif') }}</span>
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="font-semibold text-slate-900">Apartman kimliği</h2>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                <div class="sm:col-span-2"><dt class="text-slate-500">Adres</dt><dd>{{ $apartment->address ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">İlçe / İl</dt><dd>{{ collect([$apartment->district, $apartment->province])->filter()->implode(' / ') ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Daire sayısı</dt><dd>{{ $apartment->unit_count }}</dd></div>
                <div><dt class="text-slate-500">Kurucu</dt><dd>@include('admin.apartments.partials.person', ['person' => $apartment->user])</dd></div>
                <div><dt class="text-slate-500">Kayıt tarihi</dt><dd>{{ $apartment->created_at?->format('d.m.Y') ?? '—' }}</dd></div>
            </dl>
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="font-semibold text-slate-900">Güncel yöneticiler</h2>
            <div class="mt-3 space-y-3 text-sm">
                @forelse ($apartment->managers as $manager)
                    <div>@include('admin.apartments.partials.person', ['person' => $manager])<div class="text-slate-500">{{ $manager->email }}@if ($manager->deactivated_at) · Hesap pasif @endif</div><p class="mt-1 text-xs text-slate-500">Yönetim başlangıcı: {{ $currentManagement->get($manager->id)?->started_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? 'Başlangıç kaydı yok' }}</p></div>
                @empty
                    <p class="text-slate-500">Bu apartmanda aktif yönetici üyeliği yok.</p>
                @endforelse
            </div>
        </section>
    </div>
    <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold text-slate-900">Abonelikler</h2>
        <div class="mt-3 space-y-3">
            @forelse ($subscriptions as $subscription)
                @php
                    $period = $subscription->currentItem();
                    $plan = match ($period?->plan) {
                        \App\Models\SubscriptionItem::PLAN_PAID => 'Ücretli',
                        \App\Models\SubscriptionItem::PLAN_FREE => 'Ücretsiz',
                        default => 'Güncel dönem yok',
                    };
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 p-3 text-sm">
                    <div><a href="{{ route('admin.subscriptions.show', $subscription) }}" class="font-mono font-semibold text-emerald-700">{{ $subscription->subscription_no }}</a><span class="ml-2 text-slate-500">{{ $subscription->status === \App\Models\Subscription::STATUS_ACTIVE ? 'Aktif' : 'Sona erdi' }} · {{ $plan }}</span></div>
                    <span class="text-xs text-slate-500">{{ $period?->started_at?->format('d.m.Y') ?? '—' }} – {{ $period ? ($period->expires_at?->format('d.m.Y') ?? 'süresiz') : '—' }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">Abonelik kaydı yok.</p>
            @endforelse
        </div>
    </section>
    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <h2 class="px-5 py-4 font-semibold text-slate-900">Sipariş ve fatura geçmişi</h2>
        <div class="overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="px-4 py-3">Sipariş</th><th class="px-4 py-3">Sipariş müşterisi</th><th class="px-4 py-3">KDV dahil tutar</th><th class="px-4 py-3">Durum</th><th class="px-4 py-3">Fatura takibi</th><th class="px-4 py-3">Tarih</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('admin.orders.show', $order) }}" class="font-mono font-semibold text-emerald-700">{{ $order->order_number ?: '—' }}</a></td>
                        <td class="px-4 py-3">@include('admin.apartments.partials.person', ['person' => $order->user])</td>
                        <td class="whitespace-nowrap px-4 py-3 tabular-nums">{{ number_format((float) $order->price, 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3">{{ $order->trashed() ? 'Pasif silindi' : match ($order->status) { 'active' => 'Onaylandı', 'pending' => 'Bekliyor', 'cancelled' => 'İptal', default => $order->status } }}</td>
                        <td class="px-4 py-3">@if ($order->invoice)<a href="{{ route('admin.order-invoices.show', $order->invoice) }}" class="font-semibold text-emerald-700">{{ $order->invoice->trackingNumber() }}</a><div class="mt-1 text-xs text-slate-500">{{ $order->invoice->invoice_number ?: 'XML bekleniyor' }}</div>@else<span class="text-slate-400">—</span>@endif</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $order->created_at?->format('d.m.Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Sipariş kaydı yok.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="px-4 py-3">{{ $orders->links() }}</div>
    </section>
    <section id="apartment-management-history" class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <h2 class="px-5 py-4 font-semibold text-slate-900">Yönetim geçmişi</h2>
        <div class="overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr>
                <th class="px-4 py-3">Yönetici</th><th class="px-4 py-3">Başlangıç</th><th class="px-4 py-3">Bitiş</th><th class="px-4 py-3">Yönetim süresi</th><th class="px-4 py-3">Durum</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($managementPeriods as $managementPeriod)
                    <tr>
                        <td class="px-4 py-3">@if ($managementPeriod->user) @include('admin.apartments.partials.person', ['person' => $managementPeriod->user]) @else {{ $managementPeriod->manager_name }} @endif</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $managementPeriod->started_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? 'Başlangıç kaydı yok' }}@if ($managementPeriod->start_source === 'initial_membership')<div class="mt-1 text-xs text-slate-500">İlk üyelik kaydına göre</div>@endif</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $managementPeriod->ended_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? ($managementPeriod->is_current ? 'Devam ediyor' : 'Bitiş kaydı yok') }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $managementPeriod->durationLabel() }}</td>
                        <td class="px-4 py-3"><span class="{{ $managementPeriod->is_current ? 'font-semibold text-emerald-700' : 'text-slate-500' }}">{{ $managementPeriod->is_current ? 'Güncel yönetici' : 'Eski yönetici' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Yönetim geçmişi kaydı yok.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="px-4 py-3">{{ $managementPeriods->links() }}</div>
    </section>
@endsection
