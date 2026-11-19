@extends('layouts.app')

@section('title', 'Fatura Takibi')

@section('content')
    @php
        $snapshot = $invoice->snapshot;
        $billing = $snapshot['billing'];
        $billingRows = array_filter([
            'Alıcı' => $billing['billing_legal_name'] ?? $snapshot['payer_name'],
            'T.C. / Vergi numarası' => $billing['billing_identity_number'] ?? null,
            'Vergi dairesi' => $billing['billing_tax_office'] ?? null,
            'Adres' => $billing['billing_address'] ?? null,
            'İlçe / İl' => implode(' / ', array_filter([$billing['billing_district'] ?? null, $billing['billing_province'] ?? null])),
            'E-posta' => $billing['billing_email'] ?? null,
        ], fn ($value) => filled($value));
    @endphp
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.orders.show', $invoice->user_subscription_id) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">← Siparişe dön</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Fatura takibi · {{ $invoice->trackingNumber() }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $snapshot['order_number'] }}</p>
        </div>
        <button type="button" onclick="document.getElementById('atmaca-modal').showModal()" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Atmaca’ya kopyala</button>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>@endif
    <section class="mb-4 rounded-xl border border-slate-200 bg-white p-5">
        <dl class="grid gap-3 text-sm sm:grid-cols-3">
            <div><dt class="text-slate-500">Fatura takip no</dt><dd class="font-semibold text-slate-900">{{ $invoice->trackingNumber() }}</dd></div>
            <div><dt class="text-slate-500">Gerçek fatura numarası</dt><dd class="font-medium text-slate-900">{{ $invoice->invoice_number ?: 'XML bekleniyor' }}</dd></div>
            <div><dt class="text-slate-500">Gerçek fatura tarihi</dt><dd class="font-medium text-slate-900">{{ $invoice->invoice_date?->format('d.m.Y') ?? '—' }}</dd></div>
        </dl>
        @unless ($invoice->invoice_number)
            <p class="mt-3 text-xs text-slate-500">Bu bir fatura takip kaydıdır. Gerçek faturayı Atmaca’da düzenledikten sonra <a href="{{ route('admin.order-invoices.xml.create') }}" class="font-semibold text-emerald-700 underline">Satış XML gir</a> ile ekleyebilirsiniz.</p>
        @endunless
    </section>
    @if ($invoice->xml_details)
        <section class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold text-emerald-900">XML fatura bilgileri</h2>
                <a href="{{ route('admin.order-invoices.xml.download', $invoice) }}" class="text-sm font-semibold text-emerald-800 underline">XML’i indir</a>
            </div>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                @foreach (['net_amount' => 'KDV hariç', 'tax_amount' => 'KDV', 'amount' => 'KDV dahil toplam'] as $key => $label)
                    <div><dt class="text-emerald-900">{{ $label }}</dt><dd class="font-semibold">{{ number_format($invoice->xml_details[$key], 2, ',', '.') }} ₺</dd><dd class="text-xs text-slate-600">Sistemden fark: {{ number_format($invoice->xml_details[$key] - $snapshot[$key], 2, ',', '.') }} ₺</dd></div>
                @endforeach
            </dl>
            @if ($invoice->xml_difference_reason)<p class="mt-3 text-sm text-slate-700">Fark açıklaması: {{ $invoice->xml_difference_reason }}</p>@endif
            <p class="mt-3 text-xs text-slate-500">XML eklenme tarihi: {{ $invoice->xml_imported_at?->format('d.m.Y H:i') }}</p>
        </section>
    @endif
    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold text-slate-900">Fatura alıcısı</h2>
        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($billingRows as $label => $value)
                <div><dt class="text-slate-500">{{ $label }}</dt><dd class="break-words font-medium text-slate-900">{{ $value }}</dd></div>
            @endforeach
        </dl>
    </section>
    <section class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500"><tr>
                    <th class="px-4 py-3">Hizmet</th><th class="px-4 py-3 text-right">Adet</th><th class="px-4 py-3 text-right">KDV hariç</th><th class="px-4 py-3 text-right">KDV</th><th class="px-4 py-3 text-right">KDV dahil</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($snapshot['lines'] as $line)
                        <tr>
                            <td class="min-w-64 px-4 py-3 text-slate-900">{{ $line['description'] }}</td>
                            <td class="px-4 py-3 text-right">1</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ number_format($line['net_amount'], 2, ',', '.') }} ₺</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ number_format($line['tax_amount'], 2, ',', '.') }} ₺ <span class="text-xs text-slate-500">(%{{ $line['tax_rate'] }})</span></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums">{{ number_format($line['amount'], 2, ',', '.') }} ₺</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <dl class="flex flex-wrap justify-end gap-x-6 gap-y-2 border-t border-slate-200 bg-slate-50 p-4 text-sm">
            <div><dt class="text-slate-500">KDV hariç toplam</dt><dd class="font-medium tabular-nums">{{ number_format($snapshot['net_amount'], 2, ',', '.') }} ₺</dd></div>
            <div><dt class="text-slate-500">Toplam KDV</dt><dd class="font-medium tabular-nums">{{ number_format($snapshot['tax_amount'], 2, ',', '.') }} ₺</dd></div>
            <div><dt class="text-slate-500">Genel toplam</dt><dd class="font-bold tabular-nums">{{ number_format($snapshot['amount'], 2, ',', '.') }} ₺</dd></div>
        </dl>
    </section>
    <p class="mt-3 text-xs text-slate-500">Kaydeden: {{ $invoice->creator?->name ?: '—' }} · {{ $invoice->created_at->format('d.m.Y H:i') }}</p>

    <dialog id="atmaca-modal" aria-labelledby="atmaca-title" class="m-auto max-h-[90vh] w-[calc(100%-2rem)] max-w-3xl overflow-y-auto rounded-2xl bg-white p-5 shadow-xl backdrop:bg-black/50">
        <div class="flex items-center justify-between gap-3">
            <h2 id="atmaca-title" class="text-lg font-bold text-slate-900">Atmaca’ya kopyala</h2>
            <button type="button" onclick="this.closest('dialog').close()" aria-label="Kapat" class="rounded-lg px-3 py-1 text-slate-500 hover:bg-slate-100">✕</button>
        </div>
        <p class="mt-3 text-sm text-slate-600">Satırları Excel’e, ardından Atmaca’da “Excel’den yapıştır” alanına yapıştırabilirsiniz.</p>
        <p class="mt-2 text-xs text-slate-500">Sütun sırası: Açıklama / Adet / TL Fiyat (KDV hariç) / KDV (%) / İndirim (%). Her abonelik hizmeti 1 adet olarak aktarılır.</p>
        <textarea id="atmaca-text" readonly aria-label="Atmaca fatura satırları" onfocus="this.select()" class="mt-4 h-48 w-full rounded-xl border border-slate-300 p-3 font-mono text-xs">{{ $invoice->atmacaText() }}</textarea>
        <div class="mt-3 flex items-center justify-end gap-3">
            <span id="atmaca-feedback" role="status" class="text-sm text-slate-600"></span>
            <button type="button" onclick="copyAtmaca()" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Kopyala</button>
        </div>
    </dialog>
    <script>
        async function copyAtmaca() {
            const source = document.getElementById('atmaca-text');
            const feedback = document.getElementById('atmaca-feedback');
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(source.value);
                } else {
                    source.focus();
                    source.select();
                    if (!document.execCommand('copy')) throw new Error('copy');
                }
                feedback.textContent = 'Kopyalandı.';
            } catch (error) {
                source.focus();
                source.select();
                feedback.textContent = 'Otomatik kopyalanamadı. Seçili metni Ctrl+C ile kopyalayın.';
            }
        }
    </script>
@endsection
