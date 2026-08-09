@php
$steps = [
    1 => 'Apartman Bilgileri',
    2 => 'Kasa Oluşturma',
    3 => 'Daire Bilgileri',
    4 => 'Kategoriler',
];
@endphp

<ol class="mb-8 flex items-center justify-between rounded-2xl bg-white p-4 shadow-sm md:justify-start md:gap-8">
    @foreach ($steps as $step => $label)
        @php
            $isActive = $step === $activeStep;
            $isCompleted = $step < $activeStep;
        @endphp
        <li class="flex flex-1 items-center gap-3 md:flex-none">
            <span @class([
                'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                'bg-emerald-100 text-emerald-700' => $isCompleted,
                'bg-slate-950 text-white' => $isActive,
                'bg-slate-100 text-slate-500' => ! $isActive && ! $isCompleted,
            ])>
                @if ($isCompleted)
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                @else
                    {{ $step }}
                @endif
            </span>
            <span @class([
                'hidden text-sm font-medium md:block',
                'text-slate-950' => $isActive,
                'text-slate-500' => ! $isActive,
            ])>{{ $label }}</span>
            @if (! $loop->last)
                <span class="mx-2 hidden h-px w-8 bg-slate-200 md:block"></span>
            @endif
        </li>
    @endforeach
</ol>
