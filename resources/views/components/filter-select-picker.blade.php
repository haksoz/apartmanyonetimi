<dialog id="filter-select-dialog" aria-labelledby="filter-select-title" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl border-0 bg-white p-5 text-slate-900 shadow-xl backdrop:bg-slate-950/50">
    <div class="mb-5 flex items-center justify-between gap-3">
        <h2 id="filter-select-title" class="text-base font-semibold"></h2>
        <button type="button" data-select-close aria-label="Kapat" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">✕</button>
    </div>
    <div data-select-options role="group" class="grid max-h-[min(20.5rem,55dvh)] gap-2 overflow-y-auto overscroll-contain pr-1"></div>
    <p data-select-hint class="mt-3 text-center text-xs text-slate-400" hidden>Diğer seçenekler için kaydırın ↓</p>
    <button type="button" data-select-clear class="mt-4 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold hover:bg-slate-50"></button>
</dialog>
<script>
    (() => {
        const form = document.getElementById('filter-form');
        const dialog = document.getElementById('filter-select-dialog');
        const list = dialog.querySelector('[data-select-options]');
        const clear = dialog.querySelector('[data-select-clear]');
        const controls = [];
        let active;
        const refresh = () => controls.forEach(({ select, opener, text }) => {
            text.textContent = select.selectedOptions[0]?.textContent.trim() || select.options[0].textContent.trim();
            opener.disabled = select.disabled;
        });
        const choose = value => {
            active.value = value;
            active.dispatchEvent(new Event('change', { bubbles: true }));
            refresh();
            dialog.close();
        };
        form.querySelectorAll('select').forEach((select, index) => {
            const label = select.parentElement.querySelector('label');
            const title = label.textContent.trim();
            const opener = document.createElement('button');
            opener.type = 'button';
            opener.id = `filter-choice-${index}`;
            opener.setAttribute('aria-haspopup', 'dialog');
            opener.setAttribute('aria-controls', dialog.id);
            opener.className = 'flex w-full items-center justify-between gap-3 rounded-xl border border-slate-300 bg-white px-3 py-3 text-left text-base transition hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300 sm:py-2 sm:text-sm disabled:opacity-50';
            const text = document.createElement('span');
            text.className = 'truncate';
            opener.append(text);
            const arrow = document.createElement('span');
            arrow.textContent = '⌄';
            arrow.setAttribute('aria-hidden', 'true');
            arrow.className = 'shrink-0 text-slate-400';
            opener.append(arrow);
            label.id ||= `filter-choice-label-${index}`;
            label.htmlFor = opener.id;
            text.id = `filter-choice-text-${index}`;
            opener.setAttribute('aria-labelledby', `${label.id} ${text.id}`);
            select.hidden = true;
            select.after(opener);
            controls.push({ select, opener, text });
            opener.addEventListener('click', () => {
                active = select;
                document.getElementById('filter-select-title').textContent = `${title} Seç`;
                list.setAttribute('aria-label', `${title} seçimi`);
                list.replaceChildren();
                const options = [...select.options].filter(option => option !== select.options[0] && option.value && !option.hidden && !option.disabled);
                options.forEach(option => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'flex h-12 shrink-0 items-center justify-between gap-3 rounded-xl border border-slate-200 px-3 text-left text-sm transition hover:border-emerald-300 hover:bg-emerald-50/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 aria-pressed:border-emerald-600 aria-pressed:bg-emerald-50 aria-pressed:font-semibold aria-pressed:text-emerald-700';
                    button.setAttribute('aria-pressed', option.selected ? 'true' : 'false');
                    const name = document.createElement('span');
                    name.className = 'truncate';
                    name.textContent = option.textContent.trim();
                    button.append(name);
                    if (option.selected) {
                        const check = document.createElement('span');
                        check.textContent = '✓';
                        check.setAttribute('aria-hidden', 'true');
                        button.append(check);
                    }
                    button.addEventListener('click', () => choose(option.value));
                    list.append(button);
                });
                if (!options.length) {
                    const empty = document.createElement('p');
                    empty.className = 'py-6 text-center text-sm text-slate-500';
                    empty.textContent = 'Seçilebilir kayıt bulunmuyor.';
                    list.append(empty);
                }
                clear.textContent = select.options[0].textContent.trim();
                dialog.querySelector('[data-select-hint]').hidden = options.length <= 6;
                dialog.showModal();
                const selected = list.querySelector('[aria-pressed="true"]');
                (selected || list.querySelector('button') || clear).focus({ preventScroll: true });
                if (selected) selected.scrollIntoView({ block: 'nearest' });
                else list.scrollTop = 0;
            });
        });
        form.addEventListener('change', () => queueMicrotask(refresh));
        clear.addEventListener('click', () => choose(active.options[0].value));
        dialog.querySelector('[data-select-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('keydown', event => { if (event.key === 'Escape') event.stopPropagation(); });
        dialog.addEventListener('click', event => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
        });
        refresh();
    })();
</script>
