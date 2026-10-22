@php
    $accountDecisionSelector = $decisionSelector ?? 'input[name="current_month_action"], input[type="checkbox"][name="include_past_periods"]';
@endphp
<dialog id="period-accounts-dialog" aria-labelledby="period-accounts-title" aria-describedby="period-accounts-description" class="m-auto w-[calc(100%-2rem)] max-w-3xl overflow-hidden rounded-2xl border-0 bg-white p-0 shadow-xl backdrop:bg-slate-950/50">
    <div class="flex max-h-[85dvh] flex-col">
        <div class="flex items-start justify-between gap-3 border-b border-slate-100 p-4 sm:p-5">
            <div class="min-w-0">
                <h2 id="period-accounts-title" class="text-base font-semibold text-slate-900">Dönem Ayrıntıları</h2>
                <p id="period-accounts-description" class="mt-1 text-sm text-slate-500"></p>
            </div>
            <button id="close-period-accounts" type="button" autofocus aria-label="Ayrıntıları kapat" class="shrink-0 rounded-lg p-2 text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-slate-500">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M6 18 18 6" /></svg>
            </button>
        </div>
        <div id="period-accounts-scroll" class="min-h-0 overflow-y-auto">
            <table class="w-full text-left text-sm">
                <thead class="sticky top-0 bg-slate-50 text-xs text-slate-500"><tr><th scope="col" class="px-3 py-3 sm:px-4">#</th><th scope="col" class="px-3 py-3 sm:px-4">Daire / Hesap</th><th scope="col" class="px-3 py-3 text-right sm:px-4">{{ $amountLabel ?? 'Aidat Tutarı' }}</th></tr></thead>
                <tbody id="period-accounts-rows" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4">
            <p id="period-accounts-page" role="status" aria-live="polite" class="text-xs text-slate-500"></p>
            <div class="flex items-center gap-2">
                <button id="period-accounts-previous" type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40">Önceki</button>
                <button id="period-accounts-next" type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40">Sonraki</button>
            </div>
        </div>
    </div>
</dialog>
<script>
    (() => {
        const periods = @json($accountPeriods);
        const dialog = document.getElementById('period-accounts-dialog');
        const body = document.getElementById('period-accounts-rows');
        const previous = document.getElementById('period-accounts-previous');
        const next = document.getElementById('period-accounts-next');
        const pageSize = 15;
        const money = value => new Intl.NumberFormat('tr-TR', {style: 'currency', currency: 'TRY'}).format(value);
        let period;
        let page = 1;
        const render = () => {
            const accounts = period.accounts;
            const pages = Math.max(1, Math.ceil(accounts.length / pageSize));
            const start = (page - 1) * pageSize;
            body.replaceChildren();
            accounts.slice(start, start + pageSize).forEach((account, index) => {
                const row = document.createElement('tr');
                const number = document.createElement('td');
                number.className = 'px-3 py-3 text-xs tabular-nums text-slate-500 sm:px-4';
                number.textContent = start + index + 1;
                const label = document.createElement('td');
                label.className = 'break-words px-3 py-3 text-slate-700 sm:px-4';
                label.textContent = account.label;
                if (account.account_type_label) {
                    const accountType = document.createElement('p');
                    accountType.className = 'mt-1 text-xs text-slate-500';
                    accountType.textContent = account.account_type_label;
                    label.append(accountType);
                }
                if (account.note) {
                    const note = document.createElement('p');
                    note.className = 'mt-1 text-xs text-slate-500';
                    note.textContent = account.note;
                    label.append(note);
                }
                const amount = document.createElement('td');
                amount.className = 'whitespace-nowrap px-3 py-3 text-right font-medium tabular-nums text-slate-900 sm:px-4';
                amount.textContent = money(account.amount);
                row.append(number, label, amount);
                body.append(row);
            });
            previous.disabled = page === 1;
            next.disabled = page === pages;
            document.getElementById('period-accounts-page').textContent = (start + 1) + '–' + Math.min(start + pageSize, accounts.length) + ' / ' + accounts.length + ' kayıt · Sayfa ' + page + ' / ' + pages;
            document.getElementById('period-accounts-scroll').scrollTop = 0;
        };
        document.querySelectorAll('[data-period-accounts]').forEach(button => {
            button.addEventListener('click', () => {
                period = periods[button.dataset.periodAccounts];
                if (!period || !period.accounts.length) return;
                page = 1;
                document.getElementById('period-accounts-title').textContent = period.title ?? (period.label + ' · Aidat Ayrıntıları');
                document.getElementById('period-accounts-description').textContent = period.description ?? (period.accounts.length + ' yeni aidat · Toplam ' + money(period.amount));
                render();
                dialog.showModal();
            });
        });
        previous.addEventListener('click', () => { page--; render(); });
        next.addEventListener('click', () => { page++; render(); });
        document.getElementById('close-period-accounts').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const bounds = dialog.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
        });
        document.querySelectorAll(@json($accountDecisionSelector)).forEach(input => {
            input.addEventListener('change', () => {
                if (dialog.open) dialog.close();
                document.querySelectorAll('[data-period-accounts]').forEach(button => { button.disabled = true; });
            });
        });
    })();
</script>
