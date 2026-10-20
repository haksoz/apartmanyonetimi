<div class="mb-5 border-b border-slate-100 pb-5">
    <h3 class="mb-3 text-sm font-semibold text-slate-700">Gider Oluşturma Yöntemi</h3>
    <div class="flex flex-col gap-3 sm:flex-row">
        <label class="flex-1 cursor-pointer">
            <input type="radio" name="generation_mode" value="on_schedule" @checked(old('generation_mode', $plan->generation_mode ?? 'on_schedule') === 'on_schedule') class="peer sr-only">
            <span class="block h-full rounded-lg border border-slate-200 p-3 transition-all hover:bg-slate-50 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-600">
                <span class="block text-sm font-semibold text-slate-800">Zamanı geldikçe oluştur</span>
                <span class="mt-0.5 block text-xs text-slate-500">Giderler her ay seçilen oluşturma gününde oluşur.</span>
            </span>
        </label>
        <label class="flex-1 cursor-pointer">
            <input type="radio" name="generation_mode" value="create_all_now" @checked(old('generation_mode', $plan->generation_mode ?? 'on_schedule') === 'create_all_now') class="peer sr-only">
            <span class="block h-full rounded-lg border border-slate-200 p-3 transition-all hover:bg-slate-50 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-600">
                <span class="block text-sm font-semibold text-slate-800">Tümünü şimdi oluştur</span>
                <span class="mt-0.5 block text-xs text-slate-500">Bitiş tarihine kadar olan giderler kaydedince oluşur.</span>
            </span>
        </label>
    </div>
    @error('generation_mode')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
    <div id="past-periods-field" class="mt-3 hidden rounded-xl border border-amber-200 bg-amber-50 p-3">
        <label class="flex cursor-pointer items-start gap-2 text-sm text-amber-900">
            <input id="include_past_periods" name="include_past_periods" type="checkbox" value="1" @checked(old('include_past_periods', false)) class="mt-0.5 rounded border-amber-300">
            Geçmiş aylara ait giderleri de oluştur
        </label>
        <p class="mt-1 text-xs text-amber-800">İşaretlemezseniz bu aydan önceki dönemler atlanır.</p>
    </div>
    @error('include_past_periods')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modes = document.querySelectorAll('input[name="generation_mode"]');
        const start = document.getElementById('start_date');
        const end = document.getElementById('end_date');
        const amount = document.getElementById('amount');
        const active = document.getElementById('is_active');
        const includePast = document.getElementById('include_past_periods');
        const pastField = document.getElementById('past-periods-field');
        const summary = document.getElementById('generation-summary');
        const button = document.getElementById('save-expense-plan');
        const endHint = document.getElementById('end-date-hint');
        const existing = @json(isset($plan) ? $plan->expenses()->whereNotNull('plan_occurrence_key')->pluck('plan_occurrence_key') : []);
        const currentPeriod = @json(now()->format('Y-m'));
        const serial = value => {
            const [year, month] = value.slice(0, 7).split('-').map(Number);
            return year && month >= 1 && month <= 12 ? year * 12 + month - 1 : null;
        };
        const money = new Intl.NumberFormat('tr-TR', {style: 'currency', currency: 'TRY'});
        const update = () => {
            const bulk = document.querySelector('input[name="generation_mode"]:checked')?.value === 'create_all_now';
            const first = serial(start.value);
            const last = serial(end.value);
            const current = serial(currentPeriod);
            const hasPast = first !== null && first < current;
            end.required = bulk;
            end.min = start.value;
            endHint.textContent = bulk ? '(zorunlu)' : '(opsiyonel)';
            pastField.classList.toggle('hidden', !bulk || !hasPast);
            includePast.disabled = !bulk || !hasPast;
            button.textContent = bulk && active.checked ? 'Kaydet ve Giderleri Oluştur' : @json(isset($plan) ? 'Değişiklikleri Kaydet' : 'Planı Kaydet');
            if (!active.checked) {
                summary.textContent = 'Plan pasif kaydedilecek. Gider oluşturulmayacak.';
                return;
            }
            if (!bulk) {
                summary.textContent = 'Giderler zamanı geldikçe oluşturulacak. Mevcut giderler değişmez.';
                return;
            }
            if (first === null || last === null || last < first) {
                summary.textContent = 'Toplu oluşturma için geçerli bir başlangıç ve bitiş tarihi seçin.';
                return;
            }
            const eligibleStart = includePast.checked && !includePast.disabled ? first : Math.max(first, current);
            const total = Math.max(0, last - eligibleStart + 1);
            const existingCount = existing.filter(key => {
                const period = serial(key.split(':')[1]);
                return period !== null && period >= eligibleStart && period <= last;
            }).length;
            const count = total - existingCount;
            const cents = Math.round(Number(amount.value || 0) * 100);
            const excluded = Math.max(0, Math.min(last + 1, eligibleStart) - first);
            summary.textContent = start.value.slice(0, 7) + ' – ' + end.value.slice(0, 7) + ': ' + count + ' yeni gider, toplam ' + money.format(count * cents / 100) + '. ' + existingCount + ' mevcut, ' + excluded + ' geçmiş dönem atlanacak.';
        };
        modes.forEach(mode => mode.addEventListener('change', update));
        [start, end, amount, active, includePast].forEach(input => input.addEventListener('input', update));
        update();
    });
</script>
