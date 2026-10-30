<div id="expense-plan-supplier-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" id="expense-plan-supplier-overlay"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-950">Yeni Tedarikçi Ekle</h3>
                <button type="button" id="close-expense-plan-supplier-modal" aria-label="Pencereyi kapat" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="expense-plan-supplier-form" class="space-y-4">
                @csrf
                <input type="hidden" name="type" value="supplier">
                <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">

                <div>
                    <label for="expense-plan-supplier-name" class="mb-2 block text-sm font-semibold text-slate-700">Tedarikçi Adı</label>
                    <input id="expense-plan-supplier-name" type="text" name="name" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none" placeholder="Örn. A Enerji, Su ve Kanalizasyon">
                </div>

                <div>
                    <label for="expense-plan-supplier-category" class="mb-2 block text-sm font-semibold text-slate-700">Kategori <span class="font-normal text-slate-400">(opsiyonel)</span></label>
                    <select id="expense-plan-supplier-category" name="default_category_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        <option value="">Kategori seçin</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="expense-plan-supplier-opening-date" class="mb-2 block text-sm font-semibold text-slate-700">Açılış Tarihi</label>
                        <input id="expense-plan-supplier-opening-date" type="date" name="account_opening_date" value="{{ now()->toDateString() }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    </div>
                    <div>
                        <label for="expense-plan-supplier-balance" class="mb-2 block text-sm font-semibold text-slate-700">Açılış Bakiyesi</label>
                        <input id="expense-plan-supplier-balance" type="number" name="balance" min="0" step="0.01" value="0" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    </div>
                </div>

                <div id="expense-plan-supplier-opening-side" class="hidden">
                    <p class="mb-2 text-sm font-semibold text-slate-700">Girilen bakiye borç mu, alacak mı?</p>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-4 py-3 text-sm has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50">
                            <input type="radio" name="opening_side" value="debit" class="h-4 w-4 accent-emerald-600">
                            Borç
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-4 py-3 text-sm has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50">
                            <input type="radio" name="opening_side" value="credit" class="h-4 w-4 accent-emerald-600">
                            Alacak
                        </label>
                    </div>
                </div>

                <div id="expense-plan-supplier-errors" class="hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert"></div>

                <div class="flex gap-3">
                    <button type="button" id="cancel-expense-plan-supplier" class="flex-1 rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">İptal</button>
                    <button type="submit" class="flex-1 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (() => {
        const modal = document.getElementById('expense-plan-supplier-modal');
        const overlay = document.getElementById('expense-plan-supplier-overlay');
        const openButton = document.getElementById('open-expense-plan-supplier-modal');
        const closeButton = document.getElementById('close-expense-plan-supplier-modal');
        const cancelButton = document.getElementById('cancel-expense-plan-supplier');
        const form = document.getElementById('expense-plan-supplier-form');
        const accountSelect = document.getElementById('account_id');
        const balanceInput = document.getElementById('expense-plan-supplier-balance');
        const openingSide = document.getElementById('expense-plan-supplier-opening-side');
        const errors = document.getElementById('expense-plan-supplier-errors');

        const syncOpeningSide = () => {
            const hasBalance = Math.abs(parseFloat(balanceInput?.value || '0')) >= 0.01;
            openingSide?.classList.toggle('hidden', !hasBalance);
            openingSide?.querySelectorAll('input[name="opening_side"]').forEach((input) => {
                input.disabled = !hasBalance;
                if (!hasBalance) input.checked = false;
            });
        };
        const clearErrors = () => {
            errors.textContent = '';
            errors.classList.add('hidden');
        };
        const showErrors = (message) => {
            errors.textContent = message;
            errors.classList.remove('hidden');
        };
        const openModal = () => {
            clearErrors();
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            syncOpeningSide();
            document.getElementById('expense-plan-supplier-name')?.focus();
        };
        const closeModal = () => {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
            form.reset();
            clearErrors();
            syncOpeningSide();
        };

        openButton?.addEventListener('click', openModal);
        closeButton?.addEventListener('click', closeModal);
        cancelButton?.addEventListener('click', closeModal);
        overlay?.addEventListener('click', closeModal);
        balanceInput?.addEventListener('input', syncOpeningSide);

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors();
            const submitButton = form.querySelector('button[type="submit"]');

            try {
                submitButton.disabled = true;
                submitButton.textContent = 'Kaydediliyor...';
                const response = await fetch('{{ route('accounts.store', [], false) }}', {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                });
                const data = await response.json();

                if (!response.ok || !data.account) {
                    const message = data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : (data.message || 'Tedarikçi oluşturulamadı.');
                    showErrors(message);
                    return;
                }

                const option = document.createElement('option');
                option.value = data.account.id;
                option.textContent = `${data.account.name} (${data.account.type})`;
                option.dataset.categoryId = data.account.default_category_id || '';
                option.selected = true;
                accountSelect.appendChild(option);
                closeModal();
                accountSelect.dispatchEvent(new Event('change'));
            } catch (error) {
                showErrors('Tedarikçi oluşturulurken bağlantı hatası oluştu.');
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Kaydet';
            }
        });
    })();
</script>
