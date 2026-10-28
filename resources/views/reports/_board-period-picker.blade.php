<dialog id="board-period-dialog" aria-labelledby="board-period-title" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl border-0 bg-white p-5 text-slate-900 shadow-xl backdrop:bg-slate-950/50">
    <div class="mb-5 flex items-center justify-between gap-3">
        <h2 id="board-period-title" class="text-base font-semibold">Dönem Seç</h2>
        <button type="button" id="board-period-close" aria-label="Kapat" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">✕</button>
    </div>
    <div class="mb-4 flex items-center justify-between gap-3">
        <button type="button" id="board-year-previous" aria-label="Önceki yıl" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50 disabled:opacity-40">←</button>
        <label class="flex items-center gap-2 text-sm font-medium">Yıl
            <input type="number" id="board-picker-year" min="1" max="9999" required class="w-24 rounded-lg border border-slate-300 px-3 py-2 text-center text-base">
        </label>
        <button type="button" id="board-year-next" aria-label="Sonraki yıl" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50 disabled:opacity-40">→</button>
    </div>
    <div class="grid grid-cols-3 gap-2" role="group" aria-label="Ay seçimi">
        @foreach ($trMonths as $number => $label)
            <button type="button" data-board-month="{{ $number }}" aria-pressed="false" class="rounded-xl border border-slate-200 px-2 py-3 text-sm hover:bg-slate-50 aria-pressed:border-indigo-600 aria-pressed:bg-indigo-50 aria-pressed:font-semibold aria-pressed:text-indigo-700">{{ $label }}</button>
        @endforeach
    </div>
    <p class="mt-3 text-xs text-slate-500">Yılı belirleyip aya dokunun.</p>
    <button type="button" id="board-picker-current" class="mt-4 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold hover:bg-slate-50">Bu Ay</button>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('board-period-dialog');
        const opener = document.getElementById('board-period-open');
        const year = document.getElementById('board-year');
        const month = document.getElementById('board-month');
        const pickerYear = document.getElementById('board-picker-year');
        const previous = document.getElementById('board-year-previous');
        const next = document.getElementById('board-year-next');
        const months = @json($trMonths);
        const current = @json(['year' => now()->year, 'month' => now()->month]);
        const buttons = dialog.querySelectorAll('[data-board-month]');
        const refresh = () => {
            previous.disabled = Number(pickerYear.value) <= 1;
            next.disabled = Number(pickerYear.value) >= 9999;
            buttons.forEach(button => button.setAttribute('aria-pressed',
                Number(pickerYear.value) === Number(year.value) && Number(button.dataset.boardMonth) === Number(month.value) ? 'true' : 'false'));
        };
        const choose = (selectedYear, selectedMonth) => {
            year.value = selectedYear;
            month.value = selectedMonth;
            document.getElementById('board-period-text').textContent = `${months[selectedMonth]} ${selectedYear}`;
            dialog.close();
        };
        opener.addEventListener('click', () => {
            pickerYear.value = year.value;
            refresh();
            dialog.showModal();
        });
        document.getElementById('board-period-close').addEventListener('click', () => dialog.close());
        pickerYear.addEventListener('input', refresh);
        previous.addEventListener('click', () => { if (pickerYear.reportValidity()) { pickerYear.stepDown(); refresh(); } });
        next.addEventListener('click', () => { if (pickerYear.reportValidity()) { pickerYear.stepUp(); refresh(); } });
        buttons.forEach(button => button.addEventListener('click', () => {
            if (pickerYear.reportValidity()) choose(Number(pickerYear.value), Number(button.dataset.boardMonth));
        }));
        document.getElementById('board-picker-current').addEventListener('click', () => choose(current.year, current.month));
        dialog.addEventListener('click', event => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
        });
    })();
</script>
