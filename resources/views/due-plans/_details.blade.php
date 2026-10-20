@php
    $status = ! $plan->is_active ? 'Pasif' : ($plan->end_date?->lt(today()) ? 'Süresi Doldu' : 'Aktif');
    $details = [
        'Plan Yılı' => $plan->plan_year,
        'Plan Adı' => $plan->name,
        'Dağıtılacak Toplam Aylık Tutar' => number_format($plan->monthly_amount_resolved, 2, ',', '.').' ₺',
        'Dağıtım Yöntemi' => $plan->distribution_label,
        'Borç Oluşturma Yöntemi' => $plan->createsOnSchedule() ? 'Zamanı geldikçe oluştur' : 'Tümünü şimdi oluştur',
        'Borçlanacak Kişiler' => $plan->target_audience === 'owner_only' ? 'Yalnızca malikler' : 'Öncelikle kiracı, yoksa malik',
        'Oluşturma Günü' => 'Her ayın '.$plan->generate_day.'. günü',
        'Vade Günü' => 'Her ayın '.$plan->due_day.'. günü',
        'Dönem' => $plan->start_date?->format('d.m.Y').' – '.$plan->end_date?->format('d.m.Y'),
        'Tamsayıya Yuvarla' => $plan->round_to_integer ? 'Evet' : 'Hayır',
    ];
@endphp
@include('financial-plans._details', compact('status', 'details'))
