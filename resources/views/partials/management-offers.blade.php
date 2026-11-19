@if (($managementOffers ?? collect())->isNotEmpty() && ! request()->routeIs('management-offers.*'))
    <div class="mb-6 space-y-3">
        @foreach ($managementOffers as $offer)
            @if ($offer->status === \App\Models\ManagementOffer::STATUS_PENDING)
                <section class="rounded-2xl border border-red-300 bg-red-50 p-5 text-sm text-red-950" aria-labelledby="management-invite-{{ $offer->id }}">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-6 w-6 shrink-0 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l9 6 9-6M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                        </svg>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-red-700">Yöneticilik daveti</p>
                            <h2 id="management-invite-{{ $offer->id }}" class="mt-1 text-base font-bold">{{ $offer->apartment?->name }} için yönetici olarak davet edildiniz.</h2>
                            <p class="mt-2 text-red-800">Kabul ettiğinizde apartmanın tek yöneticisi olursunuz; eski yöneticilerin apartman erişimi kapanır. Reddederseniz mevcut daire üyeliğiniz devam eder.</p>
                            <a href="{{ route('management-offers.show', $offer) }}" class="mt-4 inline-flex rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Daveti incele</a>
                        </div>
                    </div>
                </section>
            @else
                <section class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                    <p class="font-semibold">{{ $offer->apartment?->name }} sizi yönetici yapmak istedi.</p>
                    <p class="mt-1 text-amber-800">{{ \App\Support\UserApartmentQuota::CONTACT_MESSAGE }}</p>
                </section>
            @endif
        @endforeach
    </div>
@endif
