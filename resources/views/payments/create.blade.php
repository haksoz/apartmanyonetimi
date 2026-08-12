@extends('layouts.app')

@section('content')
    @php
        $selectedAccount = $accounts->firstWhere('id', (int) old('account_id', $selectedAccountId ?? 0));
    @endphp

    @if ($selectedAccount)
        {{-- Breadcrumb + Hesap Sekmeleri --}}
        <div class="mb-6 flex flex-row items-center justify-between gap-2 md:gap-4 min-w-0">
            <div class="flex items-center gap-2 min-w-0 overflow-x-auto">
                <a href="{{ route('accounts.index') }}" class="shrink-0 min-h-[3.5rem] sm:min-h-0 inline-flex items-center justify-center rounded-2xl sm:rounded-xl border border-slate-300 px-5 text-xs sm:text-sm sm:px-4 sm:py-2.5 font-semibold text-slate-700 bg-slate-50 hover:bg-slate-100">
                    Hesaplar
                </a>
                <span class="text-slate-400">/</span>
                <a href="{{ route('accounts.show', $selectedAccount) }}" class="shrink-0 min-h-[3.5rem] sm:min-h-0 inline-flex items-center justify-center rounded-2xl sm:rounded-xl border border-slate-300 px-5 text-xs sm:text-sm sm:px-4 sm:py-2.5 font-semibold text-slate-700 bg-white hover:bg-slate-50">
                    @if ($selectedAccount->unit)
                        Daire {{ str_pad($selectedAccount->unit->unit_no, 2, '0', STR_PAD_LEFT) }}
                    @else
                        {{ $selectedAccount->type_label }}
                    @endif
                </a>
            </div>

            <div class="flex items-center justify-end gap-2 shrink-0">
                @include('accounts._tabs', ['account' => $selectedAccount, 'active' => 'payment', 'withOverview' => false])
            </div>
        </div>
    @else
        <div class="mb-6 flex flex-row items-center justify-between gap-2 md:gap-4 min-w-0">
            <a href="{{ route('accounts.index') }}" class="shrink-0 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 bg-slate-50 hover:bg-slate-100">
                Hesaplar
            </a>
            <div class="flex gap-2">
                <a href="{{ route('accounts.index') }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Hesaplara Dön</a>
            </div>
        </div>
    @endif

    <form id="payment-create-form" method="POST" action="{{ route('payments.store') }}" class="rounded-2xl bg-white p-6 shadow-sm">
        @csrf

        <h1 class="text-2xl font-bold text-slate-950">Ödeme Al / Tahsilat Ekle</h1>
        <p class="mt-1 text-sm text-slate-500">Yeni ödeme kaydı oluşturun. İsterseniz hemen borçlara tahsis edebilir ya da sonra yapabilirsiniz.</p>

        <div class="mt-5 grid gap-5 border-t border-slate-100 pt-5 lg:grid-cols-2">
            <div>
                <label for="account_id" class="mb-2 block text-sm font-semibold text-slate-700">Cari/Hesap</label>
                <select id="account_id" name="{{ $selectedAccountId ? null : 'account_id' }}" required
                    {{ $selectedAccountId ? 'disabled' : '' }}
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none {{ $selectedAccountId ? 'bg-slate-100 cursor-not-allowed' : '' }}">
                    <option value="">Cari seçin</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected(old('account_id', $selectedAccountId) == $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
                @if ($selectedAccountId)
                    <input type="hidden" name="account_id" value="{{ $selectedAccountId }}">
                @endif
                @error('account_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="payment_date" class="mb-2 block text-sm font-semibold text-slate-700">Ödeme Tarihi</label>
                <input id="payment_date" name="payment_date" type="date" value="{{ old('payment_date', now()->toDateString()) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('payment_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="amount" class="mb-2 block text-sm font-semibold text-slate-700">Tutar</label>
                @php $suggestedDebt = $selectedAccountId ? ($accountDebts[$selectedAccountId] ?? null) : null; @endphp
                <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $suggestedDebt && $suggestedDebt > 0 ? $suggestedDebt : '') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                <p id="debt-hint" class="mt-1.5 text-xs text-slate-400 {{ $suggestedDebt && $suggestedDebt > 0 ? '' : 'hidden' }}">Kalan borç önerildi: <span id="debt-hint-amount">{{ $suggestedDebt ? number_format($suggestedDebt, 2, ',', '.') : '0,00' }}</span> TL</p>
                @error('amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="cash_box_id" class="mb-2 block text-sm font-semibold text-slate-700">Kasa</label>
                <select id="cash_box_id" name="cash_box_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="">Kasa seçin</option>
                    @foreach ($cashBoxes as $cashBox)
                        <option value="{{ $cashBox->id }}" @selected(old('cash_box_id') == $cashBox->id)>{{ $cashBox->name }}</option>
                    @endforeach
                </select>
                @error('cash_box_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="description" class="mb-2 block text-sm font-semibold text-slate-700">Açıklama</label>
                <input id="description" name="description" type="text" value="{{ old('description') }}" placeholder="Opsiyonel açıklama (örn: kısmi ödeme, avans)" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="button" id="btn-fifo-popup"
                class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700">
                Ödeme Al / Tahsil Et
            </button>
        </div>
    </form>

    {{-- FIFO Popup Modal --}}
    <div id="fifo-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
        <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl flex flex-col max-h-[90vh]">

            {{-- Başlık --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 shrink-0">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Ödeme Al / Tahsil Et</h2>
                    <p id="fifo-modal-subtitle" class="text-xs text-slate-500 mt-0.5"></p>
                </div>
                <button type="button" id="fifo-modal-close" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Loading --}}
            <div id="fifo-loading" class="flex items-center justify-center py-12">
                <svg class="animate-spin h-6 w-6 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
            </div>

            {{-- İçerik: açık aidat var --}}
            <div id="fifo-content" class="hidden flex-1 overflow-y-auto px-6 py-4">

                {{-- FIFO / Temizle butonları --}}
                <div class="flex items-center gap-2 mb-4">
                    <button type="button" id="fifo-btn-auto" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Eskiden Yeniye Otomatik Dağıt (FIFO)
                    </button>
                    <button type="button" id="fifo-btn-clear" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Temizle
                    </button>
                </div>

                {{-- Aidat tablosu --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 mb-4">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Tarih</th>
                                <th class="px-4 py-3">Açıklama</th>
                                <th class="hidden sm:table-cell px-4 py-3 text-right">Kalan</th>
                                <th class="px-4 py-3 text-right">
                                    <span class="hidden sm:inline">Tahsis</span>
                                    <span class="sm:hidden">Kalan / Tahsis</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="fifo-dues-tbody" class="divide-y divide-slate-100">
                        </tbody>
                    </table>
                </div>

                {{-- Canlı özet --}}
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-5 py-3 text-sm flex flex-wrap gap-x-8 gap-y-1.5">
                    <div>
                        <span class="text-slate-500">Tahsis Edilecek:</span>
                        <span id="fifo-sum-allocated" class="ml-1 font-bold text-slate-900">0,00 TL</span>
                    </div>
                    <div>
                        <span class="text-slate-500">Bağlanacak Bakiye:</span>
                        <span id="fifo-budget-display" class="ml-1 font-bold text-slate-900">0,00 TL</span>
                    </div>
                    <div>
                        <span class="text-slate-500">Kalan (Tahsis Edilmeyecek):</span>
                        <span id="fifo-sum-remaining" class="ml-1 font-bold text-slate-900">0,00 TL</span>
                    </div>
                </div>

                <div id="fifo-error-msg" class="hidden mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"></div>
            </div>

            {{-- İçerik: açık aidat yok --}}
            <div id="fifo-no-dues" class="hidden px-6 py-8 text-center">
                <svg class="mx-auto h-10 w-10 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p class="text-sm font-medium text-slate-700 mb-1">Bu hesap için açık aidat bulunamadı.</p>
                <p class="text-xs text-slate-500">Ödemeyi yine de kaydedebilirsiniz; tahsis edilmemiş ödeme olarak cari'ye işlenecektir.</p>
            </div>

            {{-- Footer butonlar --}}
            <div id="fifo-footer" class="hidden shrink-0 flex items-center justify-between gap-3 px-6 py-4 border-t border-slate-200">
                <button type="button" id="fifo-modal-cancel" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">
                    İptal
                </button>
                <div class="flex gap-3">
                    <button type="button" id="fifo-btn-save-only" class="hidden rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                        Yine de Kaydet (Tahsis Etmeden)
                    </button>
                    <button type="button" id="fifo-btn-confirm" class="hidden rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Onayla ve Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const descriptionInput = document.getElementById('description');
            const paymentDateInput = document.getElementById('payment_date');
            const monthNames = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

            const formatDescription = (dateString) => {
                if (!dateString) return '';
                const [year, month] = dateString.split('-');
                if (!year || !month) return '';
                return `${year} ${monthNames[parseInt(month, 10) - 1]} Toplu Borç Tahsilatı`;
            };

            const isAutoDescription = (current, dateString) => {
                if (!current) return true;
                return current === formatDescription(dateString);
            };

            if (paymentDateInput && descriptionInput) {
                const updateDescription = () => {
                    if (isAutoDescription(descriptionInput.value.trim(), paymentDateInput.value)) {
                        descriptionInput.value = formatDescription(paymentDateInput.value);
                    }
                };
                paymentDateInput.addEventListener('change', updateDescription);
                updateDescription();
            }

            const accountDebts = @json($accountDebts);
            const accountSelect = document.getElementById('account_id');
            const amountInput   = document.getElementById('amount');
            const debtHint      = document.getElementById('debt-hint');
            const debtHintAmount = document.getElementById('debt-hint-amount');
            const mainForm      = document.getElementById('payment-create-form');

            const formatMoney = (n) =>
                n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' TL';

            const toFloat = (v) => {
                const n = parseFloat(String(v).replace(',', '.'));
                return Number.isFinite(n) ? n : 0;
            };

            if (accountSelect && !accountSelect.disabled) {
                accountSelect.addEventListener('change', () => {
                    const debt = parseFloat(accountDebts[accountSelect.value]) || 0;
                    if (debt > 0) {
                        amountInput.value = debt.toFixed(2);
                        debtHintAmount.textContent = formatMoney(debt);
                        debtHint.classList.remove('hidden');
                    } else {
                        amountInput.value = '';
                        debtHint.classList.add('hidden');
                    }
                });
            }

            // ── FIFO Popup ──────────────────────────────────────────────────
            const modal         = document.getElementById('fifo-modal');
            const loading       = document.getElementById('fifo-loading');
            const content       = document.getElementById('fifo-content');
            const noDues        = document.getElementById('fifo-no-dues');
            const footer        = document.getElementById('fifo-footer');
            const tbody         = document.getElementById('fifo-dues-tbody');
            const sumAlloc      = document.getElementById('fifo-sum-allocated');
            const budgetDisplay = document.getElementById('fifo-budget-display');
            const sumRemain     = document.getElementById('fifo-sum-remaining');
            const errorMsg      = document.getElementById('fifo-error-msg');
            const subtitle      = document.getElementById('fifo-modal-subtitle');
            const btnSaveOnly   = document.getElementById('fifo-btn-save-only');
            const btnConfirm    = document.getElementById('fifo-btn-confirm');

            let budget = 0;
            let duesData = [];

            const showLoading = () => {
                loading.classList.remove('hidden');
                content.classList.add('hidden');
                noDues.classList.add('hidden');
                footer.classList.add('hidden');
            };

            const showContent = () => {
                loading.classList.add('hidden');
                content.classList.remove('hidden');
                noDues.classList.add('hidden');
                footer.classList.remove('hidden');
                btnSaveOnly.classList.add('hidden');
                btnConfirm.classList.remove('hidden');
            };

            const showNoDues = () => {
                loading.classList.add('hidden');
                content.classList.add('hidden');
                noDues.classList.remove('hidden');
                footer.classList.remove('hidden');
                btnConfirm.classList.add('hidden');
                btnSaveOnly.classList.remove('hidden');
            };

            const closeModal = () => modal.classList.add('hidden');

            document.getElementById('fifo-modal-close').addEventListener('click', closeModal);
            document.getElementById('fifo-modal-cancel').addEventListener('click', closeModal);
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
                duesData.forEach((due, idx) => {
                    const tr = document.createElement('tr');
                    tr.className = 'divide-x-0';
                    const remainingFormatted = due.remaining_amount.toLocaleString('tr-TR', {minimumFractionDigits:2,maximumFractionDigits:2}) + ' TL';
                    tr.innerHTML = `
                        <td class="px-4 py-3 text-slate-700 whitespace-nowrap">${due.due_date}</td>
                        <td class="px-4 py-3 text-slate-700">
                            ${due.description}
                            ${due.is_imported ? '<span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>' : ''}
                        </td>
                        <td class="hidden sm:table-cell px-4 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">${remainingFormatted}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="sm:hidden mb-2 text-right font-semibold text-slate-900 whitespace-nowrap">${remainingFormatted}</div>
                            <input type="hidden" name="_popup_alloc_due_id_${idx}" data-due-id="${due.id}">
                            <div class="flex flex-col sm:flex-row items-end sm:items-center justify-end gap-2">
                                <input
                                    type="number" min="0" step="0.01" max="${due.remaining_amount}"
                                    data-alloc data-remaining="${due.remaining_amount}" data-idx="${idx}"
                                    value="${due.suggested_amount > 0 ? due.suggested_amount.toFixed(2) : ''}"
                                    class="w-full sm:w-28 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none"
                                >
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
                    if (inp) {
                        inp.value = toFloat(inp.dataset.remaining).toFixed(2);
                        updateSummary();
                    }
                });
            };

            // FIFO otomatik dağıt
            document.getElementById('fifo-btn-auto').addEventListener('click', () => {
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

            // Temizle
            document.getElementById('fifo-btn-clear').addEventListener('click', () => {
                tbody.querySelectorAll('input[data-alloc]').forEach(inp => inp.value = '');
                updateSummary();
            });

            // Popup aç
            document.getElementById('btn-fifo-popup').addEventListener('click', () => {
                const accountSelectEl = document.getElementById('account_id');
                const accountId = accountSelectEl?.value
                    || document.querySelector('input[name="account_id"]')?.value
                    || '';
                const amount    = toFloat(amountInput.value);

                if (!amount || amount <= 0) {
                    alert('Lütfen önce ödeme tutarını girin.');
                    return;
                }

                modal.classList.remove('hidden');
                showLoading();
                subtitle.textContent = amount.toLocaleString('tr-TR', {minimumFractionDigits:2}) + ' TL ödeme';

                const url = new URL('{{ route('payments.preview-allocations') }}', window.location.origin);
                if (accountId) url.searchParams.set('account_id', accountId);
                url.searchParams.set('amount', amount);

                fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(data => {
                    budget = amount;
                    budgetDisplay.textContent = formatMoney(budget);

                    if (!data.has_dues) {
                        showNoDues();
                        return;
                    }

                    duesData = data.dues;
                    buildTable();
                    updateSummary();
                    showContent();
                })
                .catch(() => {
                    closeModal();
                    alert('Aidatlar yüklenirken bir hata oluştu. Lütfen tekrar deneyin.');
                });
            });

            // Yine de Kaydet (aidat yok)
            document.getElementById('fifo-btn-save-only').addEventListener('click', () => {
                closeModal();
                const hiddenAction = document.createElement('input');
                hiddenAction.type  = 'hidden';
                hiddenAction.name  = 'action';
                hiddenAction.value = 'save';
                mainForm.appendChild(hiddenAction);
                mainForm.submit();
            });

            // Onayla ve Kaydet
            document.getElementById('fifo-btn-confirm').addEventListener('click', () => {
                // Önce eski inject edilmiş inputları temizle
                mainForm.querySelectorAll('[data-fifo-injected]').forEach(el => el.remove());

                // action
                const actionInp   = document.createElement('input');
                actionInp.type    = 'hidden';
                actionInp.name    = 'action';
                actionInp.value   = 'fifo_popup';
                actionInp.dataset.fifoInjected = '1';
                mainForm.appendChild(actionInp);

                // allocations
                let idx = 0;
                tbody.querySelectorAll('input[data-alloc]').forEach((inp, i) => {
                    const amount = toFloat(inp.value);
                    if (amount <= 0) return;
                    const dueId = inp.closest('tr').querySelector('[data-due-id]')?.dataset.dueId;
                    if (!dueId) return;

                    const dInp   = document.createElement('input');
                    dInp.type    = 'hidden';
                    dInp.name    = `allocations[${idx}][due_id]`;
                    dInp.value   = dueId;
                    dInp.dataset.fifoInjected = '1';
                    mainForm.appendChild(dInp);

                    const aInp   = document.createElement('input');
                    aInp.type    = 'hidden';
                    aInp.name    = `allocations[${idx}][amount]`;
                    aInp.value   = amount.toFixed(2);
                    aInp.dataset.fifoInjected = '1';
                    mainForm.appendChild(aInp);

                    idx++;
                });

                closeModal();
                mainForm.submit();
            });
        })();
    </script>
@endsection
