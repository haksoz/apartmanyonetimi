@php
    $demirbas = $category?->exists && $category->isDemirbasCategory();
    $relationLocked = $category?->exists && $category->isExpenseRelationLocked();
    $locked = $category?->exists && $category->isLocked();
    $usageValues = collect(array_keys(\App\Models\Category::USAGE_PERMISSIONS))->mapWithKeys(fn ($key) => [$key =>
        ($locked || ($demirbas && $key !== 'allow_aidat')) ? (bool) $category->{$key}
        : (session()->hasOldInput() ? (bool) old($key, false) : (bool) ($category?->{$key} ?? false))])->all();
@endphp
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
            <legend class="mb-2 text-sm font-semibold text-slate-700">Kullanım Alanları</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (\App\Support\CategoryUsage::LABELS as $key => $label)
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked($usageValues[$key]) @disabled($locked || ($demirbas && $key !== 'allow_aidat')) class="rounded border-slate-300">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        @include('components.category-usage-info', ['usageValues' => $usageValues, 'fixedRelations' => $relationLocked ? ($category->expense_relations ?? []) : null])

        @if($category?->exists)
        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-slate-700">Kategori Durumu</legend>
            <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" @checked(($locked || $demirbas) ? $category->is_active : old('is_active', $category->is_active)) @disabled($locked || $demirbas) class="rounded border-slate-300">Aktif kategori
            </label>
            @error('is_active')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </fieldset>
        @endif
        <div class="flex items-center gap-3">
            @unless($locked && ($relationLocked || !$category->allow_expense))
                <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">{{ $buttonText }}</button>
            @endunless
            <a href="{{ route('categories.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</a>
        </div>
    </div>
</form>
