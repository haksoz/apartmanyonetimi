@if (($summary['remaining_compensation'] ?? 0) > 0)
    <div class="mt-1 text-xs">
        @isset($summary['remaining_principal'])<div>Kalan ana para {{ number_format($summary['remaining_principal'] / 100, 2, ',', '.') }} TL</div>@endisset
        <div>Gecikme Tazminatı {{ number_format($summary['remaining_compensation'] / 100, 2, ',', '.') }} TL</div>
        <div class="font-semibold">Toplam ödenecek {{ number_format($summary['total'] / 100, 2, ',', '.') }} TL</div>
    </div>
@endif
