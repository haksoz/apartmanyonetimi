<div id="expense-payment-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-900 mb-1" id="expense-payment-modal-title">Gider Ödemesi</h3>
        <p class="text-sm text-slate-500 mb-4" id="expense-payment-modal-subtitle">Gider ödemesi yapın.</p>

        <div id="expense-payment-info-single" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-700 mb-4">
            <div class="font-semibold text-slate-950" id="expense-payment-description"></div>
            <div class="mt-1">Kategori: <span class="font-medium text-slate-900" id="expense-payment-category"></span></div>
            <div class="mt-1">Tutar: <span class="font-medium text-slate-900" id="expense-payment-amount"></span></div>
            <div class="mt-1">Tedarikçi: <span class="font-medium text-slate-900" id="expense-payment-supplier"></span></div>
        </div>

        <div id="expense-payment-info-multi" class="hidden rounded-xl bg-slate-50 p-4 text-sm text-slate-700 mb-4">
            <div id="expense-payment-multi-list" class="max-h-48 overflow-y-auto divide-y divide-slate-100"></div>
            <div class="mt-3 pt-3 border-t border-slate-200 font-semibold text-slate-900 text-right">
                Toplam: <span id="expense-payment-multi-total"></span>
            </div>
        </div>

        <form id="expense-payment-form" method="POST" action=""
              data-base-url-single="{{ route('expenses.payment.store', ['expense' => '__EXPENSE_ID__']) }}"
              data-base-url-multi="{{ route('accounts.expenses.multi-pay.store', ['account' => '__ACCOUNT_ID__']) }}">
            @csrf
            <input type="hidden" name="expense_id" id="expense-payment-expense-id">
            <input type="hidden" name="amount" id="expense-payment-amount-input">
            <input type="hidden" name="expense_ids" id="expense-payment-expense-ids">

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Kasa</label>
                    <select name="cash_box_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        <option value="">Kasa seçin</option>
                        @foreach ($cashBoxes as $cashBox)
                            <option value="{{ $cashBox->id }}">{{ $cashBox->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Ödeme Tarihi</label>
                    <input type="date" id="expense-payment-date" name="payment_date" required value="{{ now()->toDateString() }}"
                           class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Açıklama <span class="text-slate-400">(opsiyonel)</span></label>
                    <input type="text" name="description" id="expense-payment-description-input"
                           class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="submit" class="flex-1 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                    Ödemeyi Kasaya İşle
                </button>
                <button type="button" onclick="closeExpensePaymentModal()"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">
                    İptal
                </button>
            </div>
        </form>
    </div>
</div>
