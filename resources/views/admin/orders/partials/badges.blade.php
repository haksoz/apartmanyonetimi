@php
    $orderStatusLabel = match ($order->status) {
        \App\Models\UserSubscription::STATUS_PENDING => 'Bekliyor',
        \App\Models\UserSubscription::STATUS_ACTIVE => 'Onaylandı',
        \App\Models\UserSubscription::STATUS_CANCELLED => 'İptal',
        default => $order->status ?: '—',
    };
    $paymentStatusLabel = match (true) {
        $order->payments->isNotEmpty() => 'Tahsil edildi',
        $order->status === \App\Models\UserSubscription::STATUS_PENDING && ($order->receipt_path || $order->receipt_reference) => 'Onay bekliyor',
        $order->status === \App\Models\UserSubscription::STATUS_PENDING => 'Ödeme bekliyor',
        $order->status === \App\Models\UserSubscription::STATUS_CANCELLED => 'Ödenmedi',
        default => '—',
    };
    $orderBadge = match ($order->status) {
        \App\Models\UserSubscription::STATUS_PENDING => 'bg-amber-100 text-amber-800',
        \App\Models\UserSubscription::STATUS_ACTIVE => 'bg-emerald-100 text-emerald-700',
        \App\Models\UserSubscription::STATUS_CANCELLED => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-600',
    };
    $paymentBadge = match ($paymentStatusLabel) {
        'Tahsil edildi' => 'bg-emerald-100 text-emerald-700',
        'Onay bekliyor', 'Ödeme bekliyor' => 'bg-amber-100 text-amber-800',
        'Ödenmedi' => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp
<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $orderBadge }}">Sipariş: {{ $orderStatusLabel }}</span>
<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $paymentBadge }}">Ödeme: {{ $paymentStatusLabel }}</span>
