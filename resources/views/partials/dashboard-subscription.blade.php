<aside aria-label="Apartman aboneliği" class="mt-3 space-y-3 text-sm">
    <div @class([
        'rounded-xl border border-l-4 p-4 shadow-sm',
        'border-emerald-200 border-l-emerald-500 bg-emerald-50 text-emerald-950' => $summary['state'] === 'free' || ($summary['state'] === 'paid' && ! $summary['expiring']),
        'border-amber-200 border-l-amber-500 bg-amber-50 text-amber-950' => $summary['expiring'],
        'border-rose-200 border-l-rose-500 bg-rose-50 text-rose-950' => $summary['state'] === 'expired',
    ])>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold">{{ $summary['state'] === 'paid' ? 'AidatCep Ücretli Kullanım' : 'AidatCep Ücretsiz Kullanım' }}</span>
                    <span class="inline-flex rounded-full border border-emerald-200 bg-white px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Aktif</span>
                </div>
                @if ($summary['state'] === 'paid')
                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                        <span>{{ $summary['end'] ? 'Bitiş: '.$summary['end']->format('d.m.Y') : 'Süresiz' }}</span>
                        @if ($summary['days'] !== null)
                            <span class="font-semibold">{{ $summary['days'] === 0 ? 'Bugün sona eriyor' : $summary['days'].' gün kaldı' }}</span>
                        @endif
                    </div>
                @endif
                @if ($summary['state'] === 'expired')
                    <p class="mt-2 text-xs font-semibold text-rose-800">Ücretli kullanım süreniz doldu.</p>
                @endif
            </div>
            <a href="{{ $summary['details_url'] }}" class="inline-flex min-h-9 shrink-0 items-center justify-center rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:border-emerald-500 hover:bg-emerald-50">Abonelik detayları</a>
        </div>
    </div>
    @if ($summary['pending'])
        <div @class([
            'rounded-xl border border-l-4 p-4 shadow-sm',
            'border-amber-300 border-l-amber-500 bg-amber-50 text-amber-950' => ! $summary['pending_approval'],
            'border-emerald-300 border-l-emerald-500 bg-emerald-50 text-emerald-950' => $summary['pending_approval'],
        ]) role="status">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                    <div>
                        <p class="font-semibold">{{ $summary['pending_approval'] ? 'Onay bekliyor' : 'Ödeme bekliyor' }}</p>
                        <p class="mt-1 text-xs leading-5">{{ $summary['pending_message'] }}</p>
                    </div>
                </div>
                @if ($summary['pending_url'])
                    <a href="{{ $summary['pending_url'] }}" @class([
                        'inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg px-4 py-2 text-xs font-semibold text-white',
                        'bg-amber-700 hover:bg-amber-800' => ! $summary['pending_approval'],
                        'bg-emerald-700 hover:bg-emerald-800' => $summary['pending_approval'],
                    ])>{{ $summary['pending_link_label'] }}</a>
                @endif
            </div>
        </div>
    @elseif ($summary['expiring'])
        <a href="{{ $summary['purchase_url'] }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-amber-600 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-700">Süreyi uzat</a>
    @endif
</aside>
