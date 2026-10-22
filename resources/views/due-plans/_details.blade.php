@php
    $status = ! $plan->is_active ? 'Pasif' : ($plan->end_date?->lt(today()) ? 'Süresi Doldu' : 'Aktif');
    $details = [
        'Plan Yılı' => $plan->plan_year,
        'Plan Adı' => $plan->name,
        'Dağıtılacak Toplam Aylık Tutar' => number_format($plan->monthly_amount_resolved, 2, ',', '.').' ₺',
        'Dağıtım Yöntemi' => $plan->distribution_label,
        'Borç Oluşturma Yöntemi' => $plan->createsOnSchedule() ? 'Zamanı geldikçe oluştur' : 'Tümünü şimdi oluştur',
        'Borçlanacak Kişi' => 'İlgili dönemde kiracı varsa kiracıya, yoksa malike borçlandırılır.',
        'Oluşturma Günü' => 'Her ayın '.$plan->generate_day.'. günü',
        'Vade Günü' => 'Her ayın '.$plan->due_day.'. günü',
        'Dönem' => $plan->start_date?->format('d.m.Y').' – '.$plan->end_date?->format('d.m.Y'),
        'Tamsayıya Yuvarla' => $plan->round_to_integer ? 'Evet' : 'Hayır',
    ];
@endphp
<section class="mb-6 rounded-2xl bg-white p-4 shadow-sm md:hidden" aria-label="Plan bilgileri">
    <div class="mb-3 flex items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-slate-900">Plan Bilgileri</h2>
        <span @class([
            'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold',
            'bg-emerald-50 text-emerald-700' => $status === 'Aktif',
            'bg-amber-50 text-amber-700' => $status === 'Süresi Doldu',
            'bg-slate-100 text-slate-600' => $status === 'Pasif',
        ])>{{ $status }}</span>
    </div>
    <div class="space-y-2 text-sm leading-relaxed text-slate-600">
        <p class="break-words font-medium text-slate-900">{{ $plan->name }} · {{ $plan->plan_year }}</p>
        <p><strong class="font-semibold text-slate-900">Aylık {{ number_format($plan->monthly_amount_resolved, 2, ',', '.') }} ₺</strong>, {{ $plan->distribution_label }} yöntemiyle dağıtılır.</p>
        <p>{{ $plan->round_to_integer ? 'Tutarlar tam sayıya yuvarlanır.' : 'Tutarlar tam sayıya yuvarlanmaz.' }}</p>
        <p>
            @if ($plan->createsOnSchedule())
                <span class="inline-flex items-center gap-1 align-middle text-sky-700">@include('financial-plans._clock') Zamanı geldikçe</span>
                her ayın <strong class="font-medium text-slate-900">{{ $plan->generate_day }}.</strong> günü oluşturulur.
            @else
                Aidatlar toplu oluşturulur. Oluşturma günü her ayın <strong class="font-medium text-slate-900">{{ $plan->generate_day }}.</strong> günüdür.
            @endif
        </p>
        <p>Vade, her ayın <strong class="font-medium text-slate-900">{{ $plan->due_day }}.</strong> günüdür.</p>
        <p>İlgili dönemde kiracı varsa kiracıya, yoksa malike borçlandırılır.</p>
        <p class="text-xs text-slate-500">Dönem: {{ $plan->start_date?->format('d.m.Y') }} – {{ $plan->end_date?->format('d.m.Y') }}</p>
    </div>
</section>
<div class="hidden md:block">
    @include('financial-plans._details', compact('status', 'details'))
</div>
