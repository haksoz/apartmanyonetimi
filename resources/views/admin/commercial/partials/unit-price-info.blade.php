@php
    $formatUnitPrice = fn ($units) => $price === null || $units < 1
        ? '—'
        : number_format((float) $price / ($band->per_unit ? 1 : $units), 2, ',', '.').' ₺ / daire';
@endphp
<div class="mt-2 space-y-1 text-xs text-slate-500"
     data-unit-price-info data-min="{{ $band->min_units }}" data-max="{{ $band->max_units }}" data-per-unit="{{ $band->per_unit ? '1' : '0' }}">
    <p>Alt sınır ({{ $band->min_units }} daire): <span data-unit-price-min class="font-medium text-slate-700 tabular-nums">{{ $formatUnitPrice($band->min_units) }}</span></p>
    @if ($band->max_units !== null)
        <p>Üst sınır ({{ $band->max_units }} daire): <span data-unit-price-max class="font-medium text-slate-700 tabular-nums">{{ $formatUnitPrice($band->max_units) }}</span></p>
    @else
        <p>Üst sınır yok; daire başı fiyat sabittir.</p>
    @endif
</div>
