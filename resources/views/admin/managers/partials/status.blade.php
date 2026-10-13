@if ($subscription->isPending())
    @if ($subscription->receipt_path || $subscription->receipt_reference)
        <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Onay Bekliyor</span>
    @else
        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Ödeme Bekliyor</span>
    @endif
@elseif ($subscription->isCovering())
    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Aktif</span>
@elseif ($subscription->isCancelled())
    <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">İptal</span>
@else
    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Tamamlandı</span>
@endif
