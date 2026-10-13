@php
    $billingMode = $mode ?? ($profiles->isEmpty() ? 'new' : 'existing');
    $selectedId = isset($selectedId) ? (string) $selectedId : '';
@endphp

<div data-billing-picker @class(['mt-4' => ! empty($embedded), 'mt-6 rounded-xl border border-slate-200 p-4' => empty($embedded)])>
    @unless (! empty($embedded))
        <h2 class="text-sm font-semibold text-slate-900">Fatura profili</h2>
    @endunless

    @if ($profiles->isNotEmpty())
        <p class="mt-2 text-sm text-slate-600">Fatura profilini seçin veya yeni bir profil oluşturun.</p>
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
        <p class="mt-2 text-sm text-slate-600">Kayıtlı fatura profiliniz yok. Bu sipariş için bir profil oluşturun.</p>
    @endif

    <div data-billing-new @class(['mt-4 grid gap-3', 'hidden' => $billingMode !== 'new']) @if ($billingMode !== 'new') hidden @endif>
        <label class="text-sm font-medium text-slate-700">
            Profil adı
            <input name="billing_label" value="{{ old('billing_label') }}" placeholder="Bu profili listede ayırt edecek ad" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
            @error('billing_label') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm font-medium text-slate-700">
            Tür
            <select name="billing_party_type" data-billing-party class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
                <option value="">Bireysel veya kurumsal seçin</option>
                @foreach (\App\Models\BillingProfile::selectablePartyTypes() as $type => $label)
                    <option value="{{ $type }}" @selected(old('billing_party_type') === $type)>{{ $label }}</option>
                @endforeach
            </select>
            @error('billing_party_type') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
        </label>
        <label data-party-field="individual" class="hidden text-sm font-medium text-slate-700" hidden>
            Fatura ad soyad
            <input name="billing_legal_name" value="{{ old('billing_legal_name') }}" placeholder="Faturada görünecek ad ve soyad" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
            @error('billing_legal_name') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
        </label>
        <label data-party-field="corporate" class="hidden text-sm font-medium text-slate-700" hidden>
            Şirket adı
            <input data-corporate-name value="{{ old('billing_legal_name') }}" placeholder="Faturada görünecek şirket unvanı" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        </label>
        <label data-party-field="both" class="hidden text-sm font-medium text-slate-700" hidden>
            Fatura adresi
            <textarea name="billing_address" rows="2" placeholder="Faturada görünecek açık adres" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">{{ old('billing_address') }}</textarea>
            @error('billing_address') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
        </label>
        <label data-party-field="individual" class="hidden text-sm font-medium text-slate-700" hidden>
            Kimlik no
            <input name="billing_identity_number" value="{{ old('billing_identity_number') }}" placeholder="T.C. kimlik numarası" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
            @error('billing_identity_number') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
        </label>
        <label data-party-field="corporate" class="hidden text-sm font-medium text-slate-700" hidden>
            Vergi dairesi
            <input name="billing_tax_office" value="{{ old('billing_tax_office') }}" placeholder="Vergi dairesinin adı" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
            @error('billing_tax_office') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
        </label>
        <label data-party-field="corporate" class="hidden text-sm font-medium text-slate-700" hidden>
            Vergi no
            <input data-corporate-tax value="{{ old('billing_identity_number') }}" placeholder="Vergi kimlik numarası" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
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
                const party = picker.querySelector('[data-billing-party]');
                const type = party ? party.value : '';
                picker.querySelectorAll('[data-party-field]').forEach(function (field) {
                    const show = isNew && (field.getAttribute('data-party-field') === 'both' ? type !== '' : field.getAttribute('data-party-field') === type);
                    field.hidden = !show;
                    field.classList.toggle('hidden', !show);
                });
                const name = picker.querySelector('[name="billing_legal_name"]');
                const corporateName = picker.querySelector('[data-corporate-name]');
                const identity = picker.querySelector('[name="billing_identity_number"]');
                const corporateTax = picker.querySelector('[data-corporate-tax]');
                if (type === 'corporate' && corporateName && name) name.value = corporateName.value;
                if (type === 'corporate' && corporateTax && identity) identity.value = corporateTax.value;
            };
            picker.querySelectorAll('input[name="billing_mode"]').forEach(function (input) {
                input.addEventListener('change', sync);
            });
            const party = picker.querySelector('[data-billing-party]');
            if (party) party.addEventListener('change', sync);
            picker.querySelectorAll('[data-corporate-name],[data-corporate-tax]').forEach(function (input) {
                input.addEventListener('input', sync);
            });
            const form = picker.closest('form');
            if (form) form.addEventListener('submit', sync);
            sync();
        })(document.currentScript.parentElement);
    </script>
</div>
