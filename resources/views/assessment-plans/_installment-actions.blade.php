@if ($inst->status === 'generated')
    <a href="{{ route('dues.index', ['due_type' => $plan->due_type->value, 'source' => 'assessment', 'plan' => 'assessment:'.$plan->id, 'period' => $inst->period]) }}" aria-label="{{ $inst->sequence }}. taksit borçlarını görüntüle" title="Borçları görüntüle" class="inline-flex items-center justify-center rounded-lg border border-slate-200 p-2.5 text-slate-600 hover:bg-slate-50">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-7 9.75-7 9.75 7 9.75 7-3.5 7-9.75 7-9.75-7-9.75-7Z" /><circle cx="12" cy="12" r="3" /></svg>
    </a>
@else
    <span class="text-xs text-slate-400">—</span>
@endif
