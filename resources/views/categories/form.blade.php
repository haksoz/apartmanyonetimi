@php($demirbas = $category?->exists && $category->isDemirbasCategory())
@php($relationLocked = $category?->exists && $category->isExpenseRelationLocked())
@php($locked = $category?->exists && $category->isLocked())
@php($expenseEnabled = ($locked || $demirbas) ? (bool) $category->allow_expense : (session()->hasOldInput() ? (bool) old('allow_expense', false) : (bool) ($category?->allow_expense ?? false)))
<form method="POST" action="{{ $action }}" class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="space-y-5">
        <div>
            <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">Kategori Adı</label>
            <input id="name" name="name" value="{{ old('name', $category?->name) }}" required @disabled($locked || $demirbas) class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none disabled:bg-slate-100 disabled:text-slate-500" placeholder="Aidat, Demirbaş, Elektrik, Asansör...">
            @error('name')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-slate-700">Kullanım Yetkileri</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (\App\Models\Category::USAGE_PERMISSIONS as $key => $label)
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked($key === 'allow_expense' ? $expenseEnabled : old($key, $category?->{$key} ?? false)) @disabled($locked || ($demirbas && $key !== 'allow_aidat')) class="rounded border-slate-300">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category?->is_active ?? true)) @disabled($locked || $demirbas) class="rounded border-slate-300">
            Aktif kategori
        </label>

        @if(!($locked || $demirbas) || $expenseEnabled)
        <div id="category-expense-relation-field" @if(!$expenseEnabled) hidden @endif>
            <div class="mb-2 text-sm font-semibold text-slate-700" id="category-expense-relations-label">Gider İlişkileri</div>
            @php($selectedRelations = old('expense_relations', session()->hasOldInput('expense_relations_present') ? [] : ($category?->expense_relations ?? [])))
            @php($selectedRelations = is_array($selectedRelations) ? $selectedRelations : [])
            <input type="hidden" name="expense_relations_present" value="1">
            <div class="grid gap-3 sm:grid-cols-2" role="group" aria-labelledby="category-expense-relations-label">
                @foreach([\App\Enums\ExpenseRelation::Aidat, \App\Enums\ExpenseRelation::MalikPayi] as $relation)
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
                        <input type="checkbox" name="expense_relations[]" value="{{ $relation->value }}" @checked(in_array($relation->value, $selectedRelations, true)) @disabled($relationLocked || !$expenseEnabled) class="rounded border-slate-300">
                        {{ $relation->label() }}
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-slate-500">Birden fazla ilişki seçebilirsiniz. Tek seçim gider oluştururken önerilir; iki seçim varsa ilgili gider için karar verilir. Mevcut giderler değişmez.</p>
            @error('expense_relations')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            @error('expense_relations.*')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        @endif
        <div class="flex items-center gap-3">
            @unless($locked && ($relationLocked || !$expenseEnabled))
                <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">{{ $buttonText }}</button>
            @endunless
            <a href="{{ route('categories.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</a>
        </div>
    </div>
</form>

<script>
    (() => {
        const field = document.getElementById('category-expense-relation-field');
        if (!field) return;
        const form = field.closest('form');
        const expense = form.querySelector('[name="allow_expense"]');
        const relations = Array.from(field.querySelectorAll('[name="expense_relations[]"]'));
        const relationLocked = @json($relationLocked);
        const refresh = () => {
            field.hidden = !expense.checked;
            relations.forEach(relation => {
                relation.disabled = !expense.checked || relationLocked;
                if (!expense.checked) relation.checked = false;
            });
        };
        expense.addEventListener('change', refresh);
        refresh();
    })();
</script>
