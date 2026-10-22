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
    <div class="space-y-2 break-words text-sm leading-relaxed text-slate-600">
        <p class="font-medium text-slate-900">{{ $plan->name }}</p>
        <p><strong class="font-semibold text-slate-900">Aylık {{ number_format($plan->amount, 2, ',', '.') }} ₺</strong>, {{ $plan->account?->name ?? 'Tedarikçi' }} hesabına gider olarak kaydedilir.</p>
        <p>Kategori: {{ $plan->category?->name ?? '—' }}</p>
        <p>
            @if ($plan->generation_mode === 'on_schedule')
                <span class="inline-flex items-center gap-1 align-middle text-sky-700">@include('financial-plans._clock') Zamanı geldikçe</span>
                her ayın <strong class="font-medium text-slate-900">{{ $plan->generate_day }}.</strong> günü oluşturulur.
            @else
                Giderler toplu oluşturulur. Oluşturma tarihi her ayın <strong class="font-medium text-slate-900">{{ $plan->generate_day }}.</strong> günüdür.
            @endif
        </p>
        <p>
            @if ($plan->due_days === null)
                Son ödeme tarihi belirlenmemiştir.
            @elseif ($plan->due_days === 0)
                Son ödeme tarihi, oluşturma tarihiyle aynıdır.
            @else
                Son ödeme tarihi, oluşturma tarihinden <strong class="font-medium text-slate-900">{{ $plan->due_days }} gün sonradır.</strong>
            @endif
        </p>
        <p class="text-xs text-slate-500">Dönem: {{ $plan->start_date->format('d.m.Y') }} – {{ $plan->end_date?->format('d.m.Y') ?? 'Süresiz' }}</p>
        <details class="border-t border-slate-100 pt-2">
            <summary class="cursor-pointer text-xs font-medium text-slate-600">Gider açıklaması örneği</summary>
            <p class="mt-2 text-sm">{{ $description.($suffix !== '' ? ' '.$suffix : '') }}</p>
        </details>
    </div>
</section>
<div class="hidden md:block">
    @include('financial-plans._details', compact('status', 'details'))
</div>
