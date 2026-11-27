@php
    $batch = $due->batch;
    $assessmentPlan = $batch?->assessmentPlan;
    $aidatPlan = $batch?->plan;
    $plan = $assessmentPlan ?? $aidatPlan;
    $installment = $batch?->assessmentInstallment;
    $consumptionPeriod = $batch?->consumptionPeriod;
    $source = \App\Support\DueProductionSource::forRecordedDue($due);
    $details = [
        ['Borç dönemi', $due->record_date?->format('m.Y')],
        ['Sisteme kayıt zamanı', $due->created_at?->copy()->timezone('Europe/Istanbul')->format('d.m.Y H:i')],
    ];

    if (! $due->is_opening) {
        $details[] = ['Üretim kaynağı', $source?->label() ?? 'Bilinmiyor'];
    }
    if ($batch) {
        $details[] = ['Borçlandırma grubu', '#'.$batch->id, route('dues.index', ['batch_id' => $batch->id])];
        $details[] = ['Tutarın kaynağı', $batch->source_type_label];
        $details[] = ['Dağıtım yöntemi', $batch->distribution_type_label];
        if ($batch->source_amount > 0) {
            $details[] = ['Grup toplamı', number_format($batch->source_amount, 2, ',', '.').' TL'];
        }
    }
    if ($plan) {
        $details[] = ['Plan adı', $plan->name, $assessmentPlan
            ? route('assessment-plans.show', $assessmentPlan)
            : route('due-plans.index', $aidatPlan->pageParameters())];
        $details[] = ['Planın oluşturma biçimi', $plan->createsOnSchedule() ? 'Zamanı geldikçe oluştur' : 'Tümünü şimdi oluştur'];
    }
    if ($assessmentPlan && $installment?->assessment_plan_id === $assessmentPlan->id) {
        $details[] = ['Taksit', $installment->sequence.' / '.$assessmentPlan->installment_count];
    }
    if ($consumptionPeriod) {
        $consumptionType = \App\Models\ConsumptionSetting::TYPES[$consumptionPeriod->consumption_type] ?? $consumptionPeriod->consumption_type;
        $details[] = ['Tüketim dönemi', $consumptionType.' · '.$consumptionPeriod->start_date->format('d.m.Y').' – '.$consumptionPeriod->end_date->format('d.m.Y'),
            route('meters.history.detail', $consumptionPeriod)];
        $details[] = ['Ölçüm yöntemi', \App\Models\ConsumptionSetting::METHODS[$consumptionPeriod->method] ?? $consumptionPeriod->method];
        $details[] = ['Ölçüm birimi', $consumptionPeriod->measurement_unit];
    }
    if (! $due->is_opening && app(\App\Support\LateCompensationScope::class)->previewEnabled()) {
        $details[] = ['Gecikme Tazminatı kapsamı', $due->late_compensation_applies ? 'Kapsamda' : 'Kapsam dışında'];
        if ($due->late_compensation_applies) {
            $details[] = ['Kapsam başlangıcı', ($due->late_compensation_starts_on ?? $due->apartment->late_compensation_starts_on)?->format('d.m.Y')];
        }
    }
@endphp

@foreach ($details as $detail)
    @php [$label, $value, $url] = array_pad($detail, 3, null); @endphp
    @if (filled($value))
        <div class="flex items-center justify-between gap-3 bg-white px-4 py-3">
            <div class="text-xs text-slate-500">{{ $label }}</div>
            <div class="min-w-0 break-words text-right font-semibold text-slate-900">
                @if ($url)
                    <a class="text-emerald-700 hover:underline" href="{{ $url }}">{{ $value }}</a>
                @else
                    {{ $value }}
                @endif
            </div>
        </div>
    @endif
@endforeach
