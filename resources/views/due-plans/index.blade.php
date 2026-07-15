@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Aidat Planlama</h1>
            <p class="mt-1 text-sm text-slate-500">Yıllık aidat planlarını tanımlayın ve aylık aidatları otomatik oluşturun.</p>
        </div>
        <a href="{{ route('due-plans.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">+ Yeni Plan</a>
    </div>

    @if ($plans->isEmpty())
        <div class="rounded-2xl bg-white p-10 shadow-sm text-center text-slate-500 text-sm">Henüz aidat planı tanımlanmamış.</div>
    @else
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($plans as $plan)
                @php
                    $generatedPeriods = $plan->batches
                        ->filter(fn ($b) => $b->dues_count > 0)
                        ->pluck('period')
                        ->values()
                        ->toArray();
                @endphp
                <div class="rounded-2xl bg-white p-6 shadow-sm flex flex-col gap-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold text-slate-950 text-base">{{ $plan->name }}</div>
                            <div class="mt-1 text-sm text-slate-500">{{ $plan->year }} · {{ $plan->distribution_label }}</div>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $plan->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $plan->is_active ? 'Aktif' : 'Pasif' }}
                            </span>
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $plan->auto_generate ? 'bg-violet-50 text-violet-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $plan->auto_generate ? 'Otomatik' : 'Manuel' }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <div class="text-xs text-slate-500">Aylık Tutar</div>
                            <div class="mt-1 font-bold text-slate-900 tabular-nums">{{ number_format($plan->monthly_amount_resolved, 2, ',', '.') }} TL</div>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <div class="text-xs text-slate-500">Vade Günü</div>
                            <div class="mt-1 font-bold text-slate-900">Her ayın {{ $plan->due_day }}. günü</div>
                        </div>
                    </div>

                    <div class="text-xs text-slate-500">
                        @if ($plan->auto_generate)
                            Her ayın <span class="font-medium text-slate-700">{{ $plan->generate_day }}. günü</span> otomatik oluşturulur.
                        @else
                            Oluşturma günü: <span class="font-medium text-slate-700">{{ $plan->generate_day }}.</span> gün (manuel tetikleme)
                        @endif
                    </div>

                    @if ($plan->is_active)
                        @php
                            $planLabel = $plan->due_type_label !== '-' ? $plan->due_type_label : $plan->name;
                            if ($plan->category) $planLabel .= ' - ' . $plan->category->name;
                        @endphp
                        <button type="button"
                                onclick="openGenerateModal({{ $plan->id }}, {{ json_encode($plan->name) }}, {{ json_encode(route('due-plans.generate-month', $plan)) }}, {{ json_encode($generatedPeriods) }}, {{ json_encode($planLabel) }})"
                                class="w-full rounded-xl bg-violet-600 px-3 py-2 text-xs font-semibold text-white text-center hover:bg-violet-700">
                            + Aidat Oluştur
                        </button>
                    @endif

                    <div class="flex gap-2">
                        <a href="{{ route('due-plans.edit', $plan) }}" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 text-center hover:bg-slate-50">Düzenle</a>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Aidat Oluştur Modal --}}
        <div id="modal-plan-generate" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                    <h2 class="text-base font-semibold text-slate-950" id="gen-modal-title">Aidat Oluştur</h2>
                    <button type="button" onclick="closeGenerateModal()" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form id="form-plan-generate" method="POST" action="" class="px-6 py-5 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Dönem</label>
                        <input type="month" name="period" id="gen-period"
                               value="{{ now()->format('Y-m') }}"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none" required>
                        <p id="gen-period-warning" class="hidden mt-1 text-xs text-amber-600 font-medium">Bu dönem için aidat zaten oluşturulmuş.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Açıklama <span class="text-xs text-slate-400">(isteğe bağlı)</span></label>
                        <input type="text" name="description" id="gen-description"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none"
                               placeholder="Otomatik oluşturulur">
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button type="submit" id="gen-submit-btn"
                                class="flex-1 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">
                            Oluştur
                        </button>
                        <button type="button" onclick="closeGenerateModal()"
                                class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Vazgeç
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            var trMonths = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
            var currentGeneratedPeriods = [];

            var currentPlanLabel = '';

            function openGenerateModal(planId, planName, actionUrl, generatedPeriods, planLabel) {
                currentGeneratedPeriods = generatedPeriods || [];
                currentPlanLabel = planLabel || planName;

                document.getElementById('gen-modal-title').textContent = planName + ' — Aidat Oluştur';
                document.getElementById('form-plan-generate').action = actionUrl;

                var periodInput = document.getElementById('gen-period');
                periodInput.value = new Date().toISOString().slice(0, 7);
                periodInput.dataset.userEdited = '';

                document.getElementById('gen-description').value = '';
                document.getElementById('gen-description').dataset.userEdited = '';

                checkPeriodWarning(periodInput.value);
                updateGenDescription(periodInput.value);

                document.getElementById('modal-plan-generate').classList.remove('hidden');
            }

            function closeGenerateModal() {
                document.getElementById('modal-plan-generate').classList.add('hidden');
            }

            function checkPeriodWarning(period) {
                var warning = document.getElementById('gen-period-warning');
                var submitBtn = document.getElementById('gen-submit-btn');
                var alreadyGenerated = currentGeneratedPeriods.indexOf(period) !== -1;
                warning.classList.toggle('hidden', !alreadyGenerated);
                submitBtn.disabled = alreadyGenerated;
                submitBtn.classList.toggle('opacity-50', alreadyGenerated);
                submitBtn.classList.toggle('cursor-not-allowed', alreadyGenerated);
            }

            function updateGenDescription(period) {
                var descInput = document.getElementById('gen-description');
                if (descInput.dataset.userEdited) return;
                if (!period) return;
                var parts = period.split('-');
                var month = trMonths[parseInt(parts[1], 10) - 1] || '';
                var year  = parts[0] || '';
                descInput.value = month + ' ' + year + (currentPlanLabel ? ' - ' + currentPlanLabel : '');
            }

            document.getElementById('gen-period').addEventListener('change', function() {
                checkPeriodWarning(this.value);
                updateGenDescription(this.value);
            });

            document.getElementById('gen-description').addEventListener('input', function() {
                this.dataset.userEdited = this.value ? '1' : '';
            });

            document.getElementById('modal-plan-generate').addEventListener('click', function(e) {
                if (e.target === this) closeGenerateModal();
            });
        </script>
    @endif

    {{-- Aidat Planlaması ile Oluşturulan Aylar --}}
    @php
        $allBatches = $plans->flatMap(fn ($p) => $p->batches
                               ->filter(fn ($b) => $b->dues_count > 0)
                               ->map(fn ($b) => ['plan' => $p, 'batch' => $b]))
                           ->sortBy(fn ($row) => $row['batch']->period);
    @endphp
    @if ($allBatches->isNotEmpty())
        <div class="mt-8">
            <h2 class="text-base font-semibold text-slate-950 mb-3">Aidat Planlaması ile Oluşturulan Aylar</h2>
            <div class="rounded-2xl bg-white shadow-sm overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Dönem</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Plan</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Dağıtım</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">Toplam Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($allBatches as $row)
                            @php
                                $batchPeriod = \Carbon\Carbon::parse($row['batch']->period . '-01')->locale('tr')->isoFormat('MMMM YYYY');
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $batchPeriod }}</td>
                                <td class="px-5 py-3 text-slate-700">
                                    {{ $row['plan']->name }}
                                    @if (!$row['plan']->is_active)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Pasif</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-500">
                                    {{ $row['plan']->distribution_label }}
                                </td>
                                <td class="px-5 py-3 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($row['batch']->source_amount, 2, ',', '.') }} TL</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
