@if($isPayment)
    @foreach($related->cashTransactions as $cash)
        <a href="{{ route('cash.show', $cash) }}" class="my-2 block text-xs text-blue-700 hover:underline">
            Kasa #{{ $cash->id }}
        </a>
    @endforeach
@endif
