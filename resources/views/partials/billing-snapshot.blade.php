<section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-semibold text-slate-900">Sipariş Anındaki Fatura Bilgileri</h2>
    @if ($subscription->hasBillingSnapshot())
        @php
            $billingRows = array_filter([
                'Profil' => $subscription->billing_label,
                'Fatura türü' => \App\Models\BillingProfile::partyLabel($subscription->billing_party_type),
                'Fatura alıcısı / unvan' => $subscription->billing_legal_name,
                'T.C. kimlik numarası veya vergi numarası' => $subscription->billing_identity_number,
                'Vergi dairesi' => $subscription->billing_tax_office,
                'E-posta' => $subscription->billing_email,
                'Telefon' => $subscription->billing_phone,
                'Ülke' => $subscription->billing_country,
                'İl' => $subscription->billing_province,
                'İlçe' => $subscription->billing_district,
                'Adres' => $subscription->billing_address,
                'Posta kodu' => $subscription->billing_postal_code,
            ], fn ($value) => filled($value));
        @endphp
        <p class="mt-1 text-sm text-slate-500">Sipariş oluşturulurken kaydedildi. Bu ekrandan değiştirilemez.</p>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            @foreach ($billingRows as $label => $value)
                <div>
                    <dt class="text-slate-500">{{ $label }}</dt>
                    <dd class="font-medium text-slate-900">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    @else
        <p class="mt-3 text-sm text-slate-600">Bu sipariş için fatura bilgisi kayıtlı değil.</p>
    @endif
</section>
