@if ($plan?->exists)
    <input id="plan_year" name="plan_year" type="hidden" value="{{ $plan->plan_year }}">
    <input name="plan_id" type="hidden" value="{{ $plan->id }}">
@else
    <input name="creating_new" type="hidden" value="1">
@endif

<section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
    <h2 class="mb-4 text-sm font-semibold text-slate-700">Plan Bilgileri</h2>
    <div>
        <div>
            <p class="mb-2 text-sm font-medium text-slate-600">Plan Adı</p>
            <p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">{{ $plan?->name ?: 'Aidat Kararı' }}</p>
            <p class="mt-1 text-xs text-slate-500">Plan adı otomatik belirlenir.</p>
        </div>
    </div>
    <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-3">
    @unless ($plan?->exists)
        <div class="col-span-2 min-w-0 md:col-span-1">
            <label for="plan_year" class="mb-2 block text-sm font-medium text-slate-600">Takvim Yılı</label>
            <input id="plan_year" name="plan_year" type="number" min="2000" max="2100" required value="{{ old('plan_year', $selectedYear ?? now()->year) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
            <p class="mt-1 text-xs text-slate-500">1 Ocak–31 Aralık. Her yıl için tek aktif plan.</p>
            @error('plan_year')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @endunless

    @if ($plan?->exists)
        <div class="col-span-2 md:col-span-1"><p class="mb-2 text-sm font-medium text-slate-600">Takvim Yılı</p><p class="text-sm font-semibold text-slate-800">{{ $plan->plan_year }}</p><p class="mt-1 text-xs text-slate-500">1 Ocak–31 Aralık.</p></div>
    @endif
            <div>
                <label for="generate_day" class="mb-2 block text-sm font-medium text-slate-600">Oluşturma Günü</label>
                <input id="generate_day" name="generate_day" type="number" min="1" max="28" required value="{{ old('generate_day', $plan?->generate_day ?? 1) }}" aria-describedby="generate-day-help" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                <p id="generate-day-help" class="mt-1 text-xs text-slate-500">Her ayın 1–28. günü.</p>
                @error('generate_day')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="due_day" class="mb-2 block text-sm font-medium text-slate-600">Son Ödeme Günü</label>
                <input id="due_day" name="due_day" type="number" min="1" max="28" required value="{{ old('due_day', $plan?->due_day ?? 20) }}" aria-describedby="due-day-help" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                <p id="due-day-help" class="mt-1 text-xs text-slate-500">Her ayın 1–28. günü.</p>
                @error('due_day')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

    </div>
</section>
<section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
    <h2 class="mb-4 text-sm font-semibold text-slate-700">Açıklama</h2>
    <div class="rounded-xl bg-slate-50 p-3">
        <p class="mb-1 text-xs font-medium text-slate-500">Oluşacak Aidat Açıklaması</p>
        <p id="aidat-description-example" class="break-words text-sm font-semibold text-slate-800">{{ $plan?->plan_year ?? $selectedYear ?? now()->year }} {{ now()->locale('tr')->isoFormat('MMMM') }} - Aidatı</p>
        <p class="mt-1 text-xs text-slate-500">Ay ve yıl döneme göre güncellenir.</p>
    </div>
