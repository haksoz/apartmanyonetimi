@php
    $status = $plan->is_active ? 'Aktif' : 'Pasif';
    $details = [
        'Plan Adı' => $plan->name,
        'Açıklama' => $plan->description,
        'Toplam Tutar' => number_format((float) $plan->total_amount, 2, ',', '.').' ₺',
        'Taksit Sayısı' => $plan->installment_count,
        'Kategori' => $plan->category?->name,
        'Dağıtım Yöntemi' => match ($plan->distribution_type) { 'square_meters' => 'Metrekareye göre', 'share_coefficient' => 'Pay çarpanına göre', default => 'Eşit dağıtım' },
        'Borç Oluşturma Yöntemi' => $plan->createsOnSchedule() ? 'Zamanı geldikçe oluştur' : 'Tümünü şimdi oluştur',
        'Oluşturma Günü' => 'Her ayın '.$plan->generate_day.'. günü',
        'Borçlanacak Kişiler' => $plan->target_audience === 'owner_only' ? 'Yalnızca malikler' : 'Öncelikle kiracı, yoksa malik',
    ];
@endphp
@include('financial-plans._details', compact('status', 'details'))
