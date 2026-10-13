@php
    $value = function (string $key) use ($profile) {
        return old($key, $profile->{$key});
    };
    $party = $value('party_type') ?: \App\Models\BillingProfile::TYPE_INDIVIDUAL;
@endphp

<div class="grid gap-3" data-profile-party>
    <label class="text-sm font-medium text-slate-700">
        Profil adı
        <input name="label" value="{{ $value('label') }}" required placeholder="Bu profili listede ayırt edecek ad" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        @error('label') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
    </label>
    <label class="text-sm font-medium text-slate-700">
        Tür
        <select name="party_type" data-billing-party required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
            @foreach (\App\Models\BillingProfile::selectablePartyTypes() as $type => $label)
                <option value="{{ $type }}" @selected($party === $type)>{{ $label }}</option>
            @endforeach
            @if ($party === \App\Models\BillingProfile::TYPE_OTHER)
                <option value="other" selected>Diğer</option>
            @endif
        </select>
        @error('party_type') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
    </label>
    <label data-party-field="individual" @class(['text-sm font-medium text-slate-700', 'hidden' => $party !== 'individual']) @if ($party !== 'individual') hidden @endif>
        Fatura ad soyad
        <input name="legal_name" value="{{ $value('legal_name') }}" placeholder="Faturada görünecek ad ve soyad" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        @error('legal_name') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
    </label>
    <label data-party-field="corporate" @class(['text-sm font-medium text-slate-700', 'hidden' => $party !== 'corporate']) @if ($party !== 'corporate') hidden @endif>
        Şirket adı
        <input data-corporate-name value="{{ $value('legal_name') }}" placeholder="Faturada görünecek şirket unvanı" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
    </label>
    <label data-party-field="both" @class(['text-sm font-medium text-slate-700', 'hidden' => ! in_array($party, ['individual', 'corporate'], true)]) @if (! in_array($party, ['individual', 'corporate'], true)) hidden @endif>
        Fatura adresi
        <textarea name="address" rows="2" placeholder="Faturada görünecek açık adres" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">{{ $value('address') }}</textarea>
        @error('address') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
    </label>
    <label data-party-field="individual" @class(['text-sm font-medium text-slate-700', 'hidden' => $party !== 'individual']) @if ($party !== 'individual') hidden @endif>
        Kimlik no
        <input name="identity_number" value="{{ $value('identity_number') }}" placeholder="T.C. kimlik numarası" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        @error('identity_number') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
    </label>
    <label data-party-field="corporate" @class(['text-sm font-medium text-slate-700', 'hidden' => $party !== 'corporate']) @if ($party !== 'corporate') hidden @endif>
        Vergi dairesi
        <input name="tax_office" value="{{ $value('tax_office') }}" placeholder="Vergi dairesinin adı" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        @error('tax_office') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
    </label>
    <label data-party-field="corporate" @class(['text-sm font-medium text-slate-700', 'hidden' => $party !== 'corporate']) @if ($party !== 'corporate') hidden @endif>
        Vergi no
        <input data-corporate-tax value="{{ $value('identity_number') }}" placeholder="Vergi kimlik numarası" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
    </label>
    <script>
        (function (root) {
            const sync = function () {
                const party = root.querySelector('[data-billing-party]');
                const type = party ? party.value : '';
                root.querySelectorAll('[data-party-field]').forEach(function (field) {
                    const show = field.getAttribute('data-party-field') === 'both' ? (type === 'individual' || type === 'corporate') : field.getAttribute('data-party-field') === type;
                    field.hidden = !show;
                    field.classList.toggle('hidden', !show);
                });
                const name = root.querySelector('[name="legal_name"]');
                const corporateName = root.querySelector('[data-corporate-name]');
                const identity = root.querySelector('[name="identity_number"]');
                const corporateTax = root.querySelector('[data-corporate-tax]');
                if (type === 'corporate' && corporateName && name) name.value = corporateName.value;
                if (type === 'corporate' && corporateTax && identity) identity.value = corporateTax.value;
            };
            const party = root.querySelector('[data-billing-party]');
            if (party) party.addEventListener('change', sync);
            root.querySelectorAll('[data-corporate-name],[data-corporate-tax]').forEach(function (input) {
                input.addEventListener('input', sync);
            });
            const form = root.closest('form');
            if (form) form.addEventListener('submit', sync);
            sync();
        })(document.currentScript.parentElement);
    </script>
</div>
