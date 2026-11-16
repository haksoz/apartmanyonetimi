<aside aria-label="Apartman aboneliği" class="mt-2 text-xs text-slate-600">
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
        <span class="font-semibold">{{ $summary['state'] === 'paid' ? 'AidatCep Ücretli Kullanım' : 'AidatCep Ücretsiz Kullanım' }}</span>
        <span class="font-medium text-emerald-700">Aktif</span>
        @if ($summary['state'] === 'paid')
            <span>· {{ $summary['end'] ? 'Bitiş: '.$summary['end']->format('d.m.Y') : 'Süresiz' }}</span>
            @if ($summary['days'] !== null)
                <span class="font-semibold">· {{ $summary['days'] === 0 ? 'Bugün sona eriyor' : $summary['days'].' gün kaldı' }}</span>
            @endif
        @endif
        <a href="{{ $summary['details_url'] }}" class="font-semibold underline underline-offset-2">Abonelik detayları</a>
    </div>
    @if ($summary['state'] === 'expired')
        <p class="mt-1.5 font-medium text-amber-700">Ücretli kullanım süreniz doldu.</p>
    @endif
    @if ($summary['pending'])
        <div class="mt-1.5 flex flex-wrap items-center gap-2">
            <span>{{ $summary['pending_message'] }}</span>
            @if ($summary['pending_url'])
                <a href="{{ $summary['pending_url'] }}" class="font-semibold underline underline-offset-2">{{ $summary['pending_link_label'] }}</a>
            @endif
        </div>
    @elseif ($summary['expiring'])
        <a href="{{ $summary['purchase_url'] }}" class="mt-1.5 inline-flex min-h-9 items-center rounded-lg bg-amber-500 px-3 py-1.5 font-semibold text-slate-950 hover:bg-amber-400">Süreyi uzat</a>
    @endif
</aside>
