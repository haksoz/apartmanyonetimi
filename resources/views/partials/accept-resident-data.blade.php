<div class="space-y-2">
    <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-600" data-consent-open="resident-data-dialog">
        <input type="checkbox" name="accept_resident_data" value="1" class="pointer-events-none mt-0.5" tabindex="-1" @checked(old('accept_resident_data')) required>
        <span><strong class="font-bold text-slate-950">Daire sakini verisi bildirimini</strong> okudum, bu apartmandaki daire sakini bilgilerini girmeye yetkiliyim.</span>
    </label>
    @error('accept_resident_data')
        <div class="text-sm text-red-600">{{ $message }}</div>
    @enderror

    <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-600" data-consent-open="apartment-privacy-dialog">
        <input type="checkbox" name="accept_privacy" value="1" class="pointer-events-none mt-0.5" tabindex="-1" @checked(old('accept_privacy')) required>
        <span><strong class="font-bold text-slate-950">Gizlilik ve KVKK aydınlatmasını</strong> okudum, kabul ediyorum.</span>
    </label>
    @error('accept_privacy')
        <div class="text-sm text-red-600">{{ $message }}</div>
    @enderror
</div>

<div id="resident-data-dialog" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="resident-data-dialog-title">
    <div class="flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl bg-white p-4 shadow-xl">
        <h2 id="resident-data-dialog-title" class="text-base font-bold text-slate-950">Daire Sakini Verisi</h2>
        <div class="mt-3 overflow-y-auto text-sm leading-relaxed text-slate-600">
            @include('legal.content.resident-data')
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" data-consent-close class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Kapat</button>
            <button type="button" data-consent-accept="accept_resident_data" class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Okudum, kabul ediyorum</button>
        </div>
    </div>
</div>

<div id="apartment-privacy-dialog" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="apartment-privacy-dialog-title">
    <div class="flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl bg-white p-4 shadow-xl">
        <h2 id="apartment-privacy-dialog-title" class="text-base font-bold text-slate-950">Gizlilik ve KVKK</h2>
        <div class="mt-3 overflow-y-auto text-sm leading-relaxed text-slate-600">
            @include('legal.content.privacy')
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" data-consent-close class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Kapat</button>
            <button type="button" data-consent-accept="accept_privacy" class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Okudum, kabul ediyorum</button>
        </div>
    </div>
</div>

<script>
    (function () {
        const openDialog = function (id) {
            const dialog = document.getElementById(id);
            if (!dialog) return;
            dialog.classList.remove('hidden');
            dialog.classList.add('flex');
        };
        const closeDialog = function (dialog) {
            dialog.classList.add('hidden');
            dialog.classList.remove('flex');
        };
        document.querySelectorAll('[data-consent-open]').forEach(function (label) {
            if (label.dataset.consentBound) return;
            label.dataset.consentBound = '1';
            label.addEventListener('click', function (event) {
                event.preventDefault();
                openDialog(label.getAttribute('data-consent-open'));
            });
        });
        document.querySelectorAll('[data-consent-close]').forEach(function (button) {
            if (button.dataset.consentBound) return;
            button.dataset.consentBound = '1';
            button.addEventListener('click', function () {
                closeDialog(button.closest('[role="dialog"]'));
            });
        });
        document.querySelectorAll('[data-consent-accept]').forEach(function (button) {
            if (button.dataset.consentBound) return;
            button.dataset.consentBound = '1';
            button.addEventListener('click', function () {
                const input = document.querySelector('[name="' + button.getAttribute('data-consent-accept') + '"]');
                if (input) input.checked = true;
                closeDialog(button.closest('[role="dialog"]'));
            });
        });
        document.querySelectorAll('[role="dialog"]').forEach(function (dialog) {
            if (dialog.dataset.consentBound) return;
            dialog.dataset.consentBound = '1';
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) closeDialog(dialog);
            });
        });
    })();
</script>
