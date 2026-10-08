@php
    $labels = [1 => 'Abonelik', 2 => 'Fatura', 3 => 'Ödeme'];
@endphp
<ol class="mb-5 flex items-center gap-2" aria-label="Sipariş adımları">
    @foreach ($labels as $number => $label)
        <li class="flex min-w-0 items-center gap-2 {{ $number < 3 ? 'flex-1' : '' }}">
            <span @class([
                'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                'bg-slate-900 text-white' => $number === $active,
                'bg-emerald-100 text-emerald-800' => $number < $active,
                'bg-slate-100 text-slate-500' => $number > $active,
            ])>{{ $number }}</span>
            <span @class([
                'truncate text-xs font-semibold',
                'text-slate-900' => $number === $active,
                'text-slate-500' => $number !== $active,
            ])>{{ $label }}</span>
            @if ($number < 3)
                <span class="h-px min-w-2 flex-1 bg-slate-200"></span>
            @endif
        </li>
    @endforeach
</ol>
