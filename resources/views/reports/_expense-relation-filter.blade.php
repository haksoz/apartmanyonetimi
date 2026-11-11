<div>
    <label for="report-expense-relation" class="mb-1 block text-xs text-slate-500">Gider İlişkisi</label>
    <select id="report-expense-relation" name="expense_relation" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-800">
        <option value="all" @selected($expenseRelation === 'all')>Tümü</option>
        @foreach(\App\Enums\ExpenseRelation::cases() as $relation)
            <option value="{{ $relation->value }}" @selected($expenseRelation === $relation->value)>{{ $relation->label() }}</option>
        @endforeach
    </select>
</div>
