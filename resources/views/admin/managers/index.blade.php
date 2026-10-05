@extends('layouts.app')

@section('title', 'Abonelikler')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Abonelikler</h1>
            <p class="text-sm text-slate-500">Satın alma ödeyen müşteriye aittir. Hizmetin konusu apartmandır.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.managers.index') }}" class="flex gap-3">
            <input type="hidden" name="view" value="items">
            <input type="text" name="search" value="{{ $search }}" placeholder="Ad, e-posta, apartman veya sipariş no" class="w-full max-w-md rounded-xl border border-slate-300 px-4 py-2 text-sm">
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ara</button>
            @if ($search)
                <a href="{{ route('admin.managers.index', ['view' => 'items']) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Sıfırla</a>
            @endif
        </form>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Apartman</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Abonelik No</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Güncel yönetici</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Ödeyen</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Plan</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Başlangıç</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Bitiş</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Tutar</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Sipariş no</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Detay</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($items as $item)
                    @php
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
                    @endphp
                    <tr class="hover:bg-slate-50 align-top">
                        <td class="px-4 py-3 font-medium text-slate-900">
                            <a href="{{ route('admin.subscription-items.show', $item) }}" class="hover:text-emerald-700">{{ $item->apartment?->name ?? $item->apartment_name }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $item->apartmentSubscription?->subscription_no ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $owners->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($payer)
                                <a href="{{ route('admin.managers.show', $payer) }}" class="font-medium text-slate-900 hover:text-emerald-700">{{ $payer->name }}</a>
                                <div class="text-xs text-slate-500">{{ $payer->email }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $planLabel }}</td>
                        <td class="px-4 py-3">{{ $item->started_at?->format('d.m.Y') ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $item->expires_at?->format('d.m.Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($item->amount, 0, ',', '.') }} ₺</td>
                        <td class="px-4 py-3">
                            @if ($item->status === \App\Models\SubscriptionItem::STATUS_ACTIVE)
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ $statusLabel }}</span>
                            @elseif ($item->status === \App\Models\SubscriptionItem::STATUS_PENDING)
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $statusLabel }}</span>
                            @elseif ($item->status === \App\Models\SubscriptionItem::STATUS_CANCELLED)
                                <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">{{ $statusLabel }}</span>
                            @else
                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $statusLabel }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $item->subscription?->order_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.subscription-items.show', $item) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Detay</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-slate-500">Abonelik kalemi yok.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $items->links() }}</div>
@endsection
