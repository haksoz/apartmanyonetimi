@if (count($recentFilters))
    <section class="mb-5 rounded-xl bg-slate-50 p-3" aria-labelledby="recent-due-filters-title">
        <h3 id="recent-due-filters-title" class="mb-2 text-xs font-semibold text-slate-500">Son Kullanılan Filtreler</h3>
        <div class="space-y-2">
            @foreach ($recentFilters as $recent)
                <button type="button" data-recent-due-filter='@json($recent)' aria-pressed="false" title="{{ $recent['label'] }}" aria-label="{{ $recent['label'] }}"
                    class="flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-sm text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 aria-pressed:border-emerald-600 aria-pressed:bg-emerald-50 aria-pressed:text-emerald-700">
                    <span class="min-w-0 flex-1 truncate">{{ $recent['summary'] }}</span>
                    @if ($recent['extra'])
                        <span class="shrink-0 rounded-md bg-slate-100 px-1.5 py-0.5 text-xs">+{{ $recent['extra'] }} seçim</span>
                    @endif
                </button>
            @endforeach
        </div>
        <p data-recent-due-description class="mt-2 text-xs text-slate-500" aria-live="polite">Seçin, ardından Uygula düğmesine dokunun.</p>
    </section>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const buttons = [...document.querySelectorAll('[data-recent-due-filter]')];
            buttons.forEach(button => button.addEventListener('click', () => {
                const { values, label } = JSON.parse(button.dataset.recentDueFilter);
                const form = document.getElementById('filter-form');
                for (const name of ['due_type', 'status', 'unit_id', 'period', 'period_end', 'batch_id', 'search']) {
                    let input = form.querySelector(`[name="${name}"]`);
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden'; input.name = name;
                        form.append(input);
                    }
                    const value = String(values[name] || '');
                    input.value = input.tagName === 'SELECT' && ![...input.options].some(option => option.value === value && !option.disabled) ? '' : value;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
                const monthLabel = value => new Date(`${value}-01T12:00:00`).toLocaleDateString('tr-TR', { month: 'long', year: 'numeric' });
                document.getElementById('dues-period-text').textContent = values.period
                    ? monthLabel(values.period) + (values.period_end && values.period_end !== values.period ? ' – ' + monthLabel(values.period_end) : '') : 'Tüm Dönemler';
                buttons.forEach(item => item.setAttribute('aria-pressed', item === button ? 'true' : 'false'));
                document.querySelector('[data-recent-due-description]').textContent = label + ' · Uygula düğmesine dokunun.';
            }));
        });
    </script>
@endif
