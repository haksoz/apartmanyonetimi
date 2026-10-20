<form method="POST" action="{{ route('assessment-plans.update', $plan) }}" class="mb-8 rounded-2xl bg-white p-6 shadow-sm">
    @csrf
    @method('PUT')
    <div class="grid gap-5 md:grid-cols-2">
        <div>
            <label for="name" class="mb-2 block text-sm font-medium text-slate-600">Plan Adı</label>
            <input id="name" name="name" value="{{ old('name', $plan->name) }}" required maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
            @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="generate_day" class="mb-2 block text-sm font-medium text-slate-600">Oluşturma Günü</label>
            <input id="generate_day" name="generate_day" type="number" min="1" max="28" value="{{ old('generate_day', $plan->generate_day) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
            @error('generate_day')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2">
            <label for="description" class="mb-2 block text-sm font-medium text-slate-600">Açıklama</label>
            <input id="description" name="description" value="{{ old('description', $plan->description) }}" maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
            @error('description')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 p-3 md:col-span-2">
            <label for="is_active" class="text-sm font-medium text-slate-700">Aktif / Pasif</label>
            <label class="relative inline-flex cursor-pointer items-center">
                <input name="is_active" type="hidden" value="0">
                <input id="is_active" name="is_active" type="checkbox" value="1" role="switch" aria-label="Plan aktif" @checked(old('is_active', $plan->is_active)) class="peer sr-only">
                <span aria-hidden="true" class="h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-slate-900 peer-focus-visible:ring-2 peer-focus-visible:ring-slate-500 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full"></span>
            </label>
        </div>
        @error('is_active')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <p class="mt-4 text-xs text-slate-500">Tutar, dağıtım ve taksit vadeleri korunur. Değişiklikler mevcut borçları etkilemez.</p>
    <div class="mt-5 flex justify-end gap-3">
        <a href="{{ route('assessment-plans.show', $plan) }}" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">İptal</a>
        <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white">Değişiklikleri Kaydet</button>
    </div>
</form>
