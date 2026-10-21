@if ($p['scheduled_for'])
    <span class="inline-flex items-center gap-2 whitespace-nowrap text-xs text-slate-600" title="{{ $p['scheduled_for']->lt(today()) ? 'Oluşturma günü geçti; sonraki otomatik görev bekleniyor.' : 'Aktif planın aidatları bu tarihte otomatik oluşturulur.' }}">
        @include('financial-plans._clock')
        <span>{{ $p['scheduled_for']->format('d.m.Y') }}<span class="sr-only"> — Otomatik oluşturma bekleniyor</span></span>
    </span>
@else
    <span class="text-xs text-slate-400">—</span>
@endif
