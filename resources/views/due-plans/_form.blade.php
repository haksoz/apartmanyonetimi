<input type="hidden" name="name" value="Aidat Kararı">
<input type="hidden" name="amount_type" value="monthly">
<input type="hidden" name="due_type" value="aidat">
<input type="hidden" name="category_id" value="">

<div class="space-y-5">
    @if ($plan?->exists)
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            <span class="font-medium text-slate-600">Takvim Yılı</span>
            <span class="font-semibold text-slate-900">{{ $plan->plan_year }}</span>
            <span class="text-xs text-slate-500">1 Ocak – 31 Aralık</span>
            <input id="plan_year" type="hidden" name="plan_year" value="{{ $plan->plan_year }}">
        </div>
        @error('plan_year')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    @else
    <div>
        <h3 class="text-sm font-semibold text-slate-700 mb-3">Plan Yılı</h3>
        <div class="md:w-1/2">
            <label for="plan_year" class="block text-sm font-medium text-slate-600 mb-1">Takvim Yılı</label>
            <input type="number" id="plan_year" name="plan_year"
                   value="{{ old('plan_year', $plan?->plan_year ?? $selectedYear ?? now()->year) }}"
                   min="2000" max="2100" required
                   class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
                <p class="mt-1 text-xs text-slate-500">Plan 1 Ocak – 31 Aralık dönemini kapsar. Aynı yıl için tek plan oluşturulabilir.</p>
            @error('plan_year')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    @endif

    <hr class="border-slate-100">

    <div>
        <h3 class="text-sm font-semibold text-slate-700 mb-3">Borç Oluşturma Yöntemi</h3>
        <div class="flex flex-col sm:flex-row gap-3">
            <label class="cursor-pointer flex-1">
                <input type="radio" name="generation_mode" value="on_schedule" class="peer sr-only"
                       @checked(old('generation_mode', $plan?->generation_mode ?? 'on_schedule') === 'on_schedule')>
                <div class="rounded-lg border border-slate-200 p-3 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50 h-full">
                    <div class="font-semibold text-slate-800 text-sm">Zamanı geldikçe oluştur</div>
                    <div class="text-xs text-slate-500 mt-0.5">Varsayılan. Borçlar oluşturma gününde üretilir.</div>
                </div>
            </label>
            <label class="cursor-pointer flex-1">
                <input type="radio" name="generation_mode" value="create_all_now" class="peer sr-only"
                       @checked(old('generation_mode', $plan?->generation_mode) === 'create_all_now')>
                <div class="rounded-lg border border-slate-200 p-3 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50 h-full">
                    <div class="font-semibold text-slate-800 text-sm">Tümünü şimdi oluştur</div>
                    <div class="text-xs text-slate-500 mt-0.5">Uygun dönemlerin borçları kaydedince oluşturulur.</div>
                </div>
            </label>
        </div>
        @error('generation_mode')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
        <input type="hidden" name="include_past_periods" value="0">
        <div id="aidat-past-periods-field" class="mt-3 hidden rounded-xl border border-amber-200 bg-amber-50 p-3">
            <label class="flex cursor-pointer items-start gap-2 text-sm text-amber-900">
                <input id="include_past_periods" name="include_past_periods" type="checkbox" value="1" @checked(old('include_past_periods', false)) aria-describedby="aidat-past-periods-help" class="mt-0.5 rounded border-amber-300">
                Geçmiş aylara ait aidatları da oluştur
            </label>
            <p id="aidat-past-periods-help" class="mt-1 text-xs text-amber-800">Seçilen yılın geçmiş ayları dahil edilir. İşaretlemezseniz bu aydan önceki dönemler atlanır.</p>
        </div>
        @error('include_past_periods')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <hr class="border-slate-100">

    <div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div>
                <label for="generate_day" class="block text-sm font-medium text-slate-600 mb-1">Oluşturma Günü</label>
                <input type="number" id="generate_day" name="generate_day"
                       value="{{ old('generate_day', $plan?->generate_day ?? 1) }}"
                       min="1" max="28" required
                       placeholder="1-28"
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-center focus:border-emerald-500 focus:outline-none">
                <p class="mt-1 text-xs text-slate-500">Her ay aidatın oluşturulacağı gün.</p>
                @error('generate_day')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="due_day" class="block text-sm font-medium text-slate-600 mb-1">Vade Günü</label>
                <input type="number" id="due_day" name="due_day"
                       value="{{ old('due_day', $plan?->due_day ?? 1) }}"
                       min="1" max="28" required
                       placeholder="1-28"
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-center focus:border-emerald-500 focus:outline-none">
                @error('due_day')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        <div class="md:col-span-2">
            <label for="monthly_amount" class="mb-1 block text-sm font-medium text-slate-600">Dağıtılacak Toplam Aylık Tutar</label>
            <input id="monthly_amount" name="monthly_amount" type="number" min="0.01" step="0.01"
                   value="{{ old('monthly_amount', $plan?->monthly_amount) }}"
                   class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-emerald-500 focus:outline-none font-semibold"
                   placeholder="0,00" required>
            <p class="mt-1 text-xs text-slate-500">Değişiklik yalnızca henüz oluşturulmamış dönemlere uygulanır. Mevcut borçlar otomatik güncellenmez.</p>
            @error('monthly_amount')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        </div>
    </div>

    <hr class="border-slate-100">

    <div>
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-700">Borç Bilgileri</h3>
            <div id="calc-summary" class="text-sm font-medium text-red-600 hidden"></div>
        </div>

        <div class="space-y-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Dağıtım Yöntemi</label>
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <select name="distribution_type" id="distribution_type" class="w-full sm:flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
                        <option value="equal" @selected(old('distribution_type', $plan?->distribution_type ?? 'equal') === 'equal')>Eşit dağıtım</option>
                        <option value="square_meters" @selected(old('distribution_type', $plan?->distribution_type) === 'square_meters')>Metrekareye göre</option>
                        <option value="share_coefficient" @selected(old('distribution_type', $plan?->distribution_type) === 'share_coefficient')>Pay çarpanına göre</option>
                    </select>
                    <label class="inline-flex items-center gap-2 shrink-0">
                        <input type="checkbox" id="round_to_integer" name="round_to_integer" value="1"
                               @checked(old('round_to_integer', $plan?->round_to_integer))
                               class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm text-slate-600 whitespace-nowrap">Tamsayıya yuvarla</span>
                    </label>
                </div>
                @error('distribution_type')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div id="distribution-preview" class="hidden">
                <div class="rounded-xl border border-slate-200 overflow-hidden">
                    <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dağıtım Önizlemesi</span>
                        <span id="preview-total-label" class="text-xs text-slate-500"></span>
                    </div>
                    <div id="preview-warning" class="hidden px-4 py-2 bg-amber-50 border-b border-amber-200 text-xs text-amber-700"></div>
                    <div id="preview-groups" class="divide-y divide-slate-100"></div>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Borçlanacak Kişiler</label>
                <div class="flex gap-3">
                    <label class="cursor-pointer flex-1">
                        <input type="radio" name="target_audience" value="tenant_priority" class="peer sr-only" @checked(old('target_audience', $plan?->target_audience ?? 'tenant_priority') === 'tenant_priority')>
                        <div class="rounded-lg border border-slate-200 p-2.5 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50">
                            <div class="font-semibold text-slate-800 peer-checked:text-emerald-700 text-sm">Kiracı Öncelikli</div>
                            <div class="text-xs text-slate-500 mt-0.5">Varsa Kiracıya, yoksa sahibine</div>
                        </div>
                    </label>
                    <label class="cursor-pointer flex-1">
                        <input type="radio" name="target_audience" value="owner_only" class="peer sr-only" @checked(old('target_audience', $plan?->target_audience) === 'owner_only')>
                        <div class="rounded-lg border border-slate-200 p-2.5 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50">
                            <div class="font-semibold text-slate-800 peer-checked:text-emerald-700 text-sm">Sadece Sahipler</div>
                            <div class="text-xs text-slate-500 mt-0.5">Tüm borçlar kat maliklerine</div>
                        </div>
                    </label>
                </div>
                @error('target_audience')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <hr class="border-slate-100">

    <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 mb-1">Oluşacak Aidat Açıklaması</p>
        <p class="text-sm text-emerald-900">
            Her ay aidat oluşturulduğunda açıklama şu şekilde otomatik yazılır:
            <span class="font-medium">"2026 Temmuz - Aidat"</span>
        </p>
        <p class="mt-1 text-xs text-emerald-700">Ay ve yıl, oluşturulan döneme göre değişir.</p>
    </div>

    <hr class="border-slate-100">

    <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3">
        <div>
            <p class="text-sm font-medium text-slate-700">Aktif / Pasif</p>
            <p class="text-xs text-slate-500 mt-0.5">Pasif planda otomatik ve plan üzerinden manuel aidat oluşturulmaz.</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $plan?->is_active ?? true))
                   class="sr-only peer">
            <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:bg-slate-900 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
        </label>
    </div>
