@php($locked = $category?->exists && ($isDefaultAidatCategory ?? false))
<form method="POST" action="{{ $action }}" class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="space-y-5">
        <div>
            <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">Kategori Adı</label>
            <input id="name" name="name" value="{{ old('name', $category?->name) }}" required @disabled($locked) class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none disabled:bg-slate-100 disabled:text-slate-500" placeholder="Aidat, Demirbaş, Elektrik, Asansör...">
            @error('name')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-slate-700">Kullanım Yetkileri</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (\App\Models\Category::USAGE_PERMISSIONS as $key => $label)
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $category?->{$key} ?? false)) @disabled($locked) class="rounded border-slate-300">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category?->is_active ?? true)) @disabled($locked) class="rounded border-slate-300">
            Aktif kategori
        </label>

        <div class="flex items-center gap-3">
            @unless($locked)
                <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">{{ $buttonText }}</button>
            @endunless
            <a href="{{ route('categories.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</a>
        </div>
    </div>
</form>
