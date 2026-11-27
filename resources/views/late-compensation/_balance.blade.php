@if (($summary['accrued_compensation'] ?? 0) > 0 || ($summary['paid_compensation'] ?? 0) > 0)
    <dl class="mt-3 grid gap-2 rounded-xl border border-slate-200 bg-white p-3 text-sm sm:grid-cols-3">
        <div><dt>Kalan ana para</dt><dd class="font-semibold">{{ number_format($summary['remaining_principal'] / 100, 2, ',', '.') }} TL</dd></div>
        <div><dt>Birikmiş Gecikme Tazminatı</dt><dd>{{ number_format($summary['accrued_compensation'] / 100, 2, ',', '.') }} TL</dd></div>
        <div><dt>Tahsil edilmiş Gecikme Tazminatı</dt><dd>{{ number_format($summary['paid_compensation'] / 100, 2, ',', '.') }} TL</dd></div>
        <div><dt>Kalan Gecikme Tazminatı</dt><dd>{{ number_format($summary['remaining_compensation'] / 100, 2, ',', '.') }} TL</dd></div>
        <div><dt>Toplam ödenecek</dt><dd class="font-semibold">{{ number_format($summary['total'] / 100, 2, ',', '.') }} TL</dd></div>
    </dl>
@endif
