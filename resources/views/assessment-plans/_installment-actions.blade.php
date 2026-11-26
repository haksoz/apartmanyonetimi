@if ($inst->status === 'generated')
    @if ($plan->is_active && $inst->has_missing_dues && ! in_array($inst->period, $plan->skipped_periods ?? [], true))
        <form method="POST" action="{{ route('assessment-plans.regenerate-installment', [$plan, $inst]) }}" class="inline-flex">
            @csrf
            <button type="submit" class="rounded-lg border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Eksik Borçları Oluştur</button>
        </form>
    @endif
@endif
<a href="{{ route('assessment-plans.installment-detail', [$plan, $inst]) }}" aria-label="{{ $inst->sequence }}. taksiti incele" title="İncele" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">İncele</a>
