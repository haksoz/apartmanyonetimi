@php
    $status = ! $plan->is_active ? 'Pasif' : ($plan->end_date?->lt(today()) ? 'Süresi Doldu' : 'Aktif');
    $examplePeriod = $plan->start_date->copy()->locale('tr');
    $description = $plan->description ?: $examplePeriod->isoFormat('YYYY MMMM').' - '.($plan->category?->name ?: $plan->name);
    $suffix = trim($plan->description_suffix ?? '');
    $details = [
        'Plan Adı' => $plan->name,
        'Hesap / Tedarikçi' => $plan->account?->name,
        'Kategori' => $plan->category?->name,
        'Aylık Tutar' => number_format((float) $plan->amount, 2, ',', '.').' ₺',
        'Başlangıç Tarihi' => $plan->start_date->format('d.m.Y'),
        'Bitiş Tarihi' => $plan->end_date?->format('d.m.Y') ?? 'Süresiz',
        'Gider Oluşturma Yöntemi' => $plan->generation_mode === 'create_all_now' ? 'Tümünü şimdi oluştur' : 'Zamanı geldikçe oluştur',
        'Oluşturma Günü' => 'Her ayın '.$plan->generate_day.'. günü',
        'Vade' => $plan->due_days === null ? 'Vadesiz' : ($plan->due_days === 0 ? 'Aynı gün' : $plan->due_days.' gün'),
        'Açıklamaya Eklenecek Metin' => $plan->description_suffix,
        'Örnek Gider Açıklaması' => $description.($suffix !== '' ? ' '.$suffix : ''),
    ];
@endphp
@include('financial-plans._details', compact('status', 'details'))
