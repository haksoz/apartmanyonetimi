@if ($row['apartment']?->trashed())
    <p class="mt-1 text-xs text-slate-500">Apartman silinmiş.</p>
@elseif ($row['apartment'] && ! $row['apartment']->is_active)
    <p class="mt-1 text-xs text-slate-500">Apartman pasif</p>
@elseif (! $row['apartment'])
    <p class="mt-1 text-xs text-slate-500">Apartman kaydı silinmiş.</p>
@endif
@if ($row['apartment'] && $renewalPassives->get($row['apartment']->id))
    <p class="mt-1 text-xs text-slate-500">Kurulum yenileme ile pasife alındı. Daire sayısı ücretli dilimin dışındaydı.</p>
@endif
