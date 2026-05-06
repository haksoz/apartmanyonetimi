<form method="POST" action="{{ $action }}" class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="space-y-5">
        <div>
            <label for="cash_box_id" class="mb-2 block text-sm font-semibold text-slate-700">Kasa</label>
            <select id="cash_box_id" name="cash_box_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                <option value="">Kasa seçin</option>
                @foreach ($cashBoxes as $cashBox)
                    <option value="{{ $cashBox->id }}" @selected((string) old('cash_box_id', $transaction?->cash_box_id) === (string) $cashBox->id)>{{ $cashBox->name }}</option>
                @endforeach
            </select>
            @error('cash_box_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="type" class="mb-2 block text-sm font-semibold text-slate-700">Hareket Türü</label>
            <select id="type" name="type" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                <option value="income" @selected(old('type', $transaction?->type) === 'income')>Gelir</option>
                <option value="expense" @selected(old('type', $transaction?->type) === 'expense')>Gider</option>
            </select>
            @error('type')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="category_id" class="mb-2 block text-sm font-semibold text-slate-700">Kategori</label>
            <select id="category_id" name="category_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                <option value="">Kategori seçin</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', $transaction?->category_id) === (string) $category->id)>{{ $category->name }} ({{ $category->type_label }})</option>
                @endforeach
            </select>
            @error('category_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="description" class="mb-2 block text-sm font-semibold text-slate-700">Kasa Tanımı / Açıklama</label>
            <input id="description" name="description" value="{{ old('description', $transaction?->description) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
            @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="account_id" class="mb-2 block text-sm font-semibold text-slate-700">Hesap Bilgisi</label>
            <select id="account_id" name="account_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                <option value="">Hesap bağlantısı yok</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected((string) old('account_id', $transaction?->account_id) === (string) $account->id)>{{ $account->name }} - {{ $account->type_label }}</option>
                @endforeach
            </select>
            @error('account_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="amount" class="mb-2 block text-sm font-semibold text-slate-700">Tutar</label>
                <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount', $transaction?->amount) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="transaction_date" class="mb-2 block text-sm font-semibold text-slate-700">İşlem Tarihi</label>
                <input id="transaction_date" name="transaction_date" type="date" value="{{ old('transaction_date', $transaction?->transaction_date?->format('Y-m-d')) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('transaction_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $transaction?->is_active ?? true)) class="rounded border-slate-300">
            Aktif kasa hareketi
        </label>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">{{ $buttonText }}</button>
            <a href="{{ route('cash.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</a>
        </div>
    </div>
</form>
