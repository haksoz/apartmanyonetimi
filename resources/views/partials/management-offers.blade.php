@if (($managementOffers ?? collect())->isNotEmpty() && ! request()->routeIs('management-offers.*'))
    <div class="mb-6 space-y-3">
        @foreach ($managementOffers as $offer)
            <section class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                @if ($offer->status === \App\Models\ManagementOffer::STATUS_PENDING)
                    <p class="font-semibold">{{ $offer->apartment?->name }} için yöneticilik kabulü bekliyor.</p>
                    <p class="mt-1 text-amber-800">Kabul ederseniz bu apartman yönetim hakkınıza yazılır. Reddederseniz daire üyeliğiniz durur.</p>
                    <a href="{{ route('management-offers.show', $offer) }}" class="mt-3 inline-flex rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Kabul penceresini aç</a>
                @else
                    <p class="font-semibold">{{ $offer->apartment?->name }} sizi yönetici yapmak istedi.</p>
                    <p class="mt-1 text-amber-800">{{ \App\Support\UserApartmentQuota::CONTACT_MESSAGE }}</p>
                @endif
            </section>
        @endforeach
    </div>
@endif

@if (session('management_offer_url'))
    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900">
        <p class="font-semibold">Yöneticilik kabul bağlantısı</p>
        <p class="mt-1">Bu bağlantıyı kişiye iletin. Ayrı bir e-posta gönderilmez.</p>
        <input type="text" readonly value="{{ session('management_offer_url') }}" class="mt-3 w-full rounded-xl border border-emerald-200 bg-white px-3 py-2 text-sm text-slate-800" onclick="this.select()">
    </div>
@endif
