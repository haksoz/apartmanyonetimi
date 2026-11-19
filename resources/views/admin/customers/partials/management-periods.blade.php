@forelse ($row['management_history'] as $managementPeriod)
    <div class="{{ $loop->first ? '' : 'mt-2' }} text-xs text-slate-600">
        <p>{{ $managementPeriod->started_at?->timezone('Europe/Istanbul')->format('d.m.Y') ?? 'Başlangıç kaydı yok' }} – {{ $managementPeriod->ended_at?->timezone('Europe/Istanbul')->format('d.m.Y') ?? ($managementPeriod->is_current ? 'Devam ediyor' : 'Bitiş kaydı yok') }}</p>
        @if ($managementPeriod->start_source === 'initial_membership')<p class="mt-1 text-slate-500">Başlangıç ilk üyelik kaydına göre</p>@endif
    </div>
@empty
    <span class="text-xs text-slate-400">—</span>
@endforelse
