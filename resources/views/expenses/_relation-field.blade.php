@php($selectedRelation = old('expense_relation', isset($expense) ? $expense->expense_relation->value : 'unassigned'))
<fieldset class="md:col-span-2" aria-describedby="expense-relation-help">
    <legend id="expense_relation" class="mb-2 block text-sm font-medium text-slate-600">Gider Türü</legend>
    <details id="expense-type-details" class="group" @if ($selectedRelation === 'unassigned' || session()->hasOldInput('expense_relation') || $errors->has('expense_relation')) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-600">
            <span id="expense-type-summary-label" class="text-sm font-semibold text-slate-800">{{ \App\Enums\ExpenseRelation::tryFrom($selectedRelation)?->label() ?? 'Henüz Belirlenmedi' }}</span>
            <span class="text-xs font-semibold text-sky-700"><span class="group-open:hidden">Değiştir</span><span class="hidden group-open:inline">Seçenekleri Gizle</span></span>
        </summary>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        @foreach([
            'aidat' => ['Ortak Kullanım Gideri', 'Temizlik, elektrik, su vb. ortak kullanım ve düzenli işletim giderleri.'],
            'malik_payi' => ['Kat Maliki Gideri', 'Demirbaş, yenileme vb. kat maliklerinin karşılayacağı giderler.'],
        ] as $value => [$title, $description])
            <label class="cursor-pointer">
                <input type="radio" name="expense_relation" value="{{ $value }}" class="peer sr-only" @checked($selectedRelation === $value)>
                <span class="block h-full rounded-xl border-2 border-slate-200 p-4 transition-all peer-checked:border-sky-500 peer-checked:bg-sky-50 peer-focus-visible:ring-2 peer-focus-visible:ring-sky-600 peer-focus-visible:ring-offset-2 hover:bg-sky-50">
                    <span class="block text-sm font-semibold text-slate-800">{{ $title }}</span>
                    <span class="mt-1 block text-xs text-slate-600">{{ $description }}</span>
                </span>
            </label>
        @endforeach
    </div>
    <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600">
        <input type="radio" name="expense_relation" value="unassigned" @checked($selectedRelation === 'unassigned') class="border-slate-300 text-sky-600 focus:ring-sky-500">
        Henüz Belirlenmedi
    </label>
    <p id="expense-relation-help" class="mt-2 text-xs leading-relaxed text-slate-500">Raporlarda doğru sınıflandırma için gider türünü seçin. Kategori seçimi gider türünü değiştirmez. Bu seçim tek başına borçlandırma oluşturmaz.</p>
    </details>
    @error('expense_relation')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
</fieldset>

<script>
    (() => {
        const init = () => {
            const category = document.getElementById('category_id');
            const choices = Array.from(document.querySelectorAll('input[type="radio"][name="expense_relation"]'));
            if (!category) return;
            const options = Array.from(category.options);
            const syncCategories = (preserveCurrent = false) => {
                const relation = choices.find(choice => choice.checked)?.value || 'unassigned';
                const current = category.value;
                const visible = options.filter(option => !option.value
                    || relation === 'unassigned'
                    || (option.dataset.expenseRelations || '').split(',').includes(relation)
                    || (preserveCurrent && option.value === current));
                category.replaceChildren(...visible);
                category.value = visible.some(option => option.value === current) ? current : '';
            };
            const summary = document.getElementById('expense-type-summary-label');
            const labels = { aidat: 'Ortak Kullanım Gideri', malik_payi: 'Kat Maliki Gideri', unassigned: 'Henüz Belirlenmedi' };
            choices.forEach(choice => choice.addEventListener('change', () => {
                syncCategories();
                if (summary) summary.textContent = labels[choice.value];
            }));
            // Preserve historical categories on load; filter again when the user changes the type.
            syncCategories(true);
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init, { once: true });
        } else {
            init();
        }
    })();
</script>
