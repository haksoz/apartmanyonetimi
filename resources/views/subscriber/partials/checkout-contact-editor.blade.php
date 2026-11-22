<div data-contact-completion data-contact-save-url="{{ route('subscriber.checkout-contact.update') }}" class="mt-3">
    <button type="button" data-contact-open onclick="payToggleContactEditor(this, true)" class="text-sm font-semibold text-emerald-700 underline underline-offset-2">İletişim bilgilerini tamamla</button>
    <div data-contact-editor role="group" aria-label="Eksik iletişim bilgileri" class="mt-3 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3" hidden>
        <p class="text-xs text-slate-600">Yalnızca eksik veya geçersiz bilgiler istenir. Kaydettiğiniz bilgiler müşteri profilinize eklenir.</p>
        @if (in_array('phone', $contactFieldsToComplete, true))
            <div>
                <label for="{{ $payModalId }}-contact-phone" class="block text-sm font-medium text-slate-700">Telefon *</label>
                <input id="{{ $payModalId }}-contact-phone" data-contact-field="phone" type="tel" required inputmode="numeric" pattern="[1-9][0-9]{9}" maxlength="10" autocomplete="tel-national" placeholder="5551234567" value="{{ auth()->user()->phone }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Başında 0 olmadan 10 hane.</p>
            </div>
        @endif
        @if (in_array('address', $contactFieldsToComplete, true))
            <div>
                <label for="{{ $payModalId }}-contact-address" class="block text-sm font-medium text-slate-700">İletişim adresi *</label>
                <textarea id="{{ $payModalId }}-contact-address" data-contact-field="address" required maxlength="400" rows="2" autocomplete="street-address" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">{{ auth()->user()->address }}</textarea>
            </div>
        @endif
        <p data-contact-save-error role="alert" class="text-xs text-red-700" hidden></p>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" data-contact-save onclick="paySaveCheckoutContact(this)" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Kaydet ve devam et</button>
            <button type="button" data-contact-cancel onclick="payToggleContactEditor(this, false)" class="text-sm font-semibold text-slate-600">Vazgeç</button>
        </div>
    </div>
</div>
<p data-contact-saved role="status" class="mt-1 text-xs text-emerald-700" hidden>İletişim bilgileriniz kaydedildi.</p>
