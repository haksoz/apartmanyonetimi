<form method="POST" action="{{ $action }}" class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="space-y-5">
        <div>
            <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">Kasa Adı</label>
            <input id="name" name="name" value="{{ old('name', $cashBox?->name) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none" placeholder="Nakit Kasa">
            @error('name')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="description" class="mb-2 block text-sm font-semibold text-slate-700">Açıklama</label>
            <input id="description" name="description" value="{{ old('description', $cashBox?->description) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none" placeholder="Kasanın kullanım amacı">
            @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="bank_name" class="mb-2 block text-sm font-semibold text-slate-700">Banka Adı</label>
                <input id="bank_name" name="bank_name" value="{{ old('bank_name', $cashBox?->bank_name) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('bank_name')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="account_number" class="mb-2 block text-sm font-semibold text-slate-700">Hesap No</label>
                <input id="account_number" name="account_number" value="{{ old('account_number', $cashBox?->account_number) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('account_number')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <div>
            <label for="iban" class="mb-2 block text-sm font-semibold text-slate-700">IBAN</label>
            <input id="iban" name="iban" value="{{ old('iban', $cashBox?->iban) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
            @error('iban')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $cashBox?->is_active ?? true)) class="rounded border-slate-300">
            Aktif kasa
        </label>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">{{ $buttonText }}</button>
            <a href="{{ route('cash.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</a>
        </div>
    </div>
</form>
