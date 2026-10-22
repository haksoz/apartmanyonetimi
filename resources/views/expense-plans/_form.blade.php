<section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
    <h2 class="mb-4 text-sm font-semibold text-slate-700">Plan Bilgileri</h2>
    <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="name" class="mb-2 block text-sm font-medium text-slate-600">Plan Adı</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $plan->name ?? '') }}" required maxlength="255" placeholder="Örn. Aylık asansör bakımı" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('name')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="amount" class="mb-2 block text-sm font-medium text-slate-600">Aylık Tutar</label>
                    <input id="amount" name="amount" type="number" value="{{ old('amount', $plan->amount ?? '') }}" required min="0.01" step="0.01" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
    </div>
</section>
<section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
    <h2 class="mb-4 text-sm font-semibold text-slate-700">İşlem Bilgileri</h2>
    <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="account_id" class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm font-medium text-slate-600">Hesap / Tedarikçi
                        @isset($plan)<span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-500">Değiştirilemez</span>@endisset
                    </label>
                    <select id="account_id" name="account_id" @disabled(isset($plan)) required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 disabled:opacity-100">
                        <option value="">Tedarikçi seçin</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" data-category-id="{{ $account->default_category_id }}" @selected((string) (isset($plan) ? $plan->account_id : old('account_id')) === (string) $account->id)>{{ $account->name }} ({{ $account->type }})</option>
                        @endforeach
                    </select>
                    @error('account_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="category_id" class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm font-medium text-slate-600">Kategori
                        @isset($plan)<span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-500">Değiştirilemez</span>@endisset
                    </label>
                    <select id="category_id" name="category_id" @disabled(isset($plan)) required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 disabled:opacity-100">
                        <option value="">Kategori seçin</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) (isset($plan) ? $plan->category_id : old('category_id')) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
    </div>
</section>
<section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
    <h2 class="mb-4 text-sm font-semibold text-slate-700">Açıklama</h2>
    <div class="grid gap-4 md:grid-cols-2">
                @if (isset($plan) && $plan->description)
                    <div class="md:col-span-2">
                        <label for="description" class="mb-2 block text-sm font-medium text-slate-600">Mevcut Özel Açıklama</label>
                        <input id="description" name="description" value="{{ old('description', $plan->description) }}" maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        <p class="mt-1 text-xs text-slate-500">Ay, yıl ve kategoriyle otomatik açıklamaya geçmek için bu alanı boşaltın.</p>
                        @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                    </div>
                @endif
        @include('expense-plans._description')
    </div>
</section>
<section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
    <h2 class="mb-4 text-sm font-semibold text-slate-700">Takvim</h2>
    <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="start_date" class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm font-medium text-slate-600">Başlangıç Tarihi
                        @isset($plan)<span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-500">Değiştirilemez</span>@endisset
                    </label>
                    <input id="start_date" name="start_date" @readonly(isset($plan)) type="date" value="{{ isset($plan) ? $plan->start_date->toDateString() : old('start_date', now()->startOfMonth()->toDateString()) }}" required @class(['w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none', 'cursor-not-allowed bg-slate-100 text-slate-500' => isset($plan)])>
                    @error('start_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="end_date" class="mb-2 block text-sm font-medium text-slate-600">Bitiş Tarihi <span id="end-date-hint" class="font-normal text-slate-400">(opsiyonel)</span></label>
                    <input id="end_date" name="end_date" type="date" value="{{ old('end_date', isset($plan) ? $plan->end_date?->toDateString() : '') }}"  class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('end_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="generate_day" class="mb-2 block text-sm font-medium text-slate-600">Oluşturma Günü (1–28)</label>
                    <input id="generate_day" name="generate_day" type="number" value="{{ old('generate_day', $plan->generate_day ?? 1) }}" required min="1" max="28" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('generate_day')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                @include('expense-plans._due-days')
    </div>
</section>
<section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
    <h2 class="mb-4 text-sm font-semibold text-slate-700">Oluşturma ve Durum</h2>
    @include('expense-plans._generation-mode')
    <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div>
                            <label for="is_active" class="text-sm font-medium text-slate-700">Plan Durumu</label>
                            <p id="is-active-help" class="mt-0.5 text-xs text-slate-500">Pasif planda otomatik ve plan üzerinden manuel gider oluşturulmaz.</p>
                        </div>
                        <label for="is_active" class="relative inline-flex shrink-0 cursor-pointer items-center">
                            <input type="hidden" name="is_active" value="0">
                            <input id="is_active" type="checkbox" name="is_active" value="1" role="switch" aria-label="Plan aktif" aria-describedby="is-active-help" @checked(old('is_active', $plan->is_active ?? true)) class="sr-only peer">
                            <span aria-hidden="true" class="h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-slate-900 peer-focus-visible:ring-2 peer-focus-visible:ring-slate-500 peer-focus-visible:ring-offset-2 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full"></span>
                        </label>
                    </div>
                    @error('is_active')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
    </div>
</section>
<div id="expense-plan-actions" class="flex flex-wrap items-center justify-end gap-3">
    <p class="mr-auto text-xs text-slate-500">Son onayınıza kadar kayıt yapılmaz.</p>
    <a href="{{ isset($plan) ? route('expense-plans.show', $plan) : route('financial-plans.index') }}" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">İptal</a>
    <button id="save-expense-plan" type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white">Kaydet</button>
</div>
    <script>
        (() => {
            const account = document.getElementById('account_id');
            const category = document.getElementById('category_id');
            const fillCategory = () => {
                const categoryId = account.selectedOptions[0]?.dataset.categoryId;
                if (!category.value && categoryId && [...category.options].some(option => option.value === categoryId)) {
                    category.value = categoryId;
                }
                category.dispatchEvent(new Event('change'));
            };
            account.addEventListener('change', () => {
                category.value = '';
                if (!account.disabled) fillCategory();
            });
            if (!account.disabled) fillCategory();
        })();
    </script>
