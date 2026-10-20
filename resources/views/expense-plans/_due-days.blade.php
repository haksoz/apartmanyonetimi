<div>
    <label for="due_days" class="mb-2 block text-sm font-medium text-slate-600">Vade (Gün) <span class="font-normal text-slate-400">(opsiyonel)</span></label>
    <input id="due_days" name="due_days" type="number" min="0" max="365" step="1" value="{{ old('due_days', isset($plan) ? $plan->due_days : 15) }}" placeholder="Örn. 15" aria-describedby="due-days-help" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
    <p id="due-days-help" class="mt-1 text-xs text-slate-500">Son ödeme tarihi, her giderin tarihine bu kadar gün eklenerek hesaplanır. Örneğin 1 Ekim + 15 gün = 16 Ekim. 0 aynı gün, boş alan vadesiz anlamına gelir. En fazla 365 gün.</p>
    @error('due_days')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
</div>
