@if ($subscription->hasBillingSnapshot())
    @php
        $billingRows = array_filter([
            'Profil' => $subscription->billing_label,
            'Tür' => \App\Models\BillingProfile::partyLabel($subscription->billing_party_type),
            'Ad / unvan' => $subscription->billing_legal_name,
            'Kimlik veya vergi numarası' => $subscription->billing_identity_number,
            'Vergi dairesi' => $subscription->billing_tax_office,
            'E-posta' => $subscription->billing_email,
            'Telefon' => $subscription->billing_phone,
            'Adres' => collect([$subscription->billing_address, $subscription->billing_district, $subscription->billing_province, $subscription->billing_country, $subscription->billing_postal_code])->filter()->implode(' · ') ?: null,
        ], fn ($value) => filled($value));
    @endphp
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Fatura bilgileri</h2>
        <p class="mt-1 text-sm text-slate-500">Sipariş oluşturulurken kaydedildi.</p>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            @foreach ($billingRows as $label => $value)
                <div>
                    <dt class="text-slate-500">{{ $label }}</dt>
                    <dd class="font-medium text-slate-900">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
@endif
