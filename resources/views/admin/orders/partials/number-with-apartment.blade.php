@php
    $orderApartmentNames = $order->items
        ->map(fn ($item) => $item->apartment?->name ?: $item->apartment_name)
        ->filter()->unique()->join(', ');
@endphp
{{ $order->order_number ?: '—' }}@if ($orderApartmentNames !== '') <span class="font-sans text-sm font-normal text-slate-600">({{ $orderApartmentNames }})</span>@endif
