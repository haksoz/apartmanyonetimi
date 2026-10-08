@php
    $value = function (string $key) use ($profile) {
        return old($key, $profile->{$key});
    };
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <label class="text-sm font-medium text-slate-700 sm:col-span-2">
        Profil adı
        <input name="label" value="{{ $value('label') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        @error('label') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
    </label>
    <label class="text-sm font-medium text-slate-700 sm:col-span-2">
        Tür
        <select name="party_type" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
            @foreach (\App\Models\BillingProfile::partyTypes() as $type => $label)
                <option value="{{ $type }}" @selected($value('party_type') === $type)>{{ $label }}</option>
            @endforeach
        </select>
        @error('party_type') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
    </label>
    <label class="text-sm font-medium text-slate-700 sm:col-span-2">
        Ad veya unvan
        <input name="legal_name" value="{{ $value('legal_name') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        @error('legal_name') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
    </label>
    <label class="text-sm font-medium text-slate-700">
        Kimlik veya vergi numarası
        <input name="identity_number" value="{{ $value('identity_number') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        @error('identity_number') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
    </label>
    <label class="text-sm font-medium text-slate-700">
        Vergi dairesi
        <input name="tax_office" value="{{ $value('tax_office') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        @error('tax_office') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
    </label>
    <label class="text-sm font-medium text-slate-700">
        E-posta
        <input type="email" name="email" value="{{ $value('email') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        @error('email') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
    </label>
    <label class="text-sm font-medium text-slate-700">
        Telefon
        <input name="phone" value="{{ $value('phone') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
        @error('phone') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
    </label>
    <label class="text-sm font-medium text-slate-700 sm:col-span-2">
        Adres
        <textarea name="address" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">{{ $value('address') }}</textarea>
        @error('address') <span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span> @enderror
    </label>
    <label class="text-sm font-medium text-slate-700">
        İlçe
        <input name="district" value="{{ $value('district') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
    </label>
    <label class="text-sm font-medium text-slate-700">
        İl
        <input name="province" value="{{ $value('province') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
    </label>
    <label class="text-sm font-medium text-slate-700">
        Ülke
        <input name="country" value="{{ $value('country') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
    </label>
    <label class="text-sm font-medium text-slate-700">
        Posta kodu
        <input name="postal_code" value="{{ $value('postal_code') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-normal">
    </label>
</div>
