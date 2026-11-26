@if (count($recentFilters))
    <section class="mb-5 rounded-xl bg-slate-50 p-3" aria-labelledby="recent-expense-filters-title">
        <h3 id="recent-expense-filters-title" class="mb-2 text-xs font-semibold text-slate-500">Son Kullanılan Filtreler</h3>
        <div class="space-y-2">
            @foreach ($recentFilters as $recent)
                <button type="button" data-recent-expense-filter='@json($recent)' aria-pressed="false"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-sm text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 aria-pressed:border-emerald-600 aria-pressed:bg-emerald-50 aria-pressed:text-emerald-700">
                    {{ $recent['label'] }}
                </button>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-slate-500">Seçin, ardından Uygula düğmesine dokunun.</p>
    </section>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const buttons = [...document.querySelectorAll('[data-recent-expense-filter]')];
            buttons.forEach(button => button.addEventListener('click', () => {
                const { values } = JSON.parse(button.dataset.recentExpenseFilter);
                const form = document.getElementById('filter-form');
                for (const [name, value] of Object.entries({ period: values.period || '', period_end: values.period_end || '', category: values.category || '', search: values.search || '' })) {
                    let input = form.querySelector(`[name="${name}"]`);
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        form.append(input);
                    }
                    input.value = value;
                }
                const statuses = values.status || [];
                document.getElementById('expense-status-inputs').replaceChildren(...statuses.map(value => {
                    const input = document.createElement('input');
                    input.type = 'hidden'; input.name = 'status[]'; input.value = value;
                    return input;
                }));
                const names = { paid: 'Ödendi', overdue: 'Ödenmedi', pending: 'Bekliyor' };
                document.getElementById('expense-status-text').textContent = statuses.map(value => names[value]).join(', ') || 'Tüm Durumlar';
                const category = [...document.querySelectorAll('[data-expense-category]')].find(option => option.dataset.expenseCategory === String(values.category));
                document.getElementById('expense-category-text').textContent = category?.querySelector('span').textContent.trim() || 'Tüm Kategoriler';
                const monthLabel = value => new Date(`${value}-01T12:00:00`).toLocaleDateString('tr-TR', { month: 'long', year: 'numeric' });
                document.getElementById('expense-period-text').textContent = values.period
                    ? monthLabel(values.period) + (values.period_end && values.period_end !== values.period ? ' – ' + monthLabel(values.period_end) : '') : 'Tüm Dönemler';
                buttons.forEach(item => item.setAttribute('aria-pressed', item === button ? 'true' : 'false'));
            }));
        });
    </script>
@endif
