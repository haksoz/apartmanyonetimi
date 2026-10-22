<div class="mb-4">
    <h3 class="mb-3 text-sm font-semibold text-slate-700">Oluşturma Yöntemi</h3>
    <div class="flex flex-col gap-3 sm:flex-row">
        <label class="flex-1 cursor-pointer">
            <input type="radio" name="generation_mode" value="on_schedule" @checked(old('generation_mode', $plan->generation_mode ?? 'on_schedule') === 'on_schedule') class="peer sr-only">
            <span class="block h-full rounded-xl border border-slate-200 p-3 transition-all hover:bg-slate-50 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-600">
                <span class="block text-sm font-semibold text-slate-800">Zamanı geldikçe oluştur</span>
                <span class="mt-0.5 block text-xs text-slate-500">Giderler her ay seçilen oluşturma gününde oluşur.</span>
            </span>
        </label>
        <label class="flex-1 cursor-pointer">
            <input type="radio" name="generation_mode" value="create_all_now" @checked(old('generation_mode', $plan->generation_mode ?? 'on_schedule') === 'create_all_now') class="peer sr-only">
            <span class="block h-full rounded-xl border border-slate-200 p-3 transition-all hover:bg-slate-50 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-600">
                <span class="block text-sm font-semibold text-slate-800">Tümünü şimdi oluştur</span>
                <span class="mt-0.5 block text-xs text-slate-500">Bitiş tarihine kadar olan giderler kaydedince oluşur.</span>
            </span>
        </label>
    </div>
    @error('generation_mode')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
    <p class="mt-3 text-xs text-slate-500">Geçmiş aylar ve oluşturulacak giderler, Kaydet sonrası işlem özetinde seçilir.</p>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const end = document.getElementById('end_date');
        const start = document.getElementById('start_date');
        const update = () => {
            const bulk = document.querySelector('input[name="generation_mode"]:checked')?.value === 'create_all_now';
            end.required = bulk;
            end.min = start.value;
            document.getElementById('end-date-hint').textContent = bulk ? '(zorunlu)' : '(opsiyonel)';
        };
        document.querySelectorAll('input[name="generation_mode"]').forEach(input => input.addEventListener('change', update));
        start.addEventListener('input', update);
        update();
    });
</script>
