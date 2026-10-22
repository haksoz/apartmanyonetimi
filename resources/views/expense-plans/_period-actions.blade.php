@if ($p['status'] === 'complete' && $p['expense_id'])
    <a href="{{ route('expenses.show', $p['expense_id']) }}" aria-label="{{ $p['label'] }} giderini görüntüle" title="Gideri görüntüle" class="inline-flex items-center justify-center rounded-lg border border-slate-200 p-2.5 text-slate-600 hover:bg-slate-50">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-7 9.75-7 9.75 7 9.75 7-3.5 7-9.75 7-9.75-7-9.75-7Z" /><circle cx="12" cy="12" r="3" /></svg>
    </a>
@elseif ($plan->is_active && $p['status'] === 'not_generated')
    <form method="POST" action="{{ route('expense-plans.generate-month', $plan) }}" class="inline">
        @csrf
        <input type="hidden" name="period" value="{{ $p['period'] }}">
        <button type="submit" aria-label="{{ $p['label'] }} giderini oluştur" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Oluştur</button>
    </form>
@else
    <span class="text-xs text-slate-400">—</span>
@endif