</div>

<script>
    (() => {
        const generationModes = document.querySelectorAll('input[name="generation_mode"]');
        const planYearInput = document.getElementById('plan_year');
        const pastPeriodsField = document.getElementById('aidat-past-periods-field');
        const includePastInput = document.getElementById('include_past_periods');
        const currentYear = {{ now()->year }};
        const currentMonth = {{ now()->month }};
        const syncPastPeriods = () => {
            const year = Number(planYearInput.value);
            const bulk = document.querySelector('input[name="generation_mode"]:checked')?.value === 'create_all_now';
            const hasPast = year >= 2000 && (year < currentYear || (year === currentYear && currentMonth > 1));
            const visible = bulk && hasPast;
            pastPeriodsField.classList.toggle('hidden', !visible);
            includePastInput.disabled = !visible;
        };
        generationModes.forEach(input => input.addEventListener('change', syncPastPeriods));
        planYearInput.addEventListener('input', syncPastPeriods);
        syncPastPeriods();

        const unitsData = [
            @foreach ($units as $u)
            {
                label: {!! json_encode(($u->block ? $u->block . '/' : '') . $u->unit_no) !!},
                sqm: {{ (float) ($u->square_meters ?? 0) }},
                coef: {{ (float) ($u->share_coefficient ?? 0) }}
            }@if (!$loop->last),@endif
            @endforeach
        ];

        const monthlyAmount = document.getElementById('monthly_amount');
        const distributionType = document.getElementById('distribution_type');
        const roundToInteger = document.getElementById('round_to_integer');
        const calcSummary = document.getElementById('calc-summary');

        const formatMoney = (amount, fractionDigits = 2) => {
            const n = Number(amount);
            if (! Number.isFinite(n)) {
                return '—';
            }
            return new Intl.NumberFormat('tr-TR', {
                minimumFractionDigits: fractionDigits,
                maximumFractionDigits: fractionDigits
            }).format(n) + ' TL';
        };

        const getActiveUnits = (distType) => {
            return unitsData.filter(u => {
                if (distType === 'square_meters') return Number(u.sqm) > 0;
                if (distType === 'share_coefficient') return Number(u.coef) > 0;
                return true;
            });
        };

        const getTotalWeight = (activeUnits, distType) => {
            return activeUnits.reduce((s, u) => {
                if (distType === 'square_meters') return s + Number(u.sqm);
                if (distType === 'share_coefficient') return s + Number(u.coef);
                return s + 1;
            }, 0);
        };

        const getRoundedTotal = (total) => {
            const distType = distributionType.value;
            const activeUnits = getActiveUnits(distType);
            if (! total || total <= 0 || activeUnits.length === 0) return 0;

            const totalWeight = getTotalWeight(activeUnits, distType);
            if (! totalWeight || ! Number.isFinite(totalWeight)) return 0;

            const baseTotal = Math.ceil(total);
            let sum = 0;
            activeUnits.forEach(u => {
                const w = distType === 'equal' ? 1 : (distType === 'square_meters' ? Number(u.sqm) : Number(u.coef));
                sum += Math.ceil(baseTotal * w / totalWeight);
            });
            return sum;
        };

        const updateDistributionPreview = (total) => {
            const distType = distributionType.value;
            const previewEl = document.getElementById('distribution-preview');
            const groupsEl = document.getElementById('preview-groups');
            const warningEl = document.getElementById('preview-warning');
            const totalLbl = document.getElementById('preview-total-label');
            const round = roundToInteger?.checked;
            const baseTotal = round ? Math.ceil(total) : total;

            if (! baseTotal || baseTotal <= 0 || ! unitsData.length) {
                previewEl.classList.add('hidden');
                return;
            }

            const activeUnits = getActiveUnits(distType);
            const zeroCount = unitsData.length - activeUnits.length;
            if (distType !== 'equal' && zeroCount > 0) {
                warningEl.textContent = zeroCount + ' dairenin ' + (distType === 'square_meters' ? 'metrekare' : 'pay çarpanı') + ' bilgisi 0 veya boş — bu daireler dağıtımdan hariç tutulur.';
                warningEl.classList.remove('hidden');
            } else {
                warningEl.classList.add('hidden');
            }

            if (activeUnits.length === 0) {
                previewEl.classList.add('hidden');
                return;
            }

            const totalWeight = getTotalWeight(activeUnits, distType);
            if (! totalWeight || ! Number.isFinite(totalWeight)) {
                previewEl.classList.add('hidden');
                return;
            }

            let shares = [];
            let allocated = 0;
            activeUnits.forEach((u, idx) => {
                const w = distType === 'equal' ? 1 : (distType === 'square_meters' ? Number(u.sqm) : Number(u.coef));
                const share = round
                    ? Math.ceil(baseTotal * w / totalWeight)
                    : (idx === activeUnits.length - 1
                        ? Math.round((baseTotal - allocated) * 100) / 100
                        : Math.round(baseTotal * w / totalWeight * 100) / 100);
                allocated += share;
                shares.push({ unit: u, w, share });
            });

            const totalToUse = round ? allocated : baseTotal;

            const groups = {};
            shares.forEach(s => {
                const key = distType === 'equal' ? 'equal' : String(s.w);
                if (! groups[key]) groups[key] = { w: s.w, share: s.share, count: 0 };
                groups[key].count++;
            });

            const sortedGroups = Object.values(groups).sort((a, b) => a.w - b.w);

            let html = '';
            sortedGroups.forEach(g => {
                let weightLabel = distType === 'equal' ? 'Eşit dağıtım'
                    : distType === 'square_meters' ? Number(g.w).toLocaleString('tr-TR') + ' m²'
                    : Number(g.w).toLocaleString('tr-TR') + ' çarpan';
                html += `<div class="flex items-center justify-between px-4 py-3 text-sm">
                    <div class="text-slate-700"><span class="font-medium">${weightLabel}</span> — <span class="text-slate-500">${g.count} daire</span></div>
                    <div class="font-bold text-slate-900 tabular-nums">${formatMoney(g.share, round ? 0 : 2)} / daire</div>
                </div>`;
            });

            groupsEl.innerHTML = html;
            totalLbl.textContent = formatMoney(totalToUse, round ? 0 : 2) + ' · ' + activeUnits.length + ' daire';
            previewEl.classList.remove('hidden');
        };

        const updateCalculation = () => {
            const total = parseFloat(String(monthlyAmount?.value ?? '').replace(',', '.')) || 0;
            updateDistributionPreview(total);

            if (unitsData.length && total > 0) {
                const round = roundToInteger?.checked;
                const activeUnits = getActiveUnits(distributionType.value);
                const unitCount = activeUnits.length || unitsData.length;
                const displayTotal = round ? getRoundedTotal(total) : total;
                const displayPerUnit = round
                    ? (unitCount ? Math.ceil(displayTotal / unitCount) : 0)
                    : (unitCount ? displayTotal / unitCount : 0);
                calcSummary.textContent = `Toplam: ${formatMoney(displayTotal, round ? 0 : 2)} / Daire: ${formatMoney(displayPerUnit, round ? 0 : 2)}`;
                calcSummary.classList.remove('hidden');
            } else {
                calcSummary.classList.add('hidden');
            }
        };

        monthlyAmount?.addEventListener('input', updateCalculation);
        distributionType?.addEventListener('change', updateCalculation);
        roundToInteger?.addEventListener('change', updateCalculation);

        updateCalculation();
    })();
</script>
