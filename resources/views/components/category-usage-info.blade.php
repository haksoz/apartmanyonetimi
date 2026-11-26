@php
    $displayRelations = $fixedRelations ?? (! $usageValues['allow_expense'] ? [] : array_values(array_filter([
        $usageValues['allow_aidat'] ? 'aidat' : null,
        $usageValues['allow_malik_payi'] ? 'malik_payi' : null,
    ])));
    $invalidExpenseUsage = $usageValues['allow_expense'] && ! $usageValues['allow_aidat'] && ! $usageValues['allow_malik_payi'];
@endphp
<div id="category-expense-relation-field">
    <p class="mb-2 text-sm font-semibold text-slate-700">Gider Türleri</p>
    <p data-expense-type-info role="status" aria-live="polite" class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">{{ $invalidExpenseUsage ? 'Gider girişi için en az bir borçlandırma kullanım alanı seçin.' : (collect($displayRelations)->map(fn ($value) => \App\Enums\ExpenseRelation::from($value)->label())->implode(' ve ') ?: 'Gider türü atanmaz.') }}</p>
    <p class="mt-2 text-xs text-slate-500">Gider türleri, seçilen borçlandırma kullanım alanlarına göre otomatik belirlenir.</p>
    @error('allow_expense')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
<script>
(() => {
    const field = document.getElementById('category-expense-relation-field');
    const form = field.closest('form');
    const aidat = form.querySelector('[name="allow_aidat"]');
    const owner = form.querySelector('[name="allow_malik_payi"]');
    const expense = form.querySelector('[name="allow_expense"]');
    const fixed = @json($fixedRelations ?? null);
    const refresh = () => {
        const invalid = expense.checked && !aidat.checked && !owner.checked;
        expense.setCustomValidity(invalid ? 'Gider girişi için en az bir borçlandırma kullanım alanı seçin.' : '');
        const relations = fixed ?? (expense.checked ? [aidat.checked ? 'Ortak Kullanım Gideri' : '', owner.checked ? 'Kat Maliki Gideri' : ''].filter(Boolean) : []);
        field.querySelector('[data-expense-type-info]').textContent = invalid ? 'Gider girişi için en az bir borçlandırma kullanım alanı seçin.' : (relations.map(value => ({aidat: 'Ortak Kullanım Gideri', malik_payi: 'Kat Maliki Gideri'})[value] || value).join(' ve ') || 'Gider türü atanmaz.');
    };
    [aidat, owner, expense].forEach(input => input.addEventListener('change', refresh));
    refresh();
})();
</script>
