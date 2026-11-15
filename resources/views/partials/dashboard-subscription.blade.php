<aside aria-label="Apartman aboneliği" class="mt-2 text-xs text-slate-600">
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
        <span class="font-semibold">{{ match ($summary['state']) { 'paid' => 'AidatCep Ücretli Kullanım', 'expired' => 'Ücretli kullanım süreniz doldu', default => 'AidatCep Ücretsiz Kullanım' } }}</span>
        @if ($summary['state'] !== 'expired')
            <span class="font-medium text-emerald-700">Aktif</span>
        @endif
        @if ($summary['state'] === 'paid')
            <span>· {{ $summary['end'] ? 'Bitiş: '.$summary['end']->format('d.m.Y') : 'Süresiz' }}</span>
            @if ($summary['days'] !== null)
                <span class="font-semibold">· {{ $summary['days'] === 0 ? 'Bugün sona eriyor' : $summary['days'].' gün kaldı' }}</span>
            @endif
        @endif
        <a href="{{ $summary['details_url'] }}" class="font-semibold underline underline-offset-2">Abonelik detayları</a>
    </div>
    @if ($summary['pending'])
        <div class="mt-1.5 flex flex-wrap items-center gap-2">
            <span>Yenileme siparişiniz ödeme / onay bekliyor.</span>
            @if ($summary['pending_url'])
                <a href="{{ $summary['pending_url'] }}" class="font-semibold underline underline-offset-2">Siparişi görüntüle</a>
            @endif
        </div>
    @elseif ($summary['expiring'] || $summary['state'] === 'expired')
        <a href="{{ $summary['purchase_url'] }}" class="mt-1.5 inline-flex min-h-9 items-center rounded-lg bg-amber-500 px-3 py-1.5 font-semibold text-slate-950 hover:bg-amber-400">{{ $summary['state'] === 'expired' ? 'Ücretli kullanımı yenile' : 'Süreyi uzat' }}</a>
    @endif
</aside>
