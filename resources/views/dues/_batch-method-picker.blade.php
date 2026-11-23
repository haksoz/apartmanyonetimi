<dialog id="batch-method-dialog" aria-labelledby="batch-method-title" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl border-0 bg-white p-5 text-slate-900 shadow-xl backdrop:bg-slate-950/50">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 id="batch-method-title" class="text-lg font-bold">Borç Türünü Seçin</h2>
        <button type="button" data-due-picker-close aria-label="Kapat" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">✕</button>
    </div>
    <div id="due-picker-types" class="space-y-2">
        <button type="button" data-due-picker-type="aidat" class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-left hover:border-emerald-400 hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
            <span class="block text-sm font-semibold">Aidat</span>
            <span class="mt-1 block text-xs leading-relaxed text-slate-500">Ortak kullanım ve düzenli giderleri için borçlandırma. Varsa kiracıya, yoksa kat malikine uygulanır.</span>
        </button>
        <button type="button" data-due-picker-type="malik_payi" class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-left hover:border-emerald-400 hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-50">
            <span class="block text-sm font-semibold">Kat Maliki Payı</span>
            <span class="mt-1 block text-xs leading-relaxed text-slate-500">Demirbaş, yenileme vb. işler için kat maliklerine borçlandırma.</span>
            <span id="due-picker-tenant-note" hidden class="mt-2 block text-xs text-amber-700">Kiracı hesabına Kat Maliki Payı yazılamaz. Kat malikinin hesap sayfasından ilerleyin.</span>
        </button>
    </div>
    <div id="due-picker-methods" hidden>
        <div class="mb-4 flex items-center justify-between gap-3">
            <p id="due-picker-selection" class="text-sm font-semibold text-emerald-700"></p>
            <button type="button" id="due-picker-back" class="text-xs font-semibold text-slate-600 hover:text-slate-950">Geri</button>
        </div>
        <p class="mb-4 text-sm text-slate-500">Borçlandırma yöntemini seçin.</p>
        <div class="space-y-2">
            @foreach (['fixed' => ['Sabit Tutar', 'Her daireye aynı tutarı uygula'], 'manual' => ['Tutardan Hesapla', 'Toplam tutarı dairelere dağıt'], 'expenses' => ['Giderden Hesapla', 'Dönem giderlerini dairelere dağıt']] as $method => [$title, $description])
                <button type="button" data-due-picker-method="{{ $method }}" class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-left hover:border-emerald-400 hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                    <span class="block text-sm font-semibold">{{ $title }}</span>
                    <span class="mt-1 block text-xs text-slate-500">{{ $description }}</span>
                </button>
            @endforeach
        </div>
    </div>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('batch-method-dialog');
        const title = document.getElementById('batch-method-title');
        const types = document.getElementById('due-picker-types');
        const methods = document.getElementById('due-picker-methods');
        const singleUrl = new URL(@json(route('dues.create')), location.origin);
        const batchUrl = new URL(@json(route('dues.batch.create')), location.origin);
        let targetUrl;
        let selectedType;
        const showTypes = () => {
            title.textContent = 'Borç Türünü Seçin';
            types.hidden = false;
            methods.hidden = true;
        };
        const open = (url, tenant = false) => {
            targetUrl = url;
            selectedType = null;
            showTypes();
            dialog.querySelector('[data-due-picker-type="malik_payi"]').disabled = tenant;
            document.getElementById('due-picker-tenant-note').hidden = !tenant;
            dialog.showModal();
        };
        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[href]');
            if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) return;
            const url = new URL(link.href, location.origin);
            if (url.origin !== singleUrl.origin || ![singleUrl.pathname, batchUrl.pathname].includes(url.pathname) || url.searchParams.has('due_type')) return;
            event.preventDefault();
            link.closest('details')?.removeAttribute('open');
            open(url, link.dataset.dueAccountType === 'tenant');
        });
        dialog.querySelectorAll('[data-due-picker-type]').forEach(button => button.addEventListener('click', () => {
            selectedType = button.dataset.duePickerType;
            targetUrl.searchParams.set('due_type', selectedType);
            if (targetUrl.pathname === singleUrl.pathname) {
                location.assign(targetUrl.href);
                return;
            }
            title.textContent = 'Borçlandırma Yöntemini Seçin';
            document.getElementById('due-picker-selection').textContent = selectedType === 'aidat' ? 'Aidat' : 'Kat Maliki Payı';
            types.hidden = true;
            methods.hidden = false;
            document.getElementById('due-picker-back').focus();
        }));
        dialog.querySelectorAll('[data-due-picker-method]').forEach(button => button.addEventListener('click', () => {
            targetUrl.searchParams.set('source_type', button.dataset.duePickerMethod);
            location.assign(targetUrl.href);
        }));
        document.getElementById('due-picker-back').addEventListener('click', () => {
            showTypes();
            dialog.querySelector('[data-due-picker-type="' + selectedType + '"]').focus();
        });
        dialog.querySelector('[data-due-picker-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const bounds = dialog.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
        });
        window.addEventListener('pageshow', event => {
            if (event.persisted && dialog.open) dialog.close();
        });
        @if (View::hasSection('due_picker_autostart'))
            open(new URL(location.href), @json($pickerTenant ?? false));
        @endif
    })();
</script>
