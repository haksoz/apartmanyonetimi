<div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <div class="break-words text-sm font-bold text-slate-900">{{ $transaction->description ?: ucfirst($transaction->type) }}</div>
            <div class="mt-1 break-words text-sm font-semibold text-slate-700">{{ $isOrphan ? 'Hesapsız' : ($transaction->account?->name ?? '-') }}</div>
        </div>
        <span class="shrink-0 pt-0.5 text-xs text-slate-500 tabular-nums" aria-label="İşlem tarihi">{{ $transaction->transaction_date?->format('d.m.Y') ?? '-' }}</span>
    </div>
    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
        @if ($relatedUrl)
            <a href="{{ $relatedUrl }}" class="py-1 font-medium text-slate-600 underline hover:text-slate-900">{{ $relatedLabel }}</a>
        @elseif ($relatedLabel !== '-')
            <span class="text-slate-600">{{ $relatedLabel }}</span>
        @endif
        @if ($related && $related->reference_number)
            <span class="break-all text-slate-500" aria-label="Referans ID">{{ $related->reference_number }}</span>
        @endif
        @if ($isPayment)
            @foreach ($related->cashTransactions as $cash)
                <a href="{{ route('cash.show', $cash) }}" class="py-1 text-blue-700 hover:underline">Kasa #{{ $cash->id }}</a>
            @endforeach
        @endif
    </div>
    <div class="mt-2 flex items-end justify-between gap-2 border-t border-slate-100 pt-2">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-bold tabular-nums {{ $transaction->type === 'debit' ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($transaction->amount, 2, ',', '.') }} TL</span>
                <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold {{ $transaction->type === 'debit' ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $transaction->type === 'debit' ? 'bg-red-600' : 'bg-emerald-600' }}"></span>
                    {{ $transaction->type === 'debit' ? 'Borç' : 'Alacak' }}
                </span>
            </div>
            @include('ledger._allocation')
        </div>
        @if ($isPayment && $related->unallocated_amount > 0)
            <a href="{{ $transaction->account?->type === \App\Models\Account::TYPE_SUPPLIER ? route('payments.supplier-allocations.create', $related) : route('payments.allocations.create', $related) }}" class="shrink-0 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Tahsis Et</a>
        @endif
    </div>
</div>
