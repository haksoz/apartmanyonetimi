@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Gider Ekle</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }} için yeni gider kaydı oluşturun.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('expenses.index') }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Giderlere Dön</a>
        </div>
    </div>

    <form method="POST" action="{{ route('expenses.store') }}" class="space-y-4">
        @csrf
        <input id="period_month" name="period_month" type="hidden" value="{{ old('period_month', now()->format('Y-m')) }}">

        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <label for="account_id" class="text-sm font-medium text-slate-600">Hesap / Tedarikçi</label>
                        <button type="button" id="open-supplier-modal" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700">+ Yeni Tedarikçi Ekle</button>
                    </div>
                    <select id="account_id" name="account_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        <option value="">Hesap seçmeden kaydet</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('account_id', $selectedAccountId ?? '') === (string) $account->id)>{{ $account->name }} ({{ $account->type }})</option>
                        @endforeach
                    </select>
                    @error('account_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="category_id" class="mb-2 block text-sm font-medium text-slate-600">Kategori</label>
                    <select id="category_id" name="category_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        <option value="">Kategori seçin</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="expense_date" class="mb-2 block text-sm font-medium text-slate-600">Gider Tarihi</label>
                    <input id="expense_date" name="expense_date" type="date" value="{{ old('expense_date', now()->toDateString()) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('expense_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="due_date" class="mb-2 block text-sm font-medium text-slate-600">Son Ödeme Tarihi <span class="font-normal text-slate-400">(opsiyonel)</span></label>
                    <input id="due_date" name="due_date" type="date" value="{{ old('due_date') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('due_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="amount" class="mb-2 block text-sm font-medium text-slate-600">Tutar</label>
                    <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="description" class="mb-2 block text-sm font-medium text-slate-600">Açıklama</label>
                    <input id="description" name="description" value="{{ old('description') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none" placeholder="Fatura no, dönem veya kısa açıklama">
                    @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Ödeme Bilgisi --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Ödeme Bilgisi</h3>
            <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700 cursor-pointer" id="is-paid-label">
                <input type="checkbox" name="is_paid" id="is_paid" value="1" @checked(old('is_paid')) class="rounded border-slate-300">
                Bu gider ödendi
            </label>

            <div id="payment-fields" class="hidden mt-4 grid gap-5 md:grid-cols-2">
                <div>
                    <label for="payment_date" class="mb-2 block text-sm font-medium text-slate-600">Ödeme Tarihi</label>
                    <input id="payment_date" name="payment_date" type="date" value="{{ old('payment_date', now()->toDateString()) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('payment_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="cash_box_id" class="mb-2 block text-sm font-medium text-slate-600">Ödeme Yapılan Kasa</label>
                    <select id="cash_box_id" name="cash_box_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        <option value="">Kasa seçin</option>
                        @foreach ($cashBoxes as $cashBox)
                            <option value="{{ $cashBox->id }}" @selected((string) old('cash_box_id') === (string) $cashBox->id)>{{ $cashBox->name }}</option>
                        @endforeach
                    </select>
                    @error('cash_box_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="w-full md:w-auto rounded-xl bg-slate-950 px-8 py-3 text-sm font-semibold text-white hover:bg-slate-800">Gideri Kaydet</button>
        </div>
    </form>

    <script>
        // Global scope for access from modal script
        const accountCategoryMap = {!! $accountCategoryMap !!};

        (() => {
            // Use global accountCategoryMap

            const accountSelect = document.getElementById('account_id');
            const categorySelect = document.getElementById('category_id');
            const periodInput = document.getElementById('period_month');
            const expenseDateInput = document.getElementById('expense_date');
            const descriptionInput = document.getElementById('description');
            const isPaidCheckbox = document.getElementById('is_paid');
            const paymentFields = document.getElementById('payment-fields');
            const dueDateInput = document.getElementById('due_date');
            let isDueDateManuallySet = !!dueDateInput?.value;

            // Auto-fill category from selected account's default
            const fillCategoryFromAccount = () => {
                const accountId = accountSelect.value;
                if (accountId && accountCategoryMap[accountId]) {
                    const defaultCatId = accountCategoryMap[accountId].toString();
                    const option = [...categorySelect.options].find(o => o.value === defaultCatId);
                    if (option && !categorySelect.value) {
                        categorySelect.value = defaultCatId;
                        updateDescription();
                    }
                }
            };

            accountSelect?.addEventListener('change', () => {
                categorySelect.value = '';
                fillCategoryFromAccount();
            });

            // Toggle payment fields visibility
            const togglePaymentFields = () => {
                if (isPaidCheckbox.checked) {
                    paymentFields.classList.remove('hidden');
                } else {
                    paymentFields.classList.add('hidden');
                }
            };

            isPaidCheckbox?.addEventListener('change', togglePaymentFields);
            togglePaymentFields();

            const months = {
                '01': 'Ocak', '02': 'Şubat', '03': 'Mart', '04': 'Nisan',
                '05': 'Mayıs', '06': 'Haziran', '07': 'Temmuz', '08': 'Ağustos',
                '09': 'Eylül', '10': 'Ekim', '11': 'Kasım', '12': 'Aralık'
            };

            const syncPeriodFromDate = () => {
                const dateVal = expenseDateInput?.value;
                if (dateVal) {
                    periodInput.value = dateVal.substring(0, 7);
                }
            };

            const syncDueDate = () => {
                const dateVal = expenseDateInput?.value;
                if (dateVal && !isDueDateManuallySet) {
                    const dueDate = new Date(dateVal);
                    dueDate.setDate(dueDate.getDate() + 15);
                    const year = dueDate.getFullYear();
                    const month = String(dueDate.getMonth() + 1).padStart(2, '0');
                    const day = String(dueDate.getDate()).padStart(2, '0');
                    dueDateInput.value = `${year}-${month}-${day}`;
                }
            };

            const updateDescription = () => {
                const period = periodInput.value;
                const categoryOption = categorySelect.options[categorySelect.selectedIndex];
                const categoryName = categoryOption ? categoryOption.text : '';

                if (period && categoryName) {
                    const [year, month] = period.split('-');
                    const monthName = months[month] || month;
                    descriptionInput.value = `${monthName} ${year} - ${categoryName}`;
                }
            };

            expenseDateInput?.addEventListener('change', () => {
                syncPeriodFromDate();
                syncDueDate();
                updateDescription();
            });

            dueDateInput?.addEventListener('input', () => {
                isDueDateManuallySet = dueDateInput.value !== '';
            });

            categorySelect?.addEventListener('change', updateDescription);

            // Init
            syncPeriodFromDate();
            syncDueDate();

            // On page load: if account is pre-selected, fill category
            if (accountSelect?.value) fillCategoryFromAccount();
        })();
    </script>

    {{-- Supplier Modal --}}
    <div id="supplier-modal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black/50" id="modal-overlay"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-950">Yeni Tedarikçi Ekle</h3>
                    <button type="button" id="close-supplier-modal" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <form id="supplier-form" class="space-y-4">
                    <input type="hidden" name="type" value="supplier">
                    <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">
                    @csrf

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Tedarikçi Adı</label>
                        <input type="text" name="name" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none" placeholder="Örn. A Enerji, Su ve Kanalizasyon">
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Varsayılan Kategori <span class="text-slate-400 font-normal">(opsiyonel)</span></label>
                        <select name="default_category_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                            <option value="">Kategori seçin</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Telefon</label>
                            <input type="text" name="phone" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">E-posta</label>
                            <input type="email" name="email" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Açılış Tarihi</label>
                            <input type="date" name="account_opening_date" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Açılış Bakiyesi</label>
                            <input type="number" name="balance" step="0.01" value="0" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="button" id="cancel-supplier" class="flex-1 rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">İptal</button>
                        <button type="submit" class="flex-1 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('supplier-modal');
            const overlay = document.getElementById('modal-overlay');
            const openBtn = document.getElementById('open-supplier-modal');
            const closeBtn = document.getElementById('close-supplier-modal');
            const cancelBtn = document.getElementById('cancel-supplier');
            const supplierForm = document.getElementById('supplier-form');
            const accountSelect = document.getElementById('account_id');

            const openModal = () => {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
                supplierForm.reset();
            };

            openBtn?.addEventListener('click', openModal);
            closeBtn?.addEventListener('click', closeModal);
            cancelBtn?.addEventListener('click', closeModal);
            overlay?.addEventListener('click', closeModal);

            supplierForm?.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(supplierForm);
                const submitBtn = supplierForm.querySelector('button[type="submit"]');

                try {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Kaydediliyor...';

                    const response = await fetch('{{ route('accounts.store', [], false) }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        }
                    });

                    const data = await response.json();

                    if (response.ok && data.account) {
                        const option = document.createElement('option');
                        option.value = data.account.id;
                        option.textContent = `${data.account.name} (${data.account.type})`;
                        option.selected = true;
                        accountSelect.appendChild(option);
                        // Update category map with new account's default
                        if (data.account.default_category_id) {
                            accountCategoryMap[data.account.id] = data.account.default_category_id;
                        }
                        closeModal();
                        // Trigger auto-fill
                        accountSelect.dispatchEvent(new Event('change'));
                    } else {
                        alert('Hata: ' + (data.message || 'Tedarikçi oluşturulamadı'));
                    }
                } catch (error) {
                    alert('Hata: ' + error.message);
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Kaydet';
                }
            });
        })();
    </script>
@endsection
