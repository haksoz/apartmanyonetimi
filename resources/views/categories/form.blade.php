<form method="POST" action="{{ $action }}" class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="space-y-5">
        <div>
            <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">Kategori Adı</label>
            <input id="name" name="name" value="{{ old('name', $category?->name) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none" placeholder="Aidat, Demirbaş, Elektrik, Asansör...">
            @error('name')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="type" class="mb-2 block text-sm font-semibold text-slate-700">Kullanım Tipi</label>
            <select id="type" name="type" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                <option value="all" @selected(old('type', $category?->type ?? 'all') === 'all')>Tümü</option>
                <option value="income" @selected(old('type', $category?->type) === 'income')>Gelir / Tahsilat</option>
                <option value="expense" @selected(old('type', $category?->type) === 'expense')>Gider</option>
            </select>
            @error('type')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category?->is_active ?? true)) class="rounded border-slate-300">
            Aktif kategori
        </label>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">{{ $buttonText }}</button>
            <a href="{{ route('categories.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</a>
        </div>
    </div>
</form>
