@if($related instanceof \App\Models\Payment)
    <div class="mt-1 text-xs font-normal {{ $related->unallocated_amount > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
        @if($related->unallocated_amount > 0)
            Tahsis bekleyen: {{ number_format($related->unallocated_amount, 2, ',', '.') }} TL
        @else
            Tam Tahsis
        @endif
    </div>
@endif
