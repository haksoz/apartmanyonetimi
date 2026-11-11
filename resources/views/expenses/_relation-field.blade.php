<div class="md:col-span-2">
    <label for="expense_relation" class="mb-2 block text-sm font-medium text-slate-600">Gider İlişkisi</label>
    <select id="expense_relation" name="expense_relation" aria-describedby="expense-relation-help" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
        @foreach(\App\Enums\ExpenseRelation::cases() as $relation)
            <option value="{{ $relation->value }}" @selected(old('expense_relation', isset($expense) ? $expense->expense_relation->value : 'unassigned') === $relation->value)>{{ $relation->label() }}</option>
        @endforeach
    </select>
    <p id="expense-relation-help" class="mt-2 text-xs leading-relaxed text-slate-500">Raporlarda aidat ve kat maliki payı giderlerinin hesaplanmasında kullanılır. Ayrıntılı rapor alabilmek için gider ilişkisini seçin. Otomatik borçlandırma oluşturmaz.</p>
    @error('expense_relation')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
</div>
