@extends('layouts.app')

@section('title', 'XML Fatura Eşleştirme')

@section('content')
    <a href="{{ route('admin.order-invoices.xml.create') }}" class="text-sm font-semibold text-emerald-700">← Yeni XML yükle</a>
    <h1 class="mt-2 text-2xl font-bold text-slate-900">XML fatura eşleştirme</h1>
    @if ($errors->any())<p class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-700" role="alert">{{ $errors->first() }}</p>@endif
    <section class="mt-4 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold text-slate-900">XML’den okunan fatura</h2>
        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-slate-500">Fatura numarası</dt><dd class="font-medium">{{ $parsed['invoice_number'] }}</dd></div>
            <div><dt class="text-slate-500">Fatura tarihi</dt><dd>{{ \Carbon\Carbon::parse($parsed['invoice_date'])->format('d.m.Y') }}</dd></div>
            <div><dt class="text-slate-500">Alıcı VKN/TCKN</dt><dd>{{ $parsed['customer_identity'] }}</dd></div>
            <div><dt class="text-slate-500">Alıcı</dt><dd>{{ $parsed['customer_name'] ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Sipariş (SIP)</dt><dd>{{ implode(', ', $parsed['order_numbers']) ?: 'XML’de yok' }}</dd></div>
            <div><dt class="text-slate-500">Abonelik (ABN)</dt><dd>{{ implode(', ', $parsed['subscription_numbers']) ?: 'XML’de yok' }}</dd></div>
            <div><dt class="text-slate-500">KDV hariç / KDV</dt><dd>{{ number_format($parsed['net_amount'], 2, ',', '.') }} ₺ / {{ number_format($parsed['tax_amount'], 2, ',', '.') }} ₺</dd></div>
            <div><dt class="text-slate-500">KDV dahil toplam</dt><dd class="font-bold">{{ number_format($parsed['amount'], 2, ',', '.') }} ₺</dd></div>
        </dl>
    </section>
    <p class="mt-4 text-sm text-slate-600">{{ $parsed['order_numbers'] ? 'SIP numarası ve alıcı bilgisine göre bulunan takip kayıtları:' : 'Yalnızca ABN ve alıcı bilgisi eşleşti. Aynı aboneliğin farklı siparişleri olabilir; doğru takip kaydını seçin.' }} Fatura tarihi hizmet dönemine göre filtrelenmez.</p>
    @if ($candidates->isEmpty())
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">Eşleşen açık fatura takip kaydı bulunamadı.</p>
            <p class="mt-1">Sipariş için önce “Faturala” ile takip kaydı oluşturulduğunu, alıcı VKN/TCKN ve SIP/ABN bilgilerinin doğru olduğunu kontrol edin. Gerçek faturası eklenmiş kayıtlar listelenmez.</p>
        </div>
    @else
        <form method="POST" action="{{ route('admin.order-invoices.xml.confirm') }}" class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
            @csrf
            <input type="hidden" name="preview_token" value="{{ $token }}">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600"><tr>
                        <th class="px-4 py-3">Seç</th><th class="px-4 py-3">Takip / Sipariş</th><th class="px-4 py-3">Alıcı</th><th class="px-4 py-3 text-right">Sistem KDV hariç</th><th class="px-4 py-3 text-right">Sistem KDV</th><th class="px-4 py-3 text-right">Sistem toplam</th><th class="px-4 py-3">XML − Sistem farkı</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($candidates as $candidate)
                            @php($differences = $matching->differences($candidate, $parsed))
                            <tr>
                                <td class="px-4 py-3"><input type="radio" name="invoice_id" value="{{ $candidate->id }}" required aria-label="{{ $candidate->trackingNumber() }} seç" @checked(old('invoice_id') ? (int) old('invoice_id') === $candidate->id : $candidates->count() === 1)></td>
                                <td class="px-4 py-3"><a href="{{ route('admin.order-invoices.show', $candidate) }}" target="_blank" rel="noopener" class="font-semibold text-emerald-700">{{ $candidate->trackingNumber() }}</a><div class="mt-1 text-xs text-slate-500">{{ $candidate->snapshot['order_number'] }}</div></td>
                                <td class="px-4 py-3">{{ $candidate->snapshot['billing']['billing_legal_name'] ?? $candidate->snapshot['payer_name'] }}</td>
                                @foreach (['net_amount', 'tax_amount', 'amount'] as $key)
                                    <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ number_format($candidate->snapshot[$key], 2, ',', '.') }} ₺</td>
                                @endforeach
                                <td class="whitespace-nowrap px-4 py-3 text-xs">
                                    @foreach (['net_amount' => 'KDV hariç', 'tax_amount' => 'KDV', 'amount' => 'Toplam'] as $key => $label)
                                        <div class="{{ $differences[$key] === 0 ? 'text-emerald-700' : 'font-semibold text-amber-800' }}">{{ $label }}: {{ number_format($differences[$key] / 100, 2, ',', '.') }} ₺</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="space-y-3 border-t border-slate-200 p-4">
                <label for="difference_reason" class="block text-sm font-medium text-slate-700">Tutar farkı açıklaması <span class="font-normal text-slate-500">(fark varsa zorunlu)</span></label>
                <textarea id="difference_reason" name="difference_reason" maxlength="1000" rows="2" class="w-full rounded-xl border border-slate-300 p-3 text-sm">{{ old('difference_reason') }}</textarea>
                <p class="text-xs text-slate-500">Onaylandığında XML’in fatura numarası, tarihi ve tutarları seçilen takip kaydına eklenir. Siparişin kayıtlı tutarları korunur.</p>
                <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Seçilen kayıtla eşleştir</button>
            </div>
        </form>
    @endif
    <form method="POST" action="{{ route('admin.order-invoices.xml.cancel') }}" class="mt-4">
        @csrf
        <button type="submit" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Eşleştirmeyi iptal et</button>
    </form>
@endsection
