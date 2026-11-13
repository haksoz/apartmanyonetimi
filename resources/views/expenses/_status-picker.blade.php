<dialog id="expense-status-dialog" aria-labelledby="expense-status-title" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl border-0 bg-white p-5 text-slate-900 shadow-xl backdrop:bg-slate-950/50">
    <div class="mb-5 flex items-center justify-between gap-3">
        <h2 id="expense-status-title" class="text-base font-semibold">Durum Seç</h2>
        <button type="button" id="expense-status-close" aria-label="Kapat" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">✕</button>
    </div>
    <div class="grid grid-cols-3 gap-2" role="group" aria-label="Durum seçimi">
        @foreach ($statusLabels as $value => $label)
            <button type="button" data-expense-status="{{ $value }}" aria-pressed="false" class="min-w-0 whitespace-nowrap rounded-xl border border-slate-200 px-1 py-3 text-xs sm:text-sm hover:bg-slate-50 aria-pressed:border-emerald-600 aria-pressed:bg-emerald-50 aria-pressed:font-semibold aria-pressed:text-emerald-700">{{ $label }}</button>
        @endforeach
    </div>
    <p class="mt-3 text-xs text-slate-500">Birden fazla durum seçebilirsiniz. Seçim yapılmazsa tüm durumlar gösterilir.</p>
    <button type="button" id="expense-status-apply" class="mt-4 w-full rounded-xl bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white">Uygula</button>
    <button type="button" id="expense-status-clear" class="mt-4 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold hover:bg-slate-50">Tüm Durumlar</button>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('expense-status-dialog');
        const inputs = document.getElementById('expense-status-inputs');
        const buttons = [...dialog.querySelectorAll('[data-expense-status]')];
        let selected = new Set();
        const refresh = () => buttons.forEach(button => button.setAttribute('aria-pressed', selected.has(button.dataset.expenseStatus) ? 'true' : 'false'));
        document.getElementById('expense-status-open').addEventListener('click', () => {
            selected = new Set([...inputs.querySelectorAll('input')].map(input => input.value));
            refresh();
            dialog.showModal();
        });
        buttons.forEach(button => button.addEventListener('click', () => {
            const value = button.dataset.expenseStatus;
            if (selected.has(value)) selected.delete(value);
            else selected.add(value);
            refresh();
        }));
        const commit = () => {
            const chosen = buttons.filter(button => selected.has(button.dataset.expenseStatus));
            inputs.replaceChildren(...chosen.map(button => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'status[]';
                input.value = button.dataset.expenseStatus;
                return input;
            }));
            document.getElementById('expense-status-text').textContent = chosen.map(button => button.textContent.trim()).join(', ') || 'Tüm Durumlar';
            dialog.close();
        };
        document.getElementById('expense-status-apply').addEventListener('click', commit);
        document.getElementById('expense-status-clear').addEventListener('click', () => { selected.clear(); commit(); });
        document.getElementById('expense-status-close').addEventListener('click', () => dialog.close());
        dialog.addEventListener('keydown', event => { if (event.key === 'Escape') event.stopPropagation(); });
        dialog.addEventListener('click', event => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
        });
    })();
</script>
