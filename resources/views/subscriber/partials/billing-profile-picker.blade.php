@php
    $billingMode = $mode ?? ($profiles->isEmpty() ? 'new' : 'existing');
    $selectedId = isset($selectedId) ? (string) $selectedId : '';
@endphp

<div data-billing-picker @class(['mt-4' => ! empty($embedded), 'mt-6 rounded-xl border border-slate-200 p-4' => empty($embedded)])>
    <div class="flex flex-wrap items-center justify-between gap-2">
        @unless (! empty($embedded))
            <h2 class="text-sm font-semibold text-slate-900">Fatura profili</h2>
        @endunless
        <a href="{{ route('subscriber.billing-profiles.index') }}" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">Profilleri yönet</a>
    </div>

    @if ($profiles->isNotEmpty())
        <label class="mt-3 flex items-start gap-2 text-sm text-slate-800">
            <input type="radio" name="billing_mode" value="existing" class="mt-1" @checked($billingMode !== 'new')>
            <span>Kayıtlı profil</span>
        </label>
        <label class="mt-2 block text-sm font-medium text-slate-700">
            <select name="billing_profile_id" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
                <option value="">Seçin</option>
                @foreach ($profiles as $profile)
                    <option
                        value="{{ $profile->id }}"
                        data-billing-legal-name="{{ $profile->legal_name }}"
                        data-billing-identity-number="{{ $profile->identity_number }}"
                        data-billing-tax-office="{{ $profile->tax_office }}"
                        data-billing-email="{{ $profile->email }}"
                        data-billing-phone="{{ $profile->phone }}"
                        data-billing-address="{{ $profile->address }}"
                        data-billing-district="{{ $profile->district }}"
                        data-billing-province="{{ $profile->province }}"
                        data-billing-postal-code="{{ $profile->postal_code }}"
                        data-billing-country="{{ $profile->country }}"
                        @selected($selectedId === (string) $profile->id)
                    >
                        {{ $profile->label }}{{ $profile->legal_name ? ' · '.$profile->legal_name : '' }}
                    </option>
                @endforeach
            </select>
        </label>
        <label class="mt-3 flex items-start gap-2 text-sm text-slate-800">
            <input type="radio" name="billing_mode" value="new" class="mt-1" @checked($billingMode === 'new')>
            <span>Yeni profil oluştur</span>
        </label>
    @else
        <input type="hidden" name="billing_mode" value="new">
        <p class="mt-2 text-sm text-slate-600">Kayıtlı profiliniz yok. Bu sipariş için bir profil oluşturun.</p>
    @endif

    <div data-billing-new @class(['mt-4 grid gap-4 sm:grid-cols-2', 'hidden' => $billingMode !== 'new']) @if ($billingMode !== 'new') hidden @endif>
        <label class="text-sm font-medium text-slate-700 sm:col-span-2">
            Profil adı
            <input name="billing_label" value="{{ old('billing_label') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
            @error('billing_label') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm font-medium text-slate-700 sm:col-span-2">
            Tür
            <select name="billing_party_type" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
                <option value="">Seçin</option>
                @foreach (\App\Models\BillingProfile::partyTypes() as $type => $label)
                    <option value="{{ $type }}" @selected(old('billing_party_type') === $type)>{{ $label }}</option>
                @endforeach
            </select>
            @error('billing_party_type') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm font-medium text-slate-700 sm:col-span-2">
            Ad veya unvan
            <input name="billing_legal_name" value="{{ old('billing_legal_name') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        </label>
        <label class="text-sm font-medium text-slate-700">
            Kimlik veya vergi numarası
            <input name="billing_identity_number" value="{{ old('billing_identity_number') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        </label>
        <label class="text-sm font-medium text-slate-700">
            Vergi dairesi
            <input name="billing_tax_office" value="{{ old('billing_tax_office') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        </label>
        <label class="text-sm font-medium text-slate-700">
            E-posta
            <input name="billing_email" value="{{ old('billing_email') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
            @error('billing_email') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm font-medium text-slate-700">
            Telefon
            <input name="billing_phone" value="{{ old('billing_phone') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        </label>
        <label class="text-sm font-medium text-slate-700 sm:col-span-2">
            Adres
            <textarea name="billing_address" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">{{ old('billing_address') }}</textarea>
        </label>
        <label class="text-sm font-medium text-slate-700">
            İlçe
            <input name="billing_district" value="{{ old('billing_district') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        </label>
        <label class="text-sm font-medium text-slate-700">
            İl
            <input name="billing_province" value="{{ old('billing_province') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        </label>
    </div>
    @error('billing_profile_id') <div class="mt-2 text-sm text-red-600">{{ $message }}</div> @enderror
    @error('billing_mode') <div class="mt-2 text-sm text-red-600">{{ $message }}</div> @enderror

    <script>
        (function (picker) {
            const panel = picker.querySelector('[data-billing-new]');
            const sync = function () {
                const selected = picker.querySelector('input[name="billing_mode"]:checked');
                const hidden = picker.querySelector('input[type="hidden"][name="billing_mode"]');
                const isNew = hidden ? hidden.value === 'new' : (selected && selected.value === 'new');
                if (panel) {
                    panel.hidden = !isNew;
                    panel.classList.toggle('hidden', !isNew);
                }
            };
            picker.querySelectorAll('input[name="billing_mode"]').forEach(function (input) {
                input.addEventListener('change', sync);
            });
            sync();
        })(document.currentScript.parentElement);
    </script>
</div>
