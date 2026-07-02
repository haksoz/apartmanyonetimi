@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('accounts.index') }}" class="hover:text-slate-600">Hesaplar</a>
                <span>/</span>
                <a href="{{ route('accounts.show', $account) }}" class="hover:text-slate-600">{{ $account->name }}</a>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Tedarikçiye Ödeme Yap</h1>
            <p class="mt-1 text-sm text-slate-500">Yeni ödeme kaydı oluşturun.</p>
        </div>
        <a href="{{ route('accounts.show', $account) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri Dön</a>
    </div>

    <form id="supplier-payment-form" method="POST" action="{{ route('accounts.supplier-payment.store', $account) }}" class="rounded-2xl bg-white p-6 shadow-sm">
        @csrf

        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <label for="account_id" class="mb-2 block text-sm font-semibold text-slate-700">Cari/Hesap</label>
                <select id="account_id" disabled
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none bg-slate-100 cursor-not-allowed">
                    <option selected>{{ $account->name }}</option>
                </select>
                <input type="hidden" name="account_id" value="{{ $account->id }}">
            </div>

            <div>
                <label for="payment_date" class="mb-2 block text-sm font-semibold text-slate-700">Ödeme Tarihi</label>
                <input id="payment_date" type="date" name="payment_date" required
                    value="{{ old('payment_date', now()->toDateString()) }}"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('payment_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="amount" class="mb-2 block text-sm font-semibold text-slate-700">Tutar</label>
                <input id="amount" type="number" name="amount" step="0.01" min="0.01" required
                    value="{{ old('amount') }}" placeholder="0,00"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="cash_box_id" class="mb-2 block text-sm font-semibold text-slate-700">Kasa</label>
                <select id="cash_box_id" name="cash_box_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="">Kasa seçin</option>
                    @foreach ($cashBoxes as $box)
                        <option value="{{ $box->id }}" @selected(old('cash_box_id') == $box->id)>{{ $box->name }}</option>
                    @endforeach
                </select>
                @error('cash_box_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="description" class="mb-2 block text-sm font-semibold text-slate-700">Açıklama</label>
                <input id="description" type="text" name="description" value="{{ old('description') }}"
                    placeholder="Opsiyonel açıklama (örn: fatura ödemesi)"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="button" id="btn-supplier-popup"
                class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700">
                Ödemeyi Kaydet
            </button>
            <a href="{{ route('accounts.show', $account) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Vazgeç
            </a>
        </div>
    </form>

    {{-- Tedarikçi Ödeme FIFO Popup --}}
    <div id="supplier-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
        <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl flex flex-col max-h-[90vh]">

            {{-- Başlık --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 shrink-0">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Ödemeyi Kaydet</h2>
                    <p id="supplier-modal-subtitle" class="text-xs text-slate-500 mt-0.5"></p>
                </div>
                <button type="button" id="supplier-modal-close" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Loading --}}
            <div id="supplier-loading" class="flex items-center justify-center py-12">
                <svg class="animate-spin h-6 w-6 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
            </div>

            {{-- İçerik: açık gider var --}}
            <div id="supplier-content" class="hidden flex-1 overflow-y-auto px-6 py-4">
                <div class="flex items-center gap-2 mb-4">
                    <button type="button" id="supplier-btn-auto" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Eskiden Yeniye Dağıt
                    </button>
                    <button type="button" id="supplier-btn-clear" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Temizle
                    </button>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 mb-4">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Tarih</th>
                                <th class="px-4 py-3">Açıklama</th>
                                <th class="px-4 py-3 text-right">Kalan</th>
                                <th class="px-4 py-3 text-right">Tahsis</th>
                            </tr>
                        </thead>
                        <tbody id="supplier-expenses-tbody" class="divide-y divide-slate-100">
                        </tbody>
                    </table>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-5 py-3 text-sm divide-y divide-slate-200">
                    <div class="flex items-center justify-between py-2">
                        <span class="text-slate-500">Ödeme</span>
                        <span id="supplier-budget-display" class="font-bold text-slate-900">0,00 TL</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-slate-500">Kapanacak Gider</span>
                        <span id="supplier-sum-allocated" class="font-bold text-slate-900">0,00 TL</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-slate-500">Kalan (Tahsis Edilmeyecek)</span>
                        <span id="supplier-sum-remaining" class="font-bold text-slate-900">0,00 TL</span>
                    </div>
                </div>

                <div id="supplier-error-msg" class="hidden mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"></div>
            </div>

            {{-- İçerik: açık gider yok --}}
            <div id="supplier-no-expenses" class="hidden px-6 py-8 text-center">
                <svg class="mx-auto h-10 w-10 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p class="text-sm font-medium text-slate-700 mb-1">Bu tedarikçi için açık gider bulunamadı.</p>
                <p class="text-xs text-slate-500">Ödemeyi yine de kaydedebilirsiniz; tahsis edilmemiş ödeme olarak cari'ye işlenecektir.</p>
            </div>

            {{-- Footer --}}
            <div id="supplier-footer" class="hidden shrink-0 flex items-center justify-between gap-3 px-6 py-4 border-t border-slate-200">
                <button type="button" id="supplier-modal-cancel" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">
                    İptal
                </button>
                <div class="flex gap-3">
                    <button type="button" id="supplier-btn-save-only" class="hidden rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                        Yine de Kaydet (Tahsis Etmeden)
                    </button>
                    <button type="button" id="supplier-btn-confirm" class="hidden rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Onayla ve Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const mainForm      = document.getElementById('supplier-payment-form');
            const amountInput   = document.getElementById('amount');
            const modal         = document.getElementById('supplier-modal');
            const loading       = document.getElementById('supplier-loading');
            const content       = document.getElementById('supplier-content');
            const noExpenses    = document.getElementById('supplier-no-expenses');
            const footer        = document.getElementById('supplier-footer');
            const tbody         = document.getElementById('supplier-expenses-tbody');
            const sumAlloc      = document.getElementById('supplier-sum-allocated');
            const budgetDisplay = document.getElementById('supplier-budget-display');
            const sumRemain     = document.getElementById('supplier-sum-remaining');
            const errorMsg      = document.getElementById('supplier-error-msg');
            const subtitle      = document.getElementById('supplier-modal-subtitle');
            const btnSaveOnly   = document.getElementById('supplier-btn-save-only');
            const btnConfirm    = document.getElementById('supplier-btn-confirm');

            let budget = 0;
            let expensesData = [];

            const toFloat = (v) => { const n = parseFloat(String(v).replace(',','.')); return Number.isFinite(n) ? n : 0; };
            const formatMoney = (n) => n.toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' TL';

            const showLoading = () => {
                loading.classList.remove('hidden');
                content.classList.add('hidden');
                noExpenses.classList.add('hidden');
                footer.classList.add('hidden');
            };
            const showContent = () => {
                loading.classList.add('hidden');
                content.classList.remove('hidden');
                noExpenses.classList.add('hidden');
                footer.classList.remove('hidden');
                btnSaveOnly.classList.add('hidden');
                btnConfirm.classList.remove('hidden');
            };
            const showNoExpenses = () => {
                loading.classList.add('hidden');
                content.classList.add('hidden');
                noExpenses.classList.remove('hidden');
                footer.classList.remove('hidden');
                btnConfirm.classList.add('hidden');
                btnSaveOnly.classList.remove('hidden');
            };
            const closeModal = () => modal.classList.add('hidden');

            document.getElementById('supplier-modal-close').addEventListener('click', closeModal);
            document.getElementById('supplier-modal-cancel').addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

            const updateSummary = () => {
                let total = 0;
                tbody.querySelectorAll('input[data-alloc]').forEach(inp => total += toFloat(inp.value));
                total = Math.round(total * 100) / 100;
                const remaining = Math.round((budget - total) * 100) / 100;

                sumAlloc.textContent  = formatMoney(total);
                sumRemain.textContent = formatMoney(Math.max(0, remaining));

                if (total > budget + 0.001) {
                    sumAlloc.classList.add('text-red-600');
                    sumAlloc.classList.remove('text-slate-900');
                    errorMsg.textContent = 'Tahsis toplamı ödeme tutarını aşıyor.';
                    errorMsg.classList.remove('hidden');
                    btnConfirm.disabled = true;
                } else {
                    sumAlloc.classList.remove('text-red-600');
                    sumAlloc.classList.add('text-slate-900');
                    errorMsg.classList.add('hidden');
                    btnConfirm.disabled = false;
                }
            };

            const buildTable = () => {
                tbody.innerHTML = '';
                expensesData.forEach((exp, idx) => {
                    const tr = document.createElement('tr');
                    tr.className = 'divide-x-0';
                    tr.innerHTML = `
                        <td class="px-4 py-3 text-slate-700 whitespace-nowrap">${exp.expense_date}</td>
                        <td class="px-4 py-3 text-slate-700">
                            ${exp.description}
                            ${exp.is_imported ? '<span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>' : ''}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">${exp.remaining_amount.toLocaleString('tr-TR', {minimumFractionDigits:2,maximumFractionDigits:2})} TL</td>
                        <td class="px-4 py-3 text-right">
                            <input type="hidden" data-expense-id="${exp.id}">
                            <div class="flex items-center justify-end gap-2">
                                <input type="number" min="0" step="0.01" max="${exp.remaining_amount}"
                                    data-alloc data-remaining="${exp.remaining_amount}" data-idx="${idx}"
                                    value="${exp.suggested_amount > 0 ? exp.suggested_amount.toFixed(2) : ''}"
                                    class="w-28 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none">
                                <button type="button" data-fill="${idx}" class="text-xs text-slate-500 hover:underline whitespace-nowrap">Tamamı</button>
                            </div>
                        </td>`;
                    tbody.appendChild(tr);
                });

                tbody.addEventListener('input', (e) => {
                    if (e.target.dataset.alloc !== undefined) updateSummary();
                });
                tbody.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-fill]');
                    if (!btn) return;
                    const idx = parseInt(btn.dataset.fill);
                    const inp = tbody.querySelector(`input[data-idx="${idx}"]`);
                    if (inp) { inp.value = toFloat(inp.dataset.remaining).toFixed(2); updateSummary(); }
                });
            };

            document.getElementById('supplier-btn-auto').addEventListener('click', () => {
                let rem = budget;
                tbody.querySelectorAll('input[data-alloc]').forEach(inp => {
                    if (rem <= 0) { inp.value = ''; return; }
                    const maxA = toFloat(inp.dataset.remaining);
                    const alloc = Math.min(rem, maxA);
                    inp.value = alloc > 0 ? alloc.toFixed(2) : '';
                    rem = Math.round((rem - alloc) * 100) / 100;
                });
                updateSummary();
            });

            document.getElementById('supplier-btn-clear').addEventListener('click', () => {
                tbody.querySelectorAll('input[data-alloc]').forEach(inp => inp.value = '');
                updateSummary();
            });

            document.getElementById('btn-supplier-popup').addEventListener('click', () => {
                const amount = toFloat(amountInput.value);
                if (!amount || amount <= 0) {
                    alert('Lütfen önce ödeme tutarını girin.');
                    return;
                }

                modal.classList.remove('hidden');
                showLoading();
                subtitle.textContent = amount.toLocaleString('tr-TR', {minimumFractionDigits:2}) + ' TL ödeme';

                const url = '{{ route('accounts.supplier-payment.preview-allocations', $account) }}' + '?amount=' + amount;

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(data => {
                        budget = amount;
                        budgetDisplay.textContent = formatMoney(budget);

                        if (!data.has_expenses) {
                            showNoExpenses();
                            return;
                        }

                        expensesData = data.expenses;
                        buildTable();
                        updateSummary();
                        showContent();
                    })
                    .catch(() => {
                        closeModal();
                        alert('Giderler yüklenirken bir hata oluştu. Lütfen tekrar deneyin.');
                    });
            });

            document.getElementById('supplier-btn-save-only').addEventListener('click', () => {
                closeModal();
                const inp  = document.createElement('input');
                inp.type   = 'hidden';
                inp.name   = 'action';
                inp.value  = 'save';
                mainForm.appendChild(inp);
                mainForm.submit();
            });

            document.getElementById('supplier-btn-confirm').addEventListener('click', () => {
                mainForm.querySelectorAll('[data-supplier-injected]').forEach(el => el.remove());

                const actionInp = document.createElement('input');
                actionInp.type  = 'hidden';
                actionInp.name  = 'action';
                actionInp.value = 'fifo_popup';
                actionInp.dataset.supplierInjected = '1';
                mainForm.appendChild(actionInp);

                let idx = 0;
                tbody.querySelectorAll('input[data-alloc]').forEach((inp) => {
                    const amount = toFloat(inp.value);
                    if (amount <= 0) return;
                    const expenseId = inp.closest('tr').querySelector('[data-expense-id]')?.dataset.expenseId;
                    if (!expenseId) return;

                    const eInp = document.createElement('input');
                    eInp.type  = 'hidden';
                    eInp.name  = `allocations[${idx}][expense_id]`;
                    eInp.value = expenseId;
                    eInp.dataset.supplierInjected = '1';
                    mainForm.appendChild(eInp);

                    const aInp = document.createElement('input');
                    aInp.type  = 'hidden';
                    aInp.name  = `allocations[${idx}][amount]`;
                    aInp.value = amount.toFixed(2);
                    aInp.dataset.supplierInjected = '1';
                    mainForm.appendChild(aInp);

                    idx++;
                });

                closeModal();
                mainForm.submit();
            });
        })();
    </script>
@endsection
