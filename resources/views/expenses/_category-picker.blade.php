<dialog id="expense-category-dialog" aria-labelledby="expense-category-title" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl border-0 bg-white p-5 text-slate-900 shadow-xl backdrop:bg-slate-950/50">
    <div class="mb-5 flex items-center justify-between gap-3">
        <div>
            <h2 id="expense-category-title" class="text-base font-semibold">Kategori Seç</h2>
            <p class="mt-1 text-xs text-slate-500">{{ $categories->count() }} kategori</p>
        </div>
        <button type="button" data-category-close aria-label="Kapat" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">✕</button>
    </div>
    <div class="grid max-h-[min(20.5rem,55dvh)] gap-2 overflow-y-auto overscroll-contain pr-1" role="group" aria-label="Kategori seçimi">
        @foreach ($categories as $id => $name)
            <button type="button" data-expense-category="{{ $id }}" aria-pressed="false" class="group flex h-12 shrink-0 items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 text-left text-sm transition hover:border-emerald-300 hover:bg-emerald-50/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 aria-pressed:border-emerald-600 aria-pressed:bg-emerald-50 aria-pressed:font-semibold aria-pressed:text-emerald-700">
                <span class="truncate">{{ $name }}</span>
                <svg class="h-5 w-5 shrink-0 opacity-0 group-aria-pressed:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
        @endforeach
    </div>
    @if ($categories->count() > 6)
        <p class="mt-3 text-center text-xs text-slate-400">Diğer kategoriler için kaydırın ↓</p>
    @endif
    <button type="button" data-category-clear class="mt-4 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold hover:bg-slate-50">Tüm Kategoriler</button>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('expense-category-dialog');
        const input = document.getElementById('expense-category');
        const buttons = [...dialog.querySelectorAll('[data-expense-category]')];
        const commit = (value, label) => {
            input.value = value;
            document.getElementById('expense-category-text').textContent = label;
            dialog.close();
        };
        document.getElementById('expense-category-open').addEventListener('click', () => {
            buttons.forEach(button => button.setAttribute('aria-pressed', button.dataset.expenseCategory === input.value ? 'true' : 'false'));
            dialog.showModal();
            const selected = buttons.find(button => button.dataset.expenseCategory === input.value);
            (selected || buttons[0])?.focus({ preventScroll: true });
            if (selected) selected.scrollIntoView({ block: 'nearest' });
            else dialog.querySelector('[role="group"]').scrollTop = 0;
        });
        buttons.forEach(button => button.addEventListener('click', () => commit(button.dataset.expenseCategory, button.querySelector('span').textContent.trim())));
        dialog.querySelector('[data-category-clear]').addEventListener('click', () => commit('', 'Tüm Kategoriler'));
        dialog.querySelector('[data-category-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('keydown', event => { if (event.key === 'Escape') event.stopPropagation(); });
        dialog.addEventListener('click', event => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
        });
    })();
</script>
