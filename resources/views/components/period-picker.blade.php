<dialog id="{{ $periodPickerId }}-period-dialog" aria-labelledby="{{ $periodPickerId }}-period-title" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl border-0 bg-white p-5 text-slate-900 shadow-xl backdrop:bg-slate-950/50">
    <div class="mb-5 flex items-center justify-between gap-3">
        <h2 id="{{ $periodPickerId }}-period-title" class="text-base font-semibold">Tarih/Dönem Seç</h2>
        <button type="button" data-period-close aria-label="Kapat" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">✕</button>
    </div>
    <div class="mb-4 flex items-center justify-between gap-3">
        <button type="button" data-period-previous aria-label="Önceki yıl" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50 disabled:opacity-40">←</button>
        <label class="flex items-center gap-2 text-sm font-medium">Yıl
            <input type="number" data-period-year min="1" max="9999" required class="w-24 rounded-lg border border-slate-300 px-3 py-2 text-center text-base">
        </label>
        <button type="button" data-period-next aria-label="Sonraki yıl" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50 disabled:opacity-40">→</button>
    </div>
    <div class="grid grid-cols-3 gap-2" role="group" aria-label="Ay seçimi">
        @foreach (['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'] as $index => $label)
            <button type="button" data-period-month="{{ $index + 1 }}" aria-pressed="false" class="rounded-xl border border-slate-200 px-2 py-3 text-sm hover:bg-slate-50 aria-pressed:border-emerald-600 aria-pressed:bg-emerald-50 aria-pressed:font-semibold aria-pressed:text-emerald-700">{{ $label }}</button>
        @endforeach
    </div>
    <p data-period-selection class="mt-3 text-sm font-medium text-emerald-700" aria-live="polite"></p>
    <button type="button" data-period-apply class="mt-4 w-full rounded-xl bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white">Uygula</button>
    <button type="button" data-period-current class="mt-4 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold hover:bg-slate-50">Bu Ay</button>
    <button type="button" data-period-clear class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold hover:bg-slate-50">Tüm Dönemler</button>
</dialog>
<script>
    (() => {
        const prefix = @json($periodPickerId);
        const dialog = document.getElementById(`${prefix}-period-dialog`);
        const input = document.getElementById(`${prefix}-period`);
        const year = dialog.querySelector('[data-period-year]');
        const buttons = [...dialog.querySelectorAll('[data-period-month]')];
        const current = @json(now()->format('Y-m'));
        let selected;
        const label = value => value ? `${buttons[Number(value.slice(5)) - 1].textContent} ${Number(value.slice(0, 4))}` : 'Tüm Dönemler';
        const refresh = () => {
            dialog.querySelector('[data-period-selection]').textContent = label(selected);
            dialog.querySelector('[data-period-previous]').disabled = Number(year.value) <= 1;
            dialog.querySelector('[data-period-next]').disabled = Number(year.value) >= 9999;
            buttons.forEach(button => button.setAttribute('aria-pressed', selected === `${String(year.value).padStart(4, '0')}-${button.dataset.periodMonth.padStart(2, '0')}` ? 'true' : 'false'));
        };
        const commit = value => {
            input.value = value;
            document.getElementById(`${prefix}-period-text`).textContent = label(value);
            dialog.close();
        };
        document.getElementById(`${prefix}-period-open`).addEventListener('click', () => {
            selected = input.value;
            year.value = Number((selected || current).slice(0, 4));
            refresh();
            dialog.showModal();
        });
        dialog.querySelector('[data-period-close]').addEventListener('click', () => dialog.close());
        year.addEventListener('input', refresh);
        dialog.querySelector('[data-period-previous]').addEventListener('click', () => { if (year.reportValidity()) { year.stepDown(); refresh(); } });
        dialog.querySelector('[data-period-next]').addEventListener('click', () => { if (year.reportValidity()) { year.stepUp(); refresh(); } });
        buttons.forEach(button => button.addEventListener('click', () => {
            if (!year.reportValidity()) return;
            selected = `${String(year.value).padStart(4, '0')}-${button.dataset.periodMonth.padStart(2, '0')}`;
            refresh();
        }));
        dialog.querySelector('[data-period-apply]').addEventListener('click', () => commit(selected));
        dialog.querySelector('[data-period-current]').addEventListener('click', () => commit(current));
        dialog.querySelector('[data-period-clear]').addEventListener('click', () => commit(''));
        dialog.addEventListener('keydown', event => { if (event.key === 'Escape') event.stopPropagation(); });
        dialog.addEventListener('click', event => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
        });
    })();
</script>
