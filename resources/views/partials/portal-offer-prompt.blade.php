@if ($portalOffer ?? null)
    @php
        $unitNo = $portalOffer->account?->unit?->unit_no;
        $roleLabel = $portalOffer->account?->type === \App\Models\Account::TYPE_TENANT ? 'kiracısı' : 'kat maliki';
        $place = $unitNo ? $unitNo.' no.lu dairenin '.$roleLabel : $roleLabel;
    @endphp
    <div id="portal-offer-prompt" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/70 p-4" role="dialog" aria-modal="true" aria-labelledby="portal-offer-prompt-title">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <h2 id="portal-offer-prompt-title" class="text-lg font-bold text-slate-950">Apartman daveti</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                {{ $portalOffer->apartment?->name }} yöneticisi {{ $portalOffer->offerer?->name }} sizi {{ $place }} olarak davet etti.
            </p>
            <div class="mt-5 grid gap-2 sm:grid-cols-2">
                <form method="POST" action="{{ route('portal-offers.accept', $portalOffer) }}">
                    @csrf
                    <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Kabul et</button>
                </form>
                <form method="POST" action="{{ route('portal-offers.reject', $portalOffer) }}">
                    @csrf
                    <button type="submit" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reddet</button>
                </form>
            </div>
        </div>
    </div>
@endif
