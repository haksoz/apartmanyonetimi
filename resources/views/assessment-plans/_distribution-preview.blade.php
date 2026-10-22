<div class="overflow-hidden rounded-xl border border-slate-200" aria-labelledby="assessment-preview-title">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
        <h3 id="assessment-preview-title" class="text-xs font-semibold text-slate-600">Dağıtım Önizlemesi</h3>
        <span id="assessment-preview-total" class="text-xs text-slate-500"></span>
    </div>
    <p id="assessment-preview-status" role="status" class="px-4 py-3 text-sm text-slate-500">Tutar ve taksit sayısını girin.</p>
    <p id="assessment-preview-warning" class="hidden border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800"></p>
    <div id="assessment-preview-groups" class="divide-y divide-slate-100"></div>
    <p id="assessment-preview-rounding" class="hidden border-t border-slate-100 px-4 py-3 text-xs text-slate-500">Yuvarlama farkları toplam tutar korunarak dağıtılır. Aynı tipteki dairelerin payları veya son taksitleri farklı olabilir.</p>
    <p class="border-t border-slate-100 px-4 py-3 text-xs text-slate-500">Tutarlar daire başınadır. Dairelerin tek tek dökümü Kaydet sonrası işlem özetinde gösterilir.</p>
</div>
<script>
    (() => {
        const amount = document.getElementById('total_amount');
        const installments = document.getElementById('installment_count');
        const distribution = document.getElementById('distribution_type');
        const status = document.getElementById('assessment-preview-status');
        const total = document.getElementById('assessment-preview-total');
        const groups = document.getElementById('assessment-preview-groups');
        const warning = document.getElementById('assessment-preview-warning');
        const rounding = document.getElementById('assessment-preview-rounding');
        const money = value => new Intl.NumberFormat('tr-TR', {style: 'currency', currency: 'TRY'}).format(value);
        const cents = value => Math.round(Number(value) * 100);
        const range = (min, max) => cents(min) === cents(max) ? money(min) : money(min) + ' – ' + money(max);
        let timer;
        let controller;
        let version = 0;
        const showStatus = message => {
            status.textContent = message;
            status.classList.toggle('hidden', !message);
        };
        const addValue = (list, label, value) => {
            const item = document.createElement('div');
            const term = document.createElement('dt');
            term.className = 'text-xs text-slate-500';
            term.textContent = label;
            const description = document.createElement('dd');
            description.className = 'mt-1 break-words text-sm font-semibold tabular-nums text-slate-900';
            description.textContent = value;
            item.append(term, description);
            list.append(item);
        };
        const render = (data, method) => {
            groups.replaceChildren();
            showStatus(data.groups.length ? '' : 'Dağıtım için uygun daire bulunamadı. Daire bilgilerini kontrol edin.');
            total.textContent = money(data.total) + ' · ' + data.unit_count + ' daire · ' + data.installment_count + ' taksit';
            warning.classList.toggle('hidden', data.excluded_count === 0);
            warning.textContent = data.excluded_count
                ? data.excluded_count + ' dairenin ' + (method === 'square_meters' ? 'metrekare' : 'pay çarpanı') + ' bilgisi boş veya sıfır olduğu için dağıtıma dahil edilmedi.'
                : '';
            let hasRounding = false;
            data.groups.forEach(group => {
                const lastDiffers = data.installment_count > 1 && (
                    cents(group.installment_min) !== cents(group.last_min) || cents(group.installment_max) !== cents(group.last_max)
                );
                hasRounding ||= lastDiffers || cents(group.share_min) !== cents(group.share_max);
                const article = document.createElement('div');
                article.className = 'min-w-0 px-4 py-3';
                const title = document.createElement('p');
                title.className = 'text-sm font-medium text-slate-700';
                const weight = new Intl.NumberFormat('tr-TR', {maximumFractionDigits: 6}).format(group.weight);
                title.textContent = (method === 'equal' ? 'Eşit dağıtım' : weight + (method === 'square_meters' ? ' m²' : ' pay çarpanı')) + ' · ' + group.count + ' daire';
                const list = document.createElement('dl');
                list.className = 'mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3';
                addValue(list, 'Daire Başına Toplam', range(group.share_min, group.share_max));
                addValue(list, lastDiffers ? 'İlk ' + (data.installment_count - 1) + ' Taksitin Her Biri' : 'Her Taksit', range(group.installment_min, group.installment_max));
                if (lastDiffers) addValue(list, 'Son Taksit', range(group.last_min, group.last_max));
                article.append(title, list);
                groups.append(article);
            });
            rounding.classList.toggle('hidden', !hasRounding);
        };
        const update = () => {
            clearTimeout(timer);
            controller?.abort();
            const requestVersion = ++version;
            groups.replaceChildren();
            total.textContent = '';
            warning.classList.add('hidden');
            rounding.classList.add('hidden');
            if (!amount.value || !amount.validity.valid || !installments.value || !installments.validity.valid) {
                showStatus('Geçerli bir tutar ve 1–60 arasında taksit sayısı girin.');
                return;
            }
            const method = distribution.value;
            showStatus('Hesaplanıyor…');
            timer = setTimeout(async () => {
                controller = new AbortController();
                try {
                    const response = await fetch(@json(route('assessment-plans.distribution-preview')), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
                        body: JSON.stringify({total_amount: amount.value, installment_count: installments.value, distribution_type: method}),
                        signal: controller.signal,
                    });
                    if (!response.ok) throw new Error('preview');
                    const data = await response.json();
                    if (requestVersion === version) render(data, method);
                } catch (error) {
                    if (error.name !== 'AbortError' && requestVersion === version) showStatus('Önizleme hesaplanamadı. Alanları kontrol edip tekrar deneyin.');
                }
            }, 250);
        };
        amount.addEventListener('input', update);
        installments.addEventListener('input', update);
        distribution.addEventListener('change', update);
        if (amount.value) update();
    })();
</script>
