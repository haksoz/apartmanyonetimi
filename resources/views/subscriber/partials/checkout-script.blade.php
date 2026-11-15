        <script>
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
                    modal.querySelectorAll('[data-pay-panel]').forEach(function (panel) {
                        reveal(panel, panel.getAttribute('data-pay-panel') === name);
                    });
                };
                const close = function () {
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
                if (action === 'close') {
                    close();
                    return false;
                }
                if (action === 'backdrop') {
                    const reading = modal.querySelector('[data-pay-panel="distance"]:not([hidden]), [data-pay-panel="pre"]:not([hidden])');
                    if (reading) show('payment');
                    else close();
                    return false;
                }
                if (action === 'next') {
                    const next = node.getAttribute('data-pay-next');
                    const billingError = modal.querySelector('[data-pay-billing-error]');
                    if (next === 'payment' && ! payStepReady(modal)) {
                        reveal(billingError, true);
                        return false;
                    }
                    reveal(billingError, false);
                    if (next === 'payment') payLegalFill(modal);
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
                    const box = modal.querySelector('input[name="accept_sales"]');
                    if (box && box.checked && payStepReady(modal)) {
                        const button = modal.querySelector('[data-checkout-submit]');
                        if (button.disabled) return false;
                        button.disabled = true;
                        button.textContent = 'Sipariş oluşturuluyor…';
                        return true;
                    }
                    show('payment');
                    reveal(modal.querySelector('[data-pay-error]'), true);
                    return false;
                }
                return false;
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
            function payLegalFill(modal) {
                const periodInput = modal.querySelector('input[name="period"]:checked');
                const methodInput = modal.querySelector('input[name="payment_method"]:checked');
                const period = periodInput ? periodInput.value : 'monthly';
                const method = methodInput ? methodInput.value : 'havale';
                const price = period === 'yearly' ? modal.getAttribute('data-price-yearly') : modal.getAttribute('data-price-monthly');
                const billing = payLegalBilling(modal);
                const periodLabel = modal.querySelector('[data-checkout-period]');
                const totalLabel = modal.querySelector('[data-checkout-total]');
                if (periodLabel) periodLabel.textContent = period === 'yearly' ? 'Yıllık (12 ay)' : 'Aylık';
                if (totalLabel) totalLabel.textContent = payLegalMoney(price);
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
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-checkout-submit]').forEach(button => {
            button.disabled = false;
            button.textContent = 'Siparişi Onayla';
        });
    });
    document.addEventListener('keydown', event => {
        const modal = document.querySelector('[data-pay-modal]:not([hidden])');
        if (!modal) return;
        if (event.key === 'Escape') { event.preventDefault(); payStep(modal, 'backdrop'); }
        if (event.key === 'Tab') {
            const controls = Array.from(modal.querySelectorAll('a,button,input,select,textarea')).filter(node => !node.disabled && node.offsetParent !== null && node.tabIndex >= 0);
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });
</script>
