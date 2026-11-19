@php
    $modalId = $modalId ?? 'invoice-order-modal';
    $item = $order->items->first();
    $invoicePeriod = $item?->started_at
        ? $item->started_at->format('d.m.Y').' – '.($item->expires_at?->format('d.m.Y') ?? 'süresiz')
        : '—';
@endphp
        <dialog id="{{ $modalId }}" aria-labelledby="{{ $modalId }}-title" class="m-auto max-h-[90vh] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-2xl bg-white p-5 text-slate-900 shadow-xl backdrop:bg-black/50">
            <form method="POST" action="{{ route('admin.orders.invoice.store', $order) }}">
                @csrf
                <input type="hidden" name="invoice_order_id" value="{{ $order->id }}">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="{{ $modalId }}-title" class="text-lg font-bold">Siparişi faturala</h2>
                    <button type="button" onclick="this.closest('dialog').close()" aria-label="Kapat" class="rounded-lg px-3 py-1 text-slate-500 hover:bg-slate-100">✕</button>
                </div>
                <dl class="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Sipariş</dt><dd class="font-medium">{{ $order->order_number }}</dd></div>
                    <div><dt class="text-slate-500">Fatura alıcısı</dt><dd class="font-medium">{{ $order->billing_legal_name ?: $order->user?->name ?: '—' }}</dd></div>
                    @if ($order->billing_identity_number)
                        <div><dt class="text-slate-500">T.C. / Vergi numarası</dt><dd>{{ $order->billing_identity_number }}</dd></div>
                    @endif
                    @if ($order->billing_tax_office)
                        <div><dt class="text-slate-500">Vergi dairesi</dt><dd>{{ $order->billing_tax_office }}</dd></div>
                    @endif
                    @if ($order->billing_address)
                        <div class="sm:col-span-2"><dt class="text-slate-500">Adres</dt><dd>{{ $order->billing_address }} {{ $order->billing_district }} / {{ $order->billing_province }}</dd></div>
                    @endif
                    <div class="sm:col-span-2"><dt class="text-slate-500">Hizmet</dt><dd>{{ $order->serviceDescription() }}</dd><dd class="text-xs text-slate-500">{{ $invoicePeriod }}</dd></div>
                    <div><dt class="text-slate-500">KDV dahil toplam</dt><dd class="font-bold">{{ number_format((float) $order->price, 2, ',', '.') }} ₺</dd></div>
                    <div><dt class="text-slate-500">Tahsil edilen</dt><dd class="font-medium text-emerald-700">{{ number_format((float) $order->payments->sum('amount'), 2, ',', '.') }} ₺</dd></div>
                </dl>
                <p class="mt-4 text-sm text-slate-600">Onayladığınızda otomatik bir fatura takip numarası (FTN) oluşturulur. Kalemleri takip ekranından Atmaca’ya kopyalayabilirsiniz. Gerçek fatura bilgileri, Atmaca’da fatura düzenlendikten sonra XML ile eklenecek.</p>
                @if ((int) old('invoice_order_id') === (int) $order->id)
                @error('invoicing')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
                @endif
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" onclick="this.closest('dialog').close()" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Vazgeç</button>
                    <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Takip kaydını oluştur</button>
                </div>
            </form>
        </dialog>
        @if ($errors->has('invoicing') && (int) old('invoice_order_id') === (int) $order->id)
            <script>document.getElementById(@js($modalId)).showModal();</script>
        @endif
