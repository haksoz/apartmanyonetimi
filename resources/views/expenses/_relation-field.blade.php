@php($selectedRelation = old('expense_relation', isset($expense) ? $expense->expense_relation->value : 'unassigned'))
<fieldset class="md:col-span-2" aria-describedby="expense-relation-help">
    <legend id="expense_relation" class="mb-2 block text-sm font-medium text-slate-600">Gideri İlişkilendir</legend>
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach([
            'aidat' => ['İşletme Gideri', 'Apartmanın günlük işleyişi, kullanımı ve düzenli giderleri.'],
            'malik_payi' => ['Kat Maliki Gideri', 'Kat maliklerinin karşılaması öngörülen giderler.'],
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
    <p id="expense-relation-help" class="mt-2 text-xs leading-relaxed text-slate-500">Ayrıntılı rapor alabilmek için gider ilişkisini oluşturun.</p>
    @error('expense_relation')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
</fieldset>

<script>
    (() => {
        const category = document.getElementById('category_id');
        const choices = Array.from(document.querySelectorAll('input[name="expense_relation"]'));
        const syncCategoryRelation = () => {
            const value = category?.selectedOptions[0]?.dataset.expenseRelation || 'unassigned';
            choices.forEach(choice => { choice.checked = choice.value === value; });
        };
        category?.addEventListener('change', syncCategoryRelation);
        const preserveInitial = @json(isset($expense) || session()->hasOldInput('expense_relation'));
        if (!preserveInitial) syncCategoryRelation();
    })();
</script>
