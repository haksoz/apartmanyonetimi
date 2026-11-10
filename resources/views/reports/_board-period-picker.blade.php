<dialog id="board-period-dialog" aria-labelledby="board-period-title" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl border-0 bg-white p-5 text-slate-900 shadow-xl backdrop:bg-slate-950/50">
    <div class="mb-5 flex items-center justify-between gap-3">
        <h2 id="board-period-title" class="text-base font-semibold">Dönem Seç</h2>
        <button type="button" id="board-period-close" aria-label="Kapat" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">✕</button>
    </div>
    <div class="mb-4 flex items-center justify-between gap-3">
        <button type="button" id="board-year-previous" aria-label="Önceki yıl" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50 disabled:opacity-40">←</button>
        <label class="flex items-center gap-2 text-sm font-medium">Yıl
            <input type="number" id="board-picker-year" min="1" max="{{ now()->year }}" required class="w-24 rounded-lg border border-slate-300 px-3 py-2 text-center text-base">
        </label>
        <button type="button" id="board-year-next" aria-label="Sonraki yıl" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50 disabled:opacity-40">→</button>
    </div>
    <div class="grid grid-cols-3 gap-2" role="group" aria-label="Ay seçimi">
        @foreach ($trMonths as $number => $label)
            <button type="button" data-board-month="{{ $number }}" aria-pressed="false" class="rounded-xl border border-slate-200 px-2 py-3 text-sm hover:bg-slate-50 aria-pressed:font-semibold data-[selection=pending]:border-blue-600 data-[selection=pending]:bg-blue-50 data-[selection=pending]:text-blue-700 data-[selection=complete]:border-emerald-600 data-[selection=complete]:bg-emerald-50 data-[selection=complete]:text-emerald-700">{{ $label }}</button>
        @endforeach
    </div>
    <p class="mt-3 text-xs text-slate-500">Önce başlangıç, sonra bitiş ayına dokunun. Tek ay için aynı aya iki kez dokunabilirsiniz.</p>
    <p id="board-picker-selection" class="mt-3 text-sm font-medium" aria-live="polite"></p>
    <button type="button" id="board-picker-apply" class="mt-4 w-full rounded-xl bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white">Uygula</button>
    <button type="button" id="board-picker-current" class="mt-4 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold hover:bg-slate-50">Bu Ay</button>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('board-period-dialog');
        const opener = document.getElementById('board-period-open');
        const year = document.getElementById('board-year');
        const month = document.getElementById('board-month');
        const endInput = document.getElementById('board-month-end');
        const apply = document.getElementById('board-picker-apply');
        const selection = document.getElementById('board-picker-selection');
        let startKey, endKey, awaitingEnd = false;
        const key = (y, m) => `${String(y).padStart(4, '0')}-${String(m).padStart(2, '0')}`;
        const label = value => `${months[Number(value.slice(5))]} ${Number(value.slice(0, 4))}`;
        const pickerYear = document.getElementById('board-picker-year');
        const previous = document.getElementById('board-year-previous');
        const next = document.getElementById('board-year-next');
        const months = @json($trMonths);
        const current = @json(['year' => now()->year, 'month' => now()->month]);
        const buttons = dialog.querySelectorAll('[data-board-month]');
        const refresh = () => {
            selection.textContent = `${label(startKey)}${startKey !== endKey ? ' – ' + label(endKey) : ''}${awaitingEnd ? ' · Bitiş ayını seçin' : ' · Seçim tamamlandı'}`;
            selection.classList.toggle('text-blue-700', awaitingEnd);
            selection.classList.toggle('text-emerald-700', !awaitingEnd);
            previous.disabled = Number(pickerYear.value) <= 1;
            next.disabled = Number(pickerYear.value) >= current.year;
            buttons.forEach(button => {
                const buttonMonth = Number(button.dataset.boardMonth);
                const isFuture = Number(pickerYear.value) > current.year
                    || (Number(pickerYear.value) === current.year && buttonMonth > current.month);
                button.disabled = isFuture;
                button.classList.toggle('cursor-not-allowed', isFuture);
                button.classList.toggle('opacity-35', isFuture);
                const selected = key(Number(pickerYear.value), buttonMonth) >= startKey
                    && key(Number(pickerYear.value), buttonMonth) <= endKey;
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
                button.setAttribute('data-selection', selected ? (awaitingEnd ? 'pending' : 'complete') : 'none');
            });
        };
        const commit = () => {
            year.value = Number(startKey.slice(0, 4));
            month.value = Number(startKey.slice(5));
            endInput.value = endKey;
            document.getElementById('board-period-text').textContent = label(startKey) + (startKey !== endKey ? ' – ' + label(endKey) : '');
            dialog.close();
        };
        const choose = (selectedYear, selectedMonth) => {
            if (selectedYear > current.year || (selectedYear === current.year && selectedMonth > current.month)) return;
            const selected = key(selectedYear, selectedMonth);
            if (!awaitingEnd) {
                startKey = endKey = selected;
                awaitingEnd = true;
            } else {
                endKey = selected;
                if (endKey < startKey) [startKey, endKey] = [endKey, startKey];
                awaitingEnd = false;
            }
            refresh();
        };
        apply.addEventListener('click', commit);
        opener.addEventListener('click', () => {
            startKey = key(Number(year.value), Number(month.value));
            endKey = endInput.value;
            awaitingEnd = false;
            pickerYear.value = year.value;
            refresh();
            dialog.showModal();
        });
        document.getElementById('board-period-close').addEventListener('click', () => dialog.close());
        pickerYear.addEventListener('input', refresh);
        pickerYear.addEventListener('change', () => {
            if (Number(pickerYear.value) > current.year) pickerYear.value = current.year;
            refresh();
        });
        previous.addEventListener('click', () => { if (pickerYear.reportValidity()) { pickerYear.stepDown(); refresh(); } });
        next.addEventListener('click', () => { if (pickerYear.reportValidity()) { pickerYear.stepUp(); refresh(); } });
        buttons.forEach(button => button.addEventListener('click', () => {
            if (pickerYear.reportValidity()) choose(Number(pickerYear.value), Number(button.dataset.boardMonth));
        }));
        document.getElementById('board-picker-current').addEventListener('click', () => { startKey = endKey = key(current.year, current.month); commit(); });
        dialog.addEventListener('click', event => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
        });
    })();
</script>
