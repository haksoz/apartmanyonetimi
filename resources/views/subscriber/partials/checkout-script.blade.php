        <script>
            function payTrack(modal, type, stage, field) {
                const form = modal?.closest('form');
                const endpoint = form?.querySelector('[data-journey-url]')?.dataset.journeyUrl;
                if (!endpoint) return;
                const read = name => form.querySelector('input[type="hidden"][name="' + name + '"], input[name="' + name + '"]:checked, select[name="' + name + '"]')?.value || null;
                const payload = {
                    type, stage: stage || modal.querySelector('[data-pay-panel]:not([hidden])')?.dataset.payPanel || 'plan',
                    period: read('period'),
                };
                if (payload.stage !== 'plan') {
                    payload.billing_mode = read('billing_mode');
                    payload.billing_profile_id = read('billing_profile_id');
                    payload.billing_party_type = read('billing_party_type');
                }
                if (['payment', 'distance', 'pre'].includes(payload.stage)) payload.payment_method = read('payment_method');
                if (payload.billing_mode === 'new') payload.billing_profile_id = null;
                if (field) payload.field = field;
                // Sequential requests preserve the user's action order. Tracking never blocks checkout.
                const send = () => fetch(endpoint, {
                    method: 'POST', credentials: 'same-origin', keepalive: true,
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value},
                    body: JSON.stringify(payload),
                });
                if (type === 'closed') { send().catch(() => {}); return; }
                modal.journeyQueue = (modal.journeyQueue || Promise.resolve()).then(send).catch(() => {});
            }
            function payStep(node, action) {
                const modal = node && node.hasAttribute && node.hasAttribute('data-pay-modal')
                    ? node
                    : (node && node.closest ? node.closest('[data-pay-modal]') : null);
                if (!modal) return false;
                const reveal = function (element, on) {
                    if (!element) return;
                    element.hidden = !on;
                    element.classList.toggle('hidden', !on);
                    if (element.hasAttribute('data-pay-panel') || element.hasAttribute('data-pay-modal')) {
                        element.classList.toggle('flex', on);
                    }
                };
                const show = function (name) {
                    if (name !== 'plan') modal.dataset.checkoutStarted = '1';
                    modal.querySelectorAll('[data-pay-panel]').forEach(function (panel) {
                        reveal(panel, panel.getAttribute('data-pay-panel') === name);
                    });
                    payTrack(modal, 'viewed', name);
                };
                const close = function () {
                    payTrack(modal, 'closed');
                    if (modal.dataset.checkoutCloseUrl) { window.location.assign(modal.dataset.checkoutCloseUrl); return; }
                    show('plan');
                    modal.hidden = true;
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                };
                if (action === 'open') {
                    modal.hidden = false;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    show(modal.getAttribute('data-pay-step') || 'plan');
                    return false;
                }
                if (action === 'close' || action === 'backdrop') {
                    const submitButton = modal.querySelector('[data-checkout-submit]');
                    if (modal.dataset.billingSaving || (submitButton?.disabled && !submitButton.hasAttribute('data-payment-unavailable'))) return false;
                    const progressed = modal.dataset.checkoutStarted || modal.dataset.checkoutDirty
                        || modal.querySelector('[data-pay-panel]:not([hidden])')?.getAttribute('data-pay-panel') !== 'plan';
                    if ((progressed || action === 'backdrop') && !window.confirm('İşlemi tamamlamadan çıkmak istiyor musunuz?')) return false;
                    close();
                    return false;
                }
                if (action === 'next') {
                    const next = node.getAttribute('data-pay-next');
                    const billingError = modal.querySelector('[data-pay-billing-error]');
                    if (next === 'payment' && ! payStepReady(modal)) {
                        payTrack(modal, 'validation_failed', 'billing');
                        billingError.textContent = 'Devam etmek için fatura alanlarını doldurun.';
                        reveal(billingError, true);
                        return false;
                    }
                    reveal(billingError, false);
                    if (next === 'payment') {
                        if (modal.dataset.billingSaving) return false;
                        const picker = modal.querySelector('[data-billing-picker]');
                        const mode = picker.querySelector('input[type="hidden"][name="billing_mode"], input[name="billing_mode"]:checked');
                        if (mode?.value === 'new') {
                            paySaveBilling(modal, picker, node);
                            return false;
                        }
                    }
                    if (next === 'billing' || next === 'payment') payLegalFill(modal);
                    show(next);
                    return false;
                }
                if (action === 'back') {
                    show(node.getAttribute('data-pay-back') || node.getAttribute('data-pay-prev') || 'payment');
                    return false;
                }
                if (action === 'read') {
                    payLegalFill(modal);
                    show(node.getAttribute('data-pay-open'));
                    return false;
                }
                if (action === 'accept') {
                    payTrack(modal, 'accepted');
                    const visual = modal.querySelector('[data-pay-box="' + node.getAttribute('data-pay-accept') + '"]');
                    if (visual) visual.checked = true;
                    const distance = modal.querySelector('[data-pay-box="distance"]');
                    const pre = modal.querySelector('[data-pay-box="pre"]');
                    const box = modal.querySelector('input[name="accept_sales"]');
                    if (box && distance && pre) box.checked = distance.checked && pre.checked;
                    if (box && box.checked) reveal(modal.querySelector('[data-pay-error]'), false);
                    show('payment');
                    return false;
                }
                if (action === 'submit') {
                    const payer = modal.querySelector('[data-card-payer]');
                    if (payer && !payer.disabled) {
                        const invalid = Array.from(payer.querySelectorAll('input')).find(input => !input.checkValidity());
                        if (invalid) {
                            payTrack(modal, 'validation_failed', 'payment');
                            show('payment');
                            invalid.reportValidity();
                            return false;
                        }
                    }
                    const box = modal.querySelector('input[name="accept_sales"]');
                    if (box && box.checked && payStepReady(modal)) {
                        const button = modal.querySelector('[data-checkout-submit]');
                        if (button.disabled) return false;
                        button.disabled = true;
                        button.textContent = 'Ödeme hazırlanıyor…';
                        return true;
                    }
                    show('payment');
                    payTrack(modal, 'validation_failed', 'payment');
                    reveal(modal.querySelector('[data-pay-error]'), true);
                    return false;
                }
                return false;
            }
            async function paySaveBilling(modal, picker, button) {
                modal.dataset.billingSaving = '1';
                button.disabled = true;
                const originalText = button.textContent;
                button.textContent = 'Kaydediliyor…';
                const error = modal.querySelector('[data-pay-billing-error]');
                try {
                    const form = modal.closest('form');
                    const payload = {};
                    payload.checkout_journey = form.querySelector('[name="checkout_journey"]')?.value;
                    new FormData(form).forEach(function (value, key) {
                        if (key.startsWith('billing_') && key !== 'billing_mode' && key !== 'billing_profile_id') {
                            payload[key.slice(8)] = value;
                        }
                    });
                    const response = await fetch(picker.dataset.profileSaveUrl, {
                        method: 'POST', credentials: 'same-origin',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value},
                        body: JSON.stringify(payload),
                    });
                    const result = await response.json();
                    if (!response.ok) {
                        throw new Error(response.status === 422 ? Object.values(result.errors || {}).flat().join(' ') : 'Fatura profili kaydedilemedi. Lütfen tekrar deneyin.');
                    }
                    const profile = result.profile;
                    let select = picker.querySelector('[name="billing_profile_id"]');
                    if (!select) {
                        select = document.createElement('select');
                        select.name = 'billing_profile_id';
                        select.className = 'mt-3 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm';
                        picker.appendChild(select);
                        const notice = picker.querySelector('p');
                        if (notice) notice.textContent = 'Fatura profiliniz kaydedildi. Sonraki siparişlerde de kullanabilirsiniz.';
                    }
                    const option = new Option(profile.label + ' · ' + profile.legal_name, profile.id, true, true);
                    Object.entries(profile).forEach(function ([key, value]) {
                        option.setAttribute('data-billing-' + key.replaceAll('_', '-'), value ?? '');
                    });
                    select.add(option);
                    select.value = profile.id;
                    const hiddenMode = picker.querySelector('input[type="hidden"][name="billing_mode"]');
                    const existingMode = picker.querySelector('input[name="billing_mode"][value="existing"]');
                    if (hiddenMode) hiddenMode.value = 'existing';
                    if (existingMode) existingMode.checked = true;
                    (existingMode || hiddenMode).dispatchEvent(new Event('change'));
                    delete modal.dataset.billingSaving;
                    payStep(button, 'next');
                } catch (failure) {
                    payTrack(modal, 'validation_failed', 'billing');
                    error.textContent = failure.message || 'Fatura profili kaydedilemedi. Lütfen tekrar deneyin.';
                    error.hidden = false;
                    error.classList.remove('hidden');
                } finally {
                    delete modal.dataset.billingSaving;
                    button.disabled = false;
                    button.textContent = originalText;
                }
            }
            function payUseBillingAddress(button) {
                const modal = button.closest('[data-pay-modal]');
                const target = modal?.querySelector('[name="payer_address"]');
                if (!target) return;
                const option = modal.querySelector('[name="billing_profile_id"] option:checked');
                const mode = modal.querySelector('input[type="hidden"][name="billing_mode"], input[name="billing_mode"]:checked');
                const read = key => {
                    const value = mode?.value === 'new'
                        ? modal.querySelector('[name="billing_' + key + '"]')?.value
                        : option?.getAttribute('data-billing-' + key.replaceAll('_', '-'));
                    return (value || '').trim();
                };
                const address = ['address', 'district', 'province', 'postal_code', 'country'].map(read).filter(Boolean).join(', ');
                const feedback = modal.querySelector('[data-payer-address-feedback]');
                feedback.hidden = false;
                feedback.classList.remove('hidden');
                if (!read('address')) {
                    feedback.textContent = 'Seçili fatura profilinde adres bulunamadı. Adresi elle girebilirsiniz.';
                    return;
                }
                if (address.length > target.maxLength) {
                    feedback.textContent = 'Fatura adresi bu alan için çok uzun. Adresi 400 karakteri aşmayacak şekilde elle girin.';
                    return;
                }
                target.value = address;
                target.dispatchEvent(new Event('input', {bubbles: true}));
                feedback.textContent = 'Fatura adresi aktarıldı. İsterseniz değiştirebilirsiniz.';
            }
            function payLegalText(value) {
                return value && String(value).trim() !== '' ? String(value).trim() : 'Belirtilmedi';
            }
            function payLegalMoney(amount) {
                if (amount === null || amount === '' || isNaN(Number(amount))) return 'Belirtilmedi';
                const parts = Number(amount).toFixed(2).split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                return parts[0] + ',' + parts[1] + ' ₺';
            }
            function payLegalAddress(parts) {
                const text = parts.map(function (value) { return value ? String(value).trim() : ''; }).filter(Boolean).join(', ');
                return text || 'Belirtilmedi';
            }
            function payLegalBilling(modal) {
                const selected = modal.querySelector('input[name="billing_mode"]:checked');
                const hidden = modal.querySelector('input[type="hidden"][name="billing_mode"]');
                const isNew = hidden ? hidden.value === 'new' : !!(selected && selected.value === 'new');
                const read = function (name) {
                    const field = modal.querySelector('[name="' + name + '"]');
                    return field ? field.value : '';
                };
                if (isNew) {
                    return {
                        legal_name: payLegalText(read('billing_legal_name')),
                        identity_number: payLegalText(read('billing_identity_number')),
                        tax_office: payLegalText(read('billing_tax_office')),
                        email: payLegalText(read('billing_email')),
                        phone: payLegalText(read('billing_phone')),
                        address: payLegalAddress([read('billing_address'), read('billing_district'), read('billing_province'), read('billing_postal_code'), read('billing_country')]),
                    };
                }
                const option = modal.querySelector('[name="billing_profile_id"] option:checked');
                if (!option || option.value === '') {
                    return { legal_name: 'Belirtilmedi', identity_number: 'Belirtilmedi', tax_office: 'Belirtilmedi', email: 'Belirtilmedi', phone: 'Belirtilmedi', address: 'Belirtilmedi' };
                }
                return {
                    legal_name: payLegalText(option.getAttribute('data-billing-legal-name')),
                    identity_number: payLegalText(option.getAttribute('data-billing-identity-number')),
                    tax_office: payLegalText(option.getAttribute('data-billing-tax-office')),
                    email: payLegalText(option.getAttribute('data-billing-email')),
                    phone: payLegalText(option.getAttribute('data-billing-phone')),
                    address: payLegalAddress([
                        option.getAttribute('data-billing-address'),
                        option.getAttribute('data-billing-district'),
                        option.getAttribute('data-billing-province'),
                        option.getAttribute('data-billing-postal-code'),
                        option.getAttribute('data-billing-country'),
                    ]),
                };
            }
            function paySyncPaymentMethod(modal) {
                const method = modal.querySelector('input[name="payment_method"]:checked, input[type="hidden"][name="payment_method"]');
                const payer = modal.querySelector('[data-card-payer]');
                if (payer) {
                    const show = method?.value === 'kredi_kartı';
                    payer.hidden = !show;
                    payer.disabled = !show;
                    payer.classList.toggle('hidden', !show);
                }
            }
            function payLegalFill(modal) {
                paySyncPaymentMethod(modal);
                const periodInput = modal.querySelector('input[name="period"]:checked');
                const methodInput = modal.querySelector('input[name="payment_method"]:checked, input[type="hidden"][name="payment_method"]');
                const period = periodInput ? periodInput.value : 'monthly';
                const method = methodInput ? methodInput.value : 'havale';
                const price = period === 'yearly' ? modal.getAttribute('data-price-yearly') : modal.getAttribute('data-price-monthly');
                const billing = payLegalBilling(modal);
                modal.querySelectorAll('[data-checkout-period]').forEach(function (label) {
                    label.textContent = period === 'yearly' ? 'Yıllık (12 ay)' : 'Aylık';
                });
                modal.querySelectorAll('[data-checkout-total]').forEach(function (label) {
                    label.textContent = payLegalMoney(price);
                });
                const values = {
                    apartment_name: payLegalText(modal.getAttribute('data-apartment-name')),
                    unit_count: payLegalText(modal.getAttribute('data-unit-count')),
                    period: period === 'yearly' ? '12 aylık' : (period === 'monthly' ? 'Aylık' : payLegalText(period)),
                    price: payLegalMoney(price),
                    payment_method: method === 'kredi_kartı' ? 'Kredi kartı' : (method === 'havale' ? 'Havale / EFT' : payLegalText(method)),
                    order_number: 'Sipariş oluşturulduğunda verilir',
                    service_start: 'Ödeme onay tarihi itibarıyla',
                    service_end: 'Ödeme onay tarihinden itibaren seçilen hizmet dönemi',
                    billing_legal_name: billing.legal_name,
                    billing_identity_number: billing.identity_number,
                    billing_tax_office: billing.tax_office,
                    billing_address: billing.address,
                    billing_email: billing.email,
                    billing_phone: billing.phone,
                };
                modal.querySelectorAll('[data-legal-token]').forEach(function (node) {
                    const key = node.getAttribute('data-legal-token');
                    if (Object.prototype.hasOwnProperty.call(values, key)) node.textContent = values[key];
                });
            }
            function payStepReady(modal) {
                const panel = modal.querySelector('[data-pay-panel="billing"]');
                if (!panel) return false;
                const hidden = panel.querySelector('input[type="hidden"][name="billing_mode"]');
                const selected = panel.querySelector('input[name="billing_mode"]:checked');
                const isNew = hidden ? hidden.value === 'new' : !!(selected && selected.value === 'new');
                if (isNew) {
                    const type = panel.querySelector('[name="billing_party_type"]');
                    const name = panel.querySelector('[name="billing_legal_name"]');
                    const address = panel.querySelector('[name="billing_address"]');
                    const identity = panel.querySelector('[name="billing_identity_number"]');
                    const office = panel.querySelector('[name="billing_tax_office"]');
                    const profileName = panel.querySelector('[name="billing_label"]');
                    if (!profileName || profileName.value.trim() === '' || !type || type.value === '' || !name || name.value.trim() === '' || !address || address.value.trim() === '' || !identity || identity.value.trim() === '') {
                        return false;
                    }
                    if (type.value === 'corporate' && (!office || office.value.trim() === '')) return false;
                    return true;
                }
                const profile = panel.querySelector('[name="billing_profile_id"]');
                return !!(profile && profile.value !== '');
            }
        </script>
<script>
    ['input', 'change'].forEach(name => document.addEventListener(name, event => {
        const modal = event.target.closest?.('[data-pay-modal]');
        if (modal) modal.dataset.checkoutDirty = '1';
        if (modal && event.target.name === 'payment_method') paySyncPaymentMethod(modal);
    }));
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-checkout-submit]').forEach(button => {
            button.disabled = button.hasAttribute('data-payment-unavailable');
            button.textContent = 'Ödemeye Geç';
        });
    });
    document.addEventListener('keydown', event => {
        const modal = document.querySelector('[data-pay-modal]:not([hidden])');
        if (!modal) return;
        if (event.key === 'Escape') { event.preventDefault(); payStep(modal, 'backdrop'); return; }
        if (event.key === 'Tab') {
            const controls = Array.from(modal.querySelectorAll('a,button,input,select,textarea')).filter(node => !node.disabled && node.offsetParent !== null && node.tabIndex >= 0);
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });
</script>