</section>
<section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
    <h2 class="mb-4 text-sm font-semibold text-slate-700">İşlem Bilgileri</h2>
    <div class="mb-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
            <div>
                <label for="monthly_amount" class="mb-2 block text-sm font-medium text-slate-600">Aylık Dağıtılacak Toplam Tutar</label>
                <div class="relative">
                    <input id="monthly_amount" name="monthly_amount" type="number" min="0.01" step="0.01" required value="{{ old('monthly_amount', $plan?->monthly_amount) }}" placeholder="0,00" class="w-full rounded-xl border border-slate-300 py-3 pl-4 pr-12 text-sm font-semibold focus:border-emerald-500 focus:outline-none">
                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-slate-400">₺</span>
                </div>
                @error('monthly_amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="min-w-0">
                <label for="distribution_type" class="mb-2 block text-sm font-medium text-slate-600">Dağıtım Yöntemi</label>
                <select id="distribution_type" name="distribution_type" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                    <option value="equal" @selected(old('distribution_type', $plan?->distribution_type ?? 'equal') === 'equal')>Eşit dağıtım</option>
                    <option value="square_meters" @selected(old('distribution_type', $plan?->distribution_type) === 'square_meters')>Metrekareye göre</option>
                    <option value="share_coefficient" @selected(old('distribution_type', $plan?->distribution_type) === 'share_coefficient')>Pay çarpanına göre</option>
                </select>
            </div>
            <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 whitespace-nowrap py-3 text-sm text-slate-600 md:mt-7">
                <input id="round_to_integer" name="round_to_integer" type="checkbox" value="1" @checked(old('round_to_integer', $plan?->round_to_integer)) class="h-4 w-4 rounded border-slate-300 accent-emerald-600">
                Tamsayıya yuvarla
            </label>
    </div>
    <div class="space-y-3">
        @error('distribution_type')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @error('round_to_integer')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <div id="calc-summary" class="hidden text-sm font-medium text-slate-600"></div>
        <div id="distribution-preview" class="hidden overflow-hidden rounded-xl border border-slate-200">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
                <span class="text-xs font-semibold text-slate-600">Dağıtım Önizlemesi</span>
                <span id="preview-total-label" class="text-xs text-slate-500"></span>
            </div>
            <div id="preview-warning" class="hidden border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-700"></div>
            <div id="preview-groups" class="divide-y divide-slate-100"></div>
        </div>
    </div>

    <div class="mt-4 rounded-xl bg-slate-50 p-3">
        <p class="mb-1 text-xs font-medium text-slate-500">Borçlanacak Kişiler</p>
        <p class="text-sm text-slate-700">İlgili dönemde kiracı varsa kiracıya, yoksa malike borçlandırılır.</p>
        <p class="mt-2 text-xs text-slate-500">Borç türü ve kategori: Aidat.</p>
    </div>
</section>
<section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
    <h2 class="mb-4 text-sm font-semibold text-slate-700">Oluşturma ve Durum</h2>
    <div class="mb-4">
        <p class="mb-2 text-sm font-medium text-slate-600">Oluşturma Yöntemi</p>
        <p class="inline-flex items-start gap-2 text-sm text-slate-700">@include('financial-plans._clock') <span>Zamanı geldikçe oluşturulur.</span></p>
        <p class="mt-1 text-xs text-slate-500">Aidatlar her ay oluşturma gününde otomatik oluşturulur.</p>
    </div>
    <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 p-4">
        <div>
            <label for="is_active" class="text-sm font-medium text-slate-700">Plan Durumu</label>
            <p id="active-help" class="mt-1 text-xs text-slate-500">Pasif planda aidat oluşturulmaz.</p>
        </div>
        <label for="is_active" class="relative inline-flex shrink-0 cursor-pointer items-center">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" name="is_active" type="checkbox" value="1" role="switch" aria-label="Plan aktif" aria-describedby="active-help" @checked(old('is_active', $plan?->is_active ?? true)) class="peer sr-only">
            <span aria-hidden="true" class="h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-slate-900 peer-focus-visible:ring-2 peer-focus-visible:ring-slate-500 peer-focus-visible:ring-offset-2 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full"></span>
        </label>
    </div>
    @error('is_active')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

</section>
<script>
    (() => {
        const planYearInput = document.getElementById('plan_year');
        const updateDescriptionExample = () => {
            const year = Number(planYearInput.value) || {{ now()->year }};
            document.getElementById('aidat-description-example').textContent = year + ' ' + @json(now()->locale('tr')->isoFormat('MMMM')) + ' - Aidatı';
        };
        planYearInput.addEventListener('input', updateDescriptionExample);
        updateDescriptionExample();

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
