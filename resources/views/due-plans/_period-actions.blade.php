<div class="inline-flex items-center justify-end gap-3">
    @if ($plan->is_active && $p['status'] === 'incomplete' && $p['period'] <= now()->format('Y-m'))
        <form method="POST" action="{{ route('due-plans.regenerate-period', $plan) }}">
            @csrf
            <input type="hidden" name="period" value="{{ $p['period'] }}">
            <button type="submit" class="text-xs font-medium text-emerald-700 underline hover:text-emerald-800">Eksikleri Tamamla</button>
        </form>
    @elseif ($plan->is_active && $p['status'] === 'not_generated' && $p['period'] <= now()->format('Y-m'))
        <form method="POST" action="{{ route('due-plans.generate-month', $plan) }}">
            @csrf
            <input type="hidden" name="period" value="{{ $p['period'] }}">
            <button type="submit" class="text-xs font-medium text-slate-700 underline hover:text-slate-900">Oluştur</button>
        </form>
    @endif
    @if ($p['active_count'] > 0)
        <a href="{{ route('dues.index', ['due_type' => 'aidat', 'source' => 'plan', 'plan' => 'aidat:'.$plan->id, 'period' => $p['period']]) }}"
           aria-label="{{ $p['label'] }} aidatlarını görüntüle" title="Aidatları görüntüle"
           class="inline-flex shrink-0 items-center justify-center rounded-lg p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-slate-500">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.5 7.5-9.75 7.5S2.25 12 2.25 12Z" />
                <circle cx="12" cy="12" r="3" />
            </svg>
        </a>
    @elseif (! $plan->is_active || $p['status'] !== 'not_generated' || $p['period'] > now()->format('Y-m'))
        <span class="text-xs text-slate-400">—</span>
    @endif
</div>
