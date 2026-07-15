<input type="hidden" name="name" value="Aidat Kararı">
<input type="hidden" name="auto_generate" value="1">
<input type="hidden" name="amount_type" value="monthly">
<input type="hidden" name="due_type" value="aidat">
<input type="hidden" name="category_id" value="">

<div class="space-y-4">
    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Plan Dönemi</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="start_date" class="block text-sm font-medium text-slate-600 mb-1">Başlangıç Tarihi</label>
                <input type="date" id="start_date" name="start_date"
                       value="{{ old('start_date', $plan?->start_date?->format('Y-m-d')) }}"
                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none"
                       required>
                @error('start_date')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="end_date" class="block text-sm font-medium text-slate-600 mb-1">Bitiş Tarihi</label>
                <input type="date" id="end_date" name="end_date"
                       value="{{ old('end_date', $plan?->end_date?->format('Y-m-d')) }}"
                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none"
                       required>
                @error('end_date')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Vade ve Oluşturma Günleri</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="generate_day" class="block text-sm font-medium text-slate-600 mb-1">Aidat Oluşturma Günü (1-28)</label>
                <input type="number" id="generate_day" name="generate_day"
                       value="{{ old('generate_day', $plan?->generate_day ?? 1) }}"
                       min="1" max="28" required
                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                <p class="mt-1 text-xs text-slate-500">Sistem her ayın bu gününde aidatı otomatik oluşturur.</p>
                @error('generate_day')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="due_day" class="block text-sm font-medium text-slate-600 mb-1">Vade Günü (1-28)</label>
                <input type="number" id="due_day" name="due_day"
                       value="{{ old('due_day', $plan?->due_day ?? 1) }}"
                       min="1" max="28" required
                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                @error('due_day')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Tutar Girişi</h3>
        <div class="md:w-1/2">
            <label for="monthly_amount" class="mb-2 block text-sm font-medium text-slate-600">Dağıtılacak Toplam Aylık Tutar</label>
            <input id="monthly_amount" name="monthly_amount" type="number" min="0.01" step="0.01"
                   value="{{ old('monthly_amount', $plan?->monthly_amount) }}"
                   class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none text-lg font-semibold"
                   placeholder="0,00" required>
            @error('monthly_amount')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-slate-700">Borç Bilgileri</h3>
            <div id="calc-summary" class="text-sm font-medium text-red-600 hidden"></div>
        </div>

        <div class="mb-4">
            <label class="mb-2 block text-sm font-medium text-slate-600">Dağıtım Yöntemi</label>
            <select name="distribution_type" id="distribution_type" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                <option value="equal" @selected(old('distribution_type', $plan?->distribution_type ?? 'equal') === 'equal')>Eşit dağıtım</option>
                <option value="square_meters" @selected(old('distribution_type', $plan?->distribution_type) === 'square_meters')>Metrekareye göre</option>
                <option value="share_coefficient" @selected(old('distribution_type', $plan?->distribution_type) === 'share_coefficient')>Pay çarpanına göre</option>
            </select>
            @error('distribution_type')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div id="distribution-preview" class="hidden mb-4">
            <div class="rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dağıtım Önizlemesi</span>
                    <span id="preview-total-label" class="text-xs text-slate-500"></span>
                </div>
                <div id="preview-warning" class="hidden px-4 py-2 bg-amber-50 border-b border-amber-200 text-xs text-amber-700"></div>
                <div id="preview-groups" class="divide-y divide-slate-100"></div>
            </div>
        </div>

        <div class="mb-4">
            <label class="mb-2 block text-sm font-medium text-slate-600">Borçlanacak Kişiler</label>
            <div class="flex gap-3">
                <label class="cursor-pointer flex-1">
                    <input type="radio" name="target_audience" value="tenant_priority" class="peer sr-only" @checked(old('target_audience', $plan?->target_audience ?? 'tenant_priority') === 'tenant_priority')>
                    <div class="rounded-xl border-2 border-slate-200 p-3 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50">
                        <div class="font-semibold text-slate-800 peer-checked:text-emerald-700 text-sm">Kiracı Öncelikli</div>
                        <div class="text-xs text-slate-500 mt-1">Varsa Kiracıya, yoksa sahibine</div>
                    </div>
                </label>
                <label class="cursor-pointer flex-1">
                    <input type="radio" name="target_audience" value="owner_only" class="peer sr-only" @checked(old('target_audience', $plan?->target_audience) === 'owner_only')>
                    <div class="rounded-xl border-2 border-slate-200 p-3 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50">
                        <div class="font-semibold text-slate-800 peer-checked:text-emerald-700 text-sm">Sadece Sahipler</div>
                        <div class="text-xs text-slate-500 mt-1">Tüm borçlar kat maliklerine</div>
                    </div>
                </label>
            </div>
            @error('target_audience')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

    </div>

    <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 mb-2">Oluşacak Aidat Açıklaması</p>
        <p class="text-sm text-emerald-900">
            Her ay aidat oluşturulduğunda açıklama şu şekilde otomatik yazılır:<br>
            <span class="font-medium">"Temmuz 2026 - Aidat"</span>
        </p>
        <p class="mt-2 text-xs text-emerald-700">
            Ay ve yıl, oluşturulan döneme göre değişir.
        </p>
    </div>

    <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div>
            <p class="text-sm font-medium text-slate-700">Aktif / Pasif</p>
            <p class="text-xs text-slate-500 mt-0.5">Plan aktifken aidatlar otomatik oluşturulur.</p>
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
        const unitsData = [
            @foreach ($units as $u)
            {
                label: {!! json_encode(($u->block ? $u->block . '/' : '') . $u->unit_no) !!},
                sqm: {!! json_encode($u->square_meters) !!},
                coef: {!! json_encode($u->share_coefficient) !!}
            }@if (!$loop->last),@endif
            @endforeach
        ];

        const monthlyAmount = document.getElementById('monthly_amount');
        const distributionType = document.getElementById('distribution_type');
        const calcSummary = document.getElementById('calc-summary');

        const formatMoney = (amount) => {
            return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + ' TL';
        };

        const updateDistributionPreview = (total) => {
            const distType = distributionType.value;
            const previewEl = document.getElementById('distribution-preview');
            const groupsEl = document.getElementById('preview-groups');
            const warningEl = document.getElementById('preview-warning');
            const totalLbl = document.getElementById('preview-total-label');

            if (! total || total <= 0 || ! unitsData.length) {
                previewEl.classList.add('hidden');
                return;
            }

            const activeUnits = unitsData.filter(u => {
                if (distType === 'square_meters') return u.sqm > 0;
                if (distType === 'share_coefficient') return u.coef > 0;
                return true;
            });

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

            const totalWeight = activeUnits.reduce((s, u) => {
                if (distType === 'square_meters') return s + u.sqm;
                if (distType === 'share_coefficient') return s + u.coef;
                return s + 1;
            }, 0);

            let shares = [];
            let allocated = 0;
            activeUnits.forEach((u, idx) => {
                const w = distType === 'equal' ? 1 : (distType === 'square_meters' ? u.sqm : u.coef);
                const share = idx === activeUnits.length - 1
                    ? Math.round((total - allocated) * 100) / 100
                    : Math.round(total * w / totalWeight * 100) / 100;
                allocated += share;
                shares.push({ unit: u, w, share });
            });

            const groups = {};
            shares.forEach(s => {
                const key = distType === 'equal' ? 'equal' : s.w.toString();
                if (! groups[key]) groups[key] = { w: s.w, share: s.share, count: 0 };
                groups[key].count++;
            });

            const sortedGroups = Object.values(groups).sort((a, b) => a.w - b.w);

            let html = '';
            sortedGroups.forEach(g => {
                let weightLabel = distType === 'equal' ? 'Eşit dağıtım'
                    : distType === 'square_meters' ? g.w.toLocaleString('tr-TR') + ' m²'
                    : g.w.toLocaleString('tr-TR') + ' çarpan';
                html += `<div class="flex items-center justify-between px-4 py-3 text-sm">
                    <div class="text-slate-700"><span class="font-medium">${weightLabel}</span> — <span class="text-slate-500">${g.count} daire</span></div>
                    <div class="font-bold text-slate-900 tabular-nums">${formatMoney(g.share)} / daire</div>
                </div>`;
            });

            groupsEl.innerHTML = html;
            totalLbl.textContent = formatMoney(total) + ' · ' + activeUnits.length + ' daire';
            previewEl.classList.remove('hidden');
        };

        const updateCalculation = () => {
            const total = parseFloat(monthlyAmount?.value?.replace(',', '.')) || 0;
            updateDistributionPreview(total);

            if (unitsData.length && total > 0) {
                const perUnit = total / unitsData.length;
                calcSummary.textContent = `Toplam: ${formatMoney(total)} / Daire: ${formatMoney(perUnit)}`;
                calcSummary.classList.remove('hidden');
            } else {
                calcSummary.classList.add('hidden');
            }
        };

        monthlyAmount?.addEventListener('input', updateCalculation);
        distributionType?.addEventListener('change', updateCalculation);

        updateCalculation();
    })();
</script>
