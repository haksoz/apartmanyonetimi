<div id="due-payment-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-900 mb-1">Tahsilat Ekle</h3>
        <p class="text-sm text-slate-500 mb-4">Borçlu aidat için ödeme kaydı oluşturun.</p>

        <div id="due-payment-info" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-700 mb-4">
            <div class="font-semibold text-slate-950" id="due-payment-description"></div>
            <div class="mt-1">Tutar: <span class="font-medium text-slate-900" id="due-payment-amount"></span></div>
            <div class="mt-1">Hesap: <span class="font-medium text-slate-900" id="due-payment-account"></span></div>
        </div>

        <form id="due-payment-form" method="POST" action="" data-base-url="{{ route('dues.payment.store', ['due' => '__DUE_ID__']) }}">
            @csrf
            <input type="hidden" name="due_id" id="due-payment-due-id">
            <input type="hidden" name="amount" id="due-payment-amount-input">

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
                    <input type="date" name="payment_date" required value="{{ now()->toDateString() }}"
                           class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Açıklama <span class="text-slate-400">(opsiyonel)</span></label>
                    <input type="text" name="description" id="due-payment-description-input"
                           class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none"
                           placeholder="Aidat Tahsilatı">
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="submit" class="flex-1 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                    Tahsil Et
                </button>
                <button type="button" onclick="closeDuePaymentModal()"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">
                    İptal
                </button>
            </div>
        </form>
    </div>
</div>
