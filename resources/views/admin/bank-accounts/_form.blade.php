<form method="POST" action="{{ $action }}" class="rounded-2xl border border-slate-200 bg-white p-6 space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <label for="name" class="block text-sm font-medium text-slate-700 mb-2">Hesap Adı</label>
        <input type="text" id="name" name="name" value="{{ old('name', $bankAccount->name ?? '') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label for="bank_name" class="block text-sm font-medium text-slate-700 mb-2">Banka Adı</label>
            <input type="text" id="bank_name" name="bank_name" value="{{ old('bank_name', $bankAccount->bank_name ?? '') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            @error('bank_name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="branch" class="block text-sm font-medium text-slate-700 mb-2">Şube</label>
            <input type="text" id="branch" name="branch" value="{{ old('branch', $bankAccount->branch ?? '') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            @error('branch')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="account_holder" class="block text-sm font-medium text-slate-700 mb-2">Hesap Sahibi</label>
        <input type="text" id="account_holder" name="account_holder" value="{{ old('account_holder', $bankAccount->account_holder ?? '') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        @error('account_holder')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label for="account_number" class="block text-sm font-medium text-slate-700 mb-2">Hesap No</label>
            <input type="text" id="account_number" name="account_number" value="{{ old('account_number', $bankAccount->account_number ?? '') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            @error('account_number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="iban" class="block text-sm font-medium text-slate-700 mb-2">IBAN</label>
            <input type="text" id="iban" name="iban" value="{{ old('iban', $bankAccount->iban ?? '') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            @error('iban')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label for="currency" class="block text-sm font-medium text-slate-700 mb-2">Para Birimi</label>
            <input type="text" id="currency" name="currency" value="{{ old('currency', $bankAccount->currency ?? 'TRY') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            @error('currency')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="sort_order" class="block text-sm font-medium text-slate-700 mb-2">Sıralama</label>
            <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $bankAccount->sort_order ?? 0) }}" min="0" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            @error('sort_order')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="flex items-center gap-3">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $bankAccount->is_active ?? true) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
        <label for="is_active" class="text-sm text-slate-700">Aktif</label>
    </div>
    @error('is_active')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

    <div class="pt-4 border-t border-slate-200 flex items-center gap-3">
        <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors">{{ $buttonText }}</button>
        <a href="{{ route('admin.bank-accounts.index') }}" class="rounded-xl border border-slate-300 px-6 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">İptal</a>
    </div>
</form>
