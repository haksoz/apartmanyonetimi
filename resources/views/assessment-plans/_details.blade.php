@php
    $status = $plan->is_active ? 'Aktif' : 'Pasif';
    $details = [
        'Plan Adı' => $plan->name,
        'Borç Türü' => $plan->due_type->label(),
        'Örnek Borç Açıklaması' => $plan->installmentDescription(1),
        'Toplam Tutar' => number_format((float) $plan->total_amount, 2, ',', '.').' ₺',
        'Taksit Sayısı' => $plan->installment_count,
        'Kategori' => $plan->category?->name,
        'Dağıtım Yöntemi' => match ($plan->distribution_type) { 'square_meters' => 'Metrekareye göre', 'share_coefficient' => 'Pay çarpanına göre', default => 'Eşit dağıtım' },
        'Borç Oluşturma Yöntemi' => $plan->createsOnSchedule() ? 'Zamanı geldikçe oluştur' : 'Tümünü şimdi oluştur',
        'Oluşturma Günü' => 'Her ayın '.$plan->generate_day.'. günü',
        'Borçlanacak Kişiler' => $plan->due_type === \App\Enums\DueType::MalikPayi ? 'Yalnızca malikler' : 'Öncelikle kiracı, yoksa malik',
    ];
@endphp
<section class="mb-6 rounded-2xl bg-white p-4 shadow-sm md:hidden" aria-label="Plan bilgileri">
    <div class="mb-3 flex items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-slate-900">Plan Bilgileri</h2>
        <span @class(['shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold', 'bg-emerald-50 text-emerald-700' => $plan->is_active, 'bg-slate-100 text-slate-600' => ! $plan->is_active])>{{ $status }}</span>
    </div>
    <div class="space-y-2 break-words text-sm leading-relaxed text-slate-600">
        <p class="font-medium text-slate-900">{{ $plan->name }}</p>
        <p><strong class="font-semibold text-slate-900">Toplam {{ number_format($plan->total_amount, 2, ',', '.') }} ₺</strong>, {{ $plan->installment_count }} taksitte {{ mb_strtolower($details['Dağıtım Yöntemi']) }} yöntemiyle dağıtılır.</p>
        <p>Borç türü: {{ $plan->due_type->label() }} · Kategori: {{ $plan->category?->name ?? '—' }}</p>
        <p>{{ $plan->due_type === \App\Enums\DueType::MalikPayi ? 'Yalnızca malikler borçlandırılır.' : 'İlgili dönemde kiracı varsa kiracıya, yoksa malike borçlandırılır.' }}</p>
        @if ($plan->createsOnSchedule())
            <p><span class="inline-flex items-center gap-1 align-middle text-sky-700">@include('financial-plans._clock') Zamanı geldikçe</span> vade ayının <strong class="font-medium text-slate-900">{{ $plan->generate_day }}.</strong> günü oluşturulur.</p>
        @else
            <p>Taksitler toplu oluşturma yöntemiyle hazırlanır.</p>
        @endif
        <p class="text-xs text-slate-500">İlk vade: {{ $plan->installments->first()?->due_date?->format('d.m.Y') ?? '—' }} · Son vade: {{ $plan->installments->last()?->due_date?->format('d.m.Y') ?? '—' }}</p>
        <p class="text-xs text-slate-500">Örnek açıklama: {{ $plan->installmentDescription(1) }}</p>
    </div>
</section>
<div class="hidden md:block">@include('financial-plans._details', compact('status', 'details'))</div>
