@php
    $installmentStatus = match (true) {
        $inst->status === 'generated' => 'Oluşturuldu',
        $inst->status === 'cancelled' => 'İptal',
        in_array($inst->period, $plan->skipped_periods ?? [], true) => 'Atlandı',
        ! $plan->is_active => 'Durduruldu',
        default => 'Bekliyor',
    };
@endphp
<span @class(['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', 'bg-emerald-50 text-emerald-700' => $installmentStatus === 'Oluşturuldu', 'bg-red-50 text-red-700' => $installmentStatus === 'İptal', 'bg-slate-100 text-slate-600' => ! in_array($installmentStatus, ['Oluşturuldu', 'İptal'], true)])>{{ $installmentStatus }}</span>
