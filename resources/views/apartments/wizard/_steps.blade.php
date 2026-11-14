@php
$steps = [
    1 => 'Apartman Bilgileri',
    2 => 'Yönetici Bilgileri',
    3 => 'Kasa Oluşturma',
    4 => 'Daire Bilgileri',
    5 => 'Kategoriler',
];
@endphp

<ol aria-label="Kurulum aşamaları" class="mb-3 flex items-center gap-2 rounded-2xl bg-white p-2 shadow-sm sm:gap-3 sm:p-3 md:mb-4">
    @foreach ($steps as $step => $label)
        @php
            $isActive = $step === $activeStep;
            $isCompleted = $step < $activeStep;
        @endphp
        <li class="flex min-w-0 items-center gap-2" @if($isActive) aria-current="step" @endif aria-label="{{ $step }}. aşama{{ $isCompleted ? ', tamamlandı' : ($isActive ? ', mevcut aşama' : ', sonraki aşama') }}">
            <span @class([
                'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-sm font-semibold sm:h-8 sm:w-8',
                'bg-emerald-100 text-emerald-700' => $isCompleted,
                'bg-slate-950 text-white' => $isActive,
                'bg-slate-100 text-slate-500' => ! $isActive && ! $isCompleted,
            ])>
                {{ $step }}
            </span>
            @if ($isActive)
                <span class="text-xs font-semibold leading-tight text-slate-950 sm:text-sm">{{ $label }}</span>
            @endif
        </li>
    @endforeach
</ol>
