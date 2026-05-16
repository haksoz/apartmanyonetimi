<form method="POST" action="{{ $action }}" class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm" data-account-form>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="space-y-5">
        <div>
            <label for="type" class="mb-2 block text-sm font-semibold text-slate-700">Hesap Türü</label>
            <select id="type" name="type" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                <option value="owner" @selected(old('type', $account?->type) === 'owner')>Kat Maliki</option>
                <option value="tenant" @selected(old('type', $account?->type) === 'tenant')>Kiracı</option>
                <option value="supplier" @selected(old('type', $account?->type) === 'supplier')>Tedarikçi</option>
            </select>
            @error('type')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <div data-unit-field>
            <label for="unit_id" class="mb-2 block text-sm font-semibold text-slate-700">Daire Bağlantısı</label>
            <select id="unit_id" name="unit_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                <option value="" disabled {{ old('unit_id', $account?->unit_id) ? '' : 'selected' }}>Daire seçiniz...</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}" @selected((string) old('unit_id', $account?->unit_id) === (string) $unit->id)>{{ str_pad($unit->unit_no, 2, '0', STR_PAD_LEFT) }} no.lu daire</option>
                @endforeach
            </select>
            @error('unit_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        @php
            $activeTenantAssignment = $account?->activeTenantAssignment;
        @endphp

        <div>
            <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">Adı Soyadı / Ünvan</label>
            <input id="name" name="name" value="{{ old('name', $account?->name) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
            @error('name')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div data-tenant-move-in-field>
                <label for="move_in_date" class="mb-2 block text-sm font-semibold text-slate-700">Kiracı Giriş Tarihi</label>
                <input id="move_in_date" name="move_in_date" type="date" value="{{ old('move_in_date', $activeTenantAssignment?->move_in_date?->format('Y-m-d')) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('move_in_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div data-tenant-move-out-field>
                <label for="move_out_date" class="mb-2 block text-sm font-semibold text-slate-700">Kiracı Çıkış Tarihi</label>
                <input id="move_out_date" name="move_out_date" type="date" value="{{ old('move_out_date', $activeTenantAssignment?->move_out_date?->format('Y-m-d')) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('move_out_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div data-account-opening-date-field>
                <label for="account_opening_date" class="mb-2 block text-sm font-semibold text-slate-700">Hesap Açılış Tarihi</label>
                <input id="account_opening_date" name="account_opening_date" type="date" value="{{ old('account_opening_date', $account?->account_opening_date?->format('Y-m-d')) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('account_opening_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="phone" class="mb-2 block text-sm font-semibold text-slate-700">Telefon</label>
                <input id="phone" name="phone" value="{{ old('phone', $account?->phone) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('phone')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">E-posta</label>
                <input id="email" name="email" type="email" value="{{ old('email', $account?->email) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('email')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <div>
            <label for="balance" class="mb-2 block text-sm font-semibold text-slate-700">Açılış Bakiyesi</label>
            <input id="balance" name="balance" type="number" step="0.01" value="{{ old('balance', $account?->balance ?? 0) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
            @error('balance')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <div data-default-category-field>
            <label for="default_category_id" class="mb-2 block text-sm font-semibold text-slate-700">Varsayılan Kategori <span class="font-normal text-slate-400">(opsiyonel)</span></label>
            <select id="default_category_id" name="default_category_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                <option value="">Kategori seçin</option>
                @foreach ($categories ?? [] as $category)
                    <option value="{{ $category->id }}" @selected((string) old('default_category_id', $account?->default_category_id) === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('default_category_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account?->is_active ?? true)) class="rounded border-slate-300">
            Aktif hesap
        </label>

        <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">{{ $buttonText }}</button>
    </div>
</form>

<script>
    (() => {
        const form = document.querySelector('[data-account-form]');
        if (! form) {
            return;
        }

        const type = form.querySelector('#type');
        const unitField = form.querySelector('[data-unit-field]');
        const unitInput = form.querySelector('#unit_id');
        const tenantMoveInField = form.querySelector('[data-tenant-move-in-field]');
        const tenantMoveInInput = form.querySelector('#move_in_date');
        const tenantMoveOutField = form.querySelector('[data-tenant-move-out-field]');
        const tenantMoveOutInput = form.querySelector('#move_out_date');
        const accountOpeningDateField = form.querySelector('[data-account-opening-date-field]');
        const accountOpeningDateInput = form.querySelector('#account_opening_date');
        const defaultCategoryField = form.querySelector('[data-default-category-field]');
        const defaultCategoryInput = form.querySelector('#default_category_id');

        const toggleField = (field, input, show, required = false, clearWhenHidden = false) => {
            field.classList.toggle('hidden', ! show);
            input.disabled = ! show;
            input.required = show && required;

            if (! show && clearWhenHidden) {
                input.value = '';
            }
        };

        const refresh = () => {
            const selectedType = type.value;

            const requiresUnit = ['owner', 'tenant'].includes(selectedType);
            toggleField(unitField, unitInput, selectedType !== 'supplier', requiresUnit, selectedType === 'supplier');
            // Set empty value for supplier, first unit for others if not already selected
            if (selectedType === 'supplier') {
                unitInput.value = '';
            } else if (!unitInput.value && unitInput.options.length > 0) {
                unitInput.value = unitInput.options[0].value;
            }
            toggleField(tenantMoveInField, tenantMoveInInput, selectedType === 'tenant', selectedType === 'tenant', selectedType !== 'tenant');
            toggleField(tenantMoveOutField, tenantMoveOutInput, selectedType === 'tenant', false, selectedType !== 'tenant');
            toggleField(accountOpeningDateField, accountOpeningDateInput, selectedType === 'supplier', selectedType === 'supplier', selectedType !== 'supplier');
            if (defaultCategoryField && defaultCategoryInput) {
                toggleField(defaultCategoryField, defaultCategoryInput, selectedType === 'supplier', false, selectedType !== 'supplier');
            }
        };

        type.addEventListener('change', refresh);
        refresh();
    })();
</script>
