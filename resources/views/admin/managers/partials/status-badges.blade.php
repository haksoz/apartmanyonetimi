<div class="flex flex-col items-start gap-1">
    @if ($status === 'Aktif')
        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ $status }}</span>
    @elseif ($status === 'Ödeme bekliyor')
        <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $status }}</span>
    @elseif ($status === 'İptal')
        <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">{{ $status }}</span>
    @else
        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $status }}</span>
    @endif
    @if ($pending ?? false)
        <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Bekleyen sipariş</span>
    @endif
</div>
