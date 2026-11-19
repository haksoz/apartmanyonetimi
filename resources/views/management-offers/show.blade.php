@extends('layouts.app')

@section('title', 'Yöneticilik kabulü')

@section('content')
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('subscriber.dashboard') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:text-emerald-900">← Abone paneline dön</a>
        <section class="mt-4 overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm">
            <div class="border-b border-emerald-100 bg-gradient-to-br from-emerald-50 to-emerald-100 p-5 sm:p-7">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-700 text-white">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V3h14v18M9 7h1m4 0h1M9 11h1m4 0h1M9 15h1m4 0h1M10 21v-3h4v3"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Yöneticilik daveti</p>
                        <h1 class="mt-1 text-2xl font-bold text-emerald-950">Yöneticilik kabulü</h1>
                        <p class="mt-2 text-sm leading-relaxed text-emerald-800">Bu apartmanın yönetimine katılmanız için davet edildiniz. Devam etmeden önce davet bilgilerini inceleyin.</p>
                    </div>
                </div>
                <div class="mt-5 rounded-xl border border-emerald-100 bg-white/80 p-4">
                    <h2 class="break-words text-lg font-bold text-emerald-950">{{ $offer->apartment->name }}</h2>
                    <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                        <div><dt class="text-emerald-700">Apartman kodu</dt><dd class="mt-1 font-mono font-semibold text-slate-900">{{ $offer->apartment->code }}</dd></div>
                        <div><dt class="text-emerald-700">Davet eden</dt><dd class="mt-1 font-medium text-slate-900">{{ $offer->offerer?->name ?: '—' }}</dd></div>
                        <div><dt class="text-emerald-700">Son kabul tarihi</dt><dd class="mt-1 font-medium text-slate-900">{{ $offer->expires_at?->format('d.m.Y') ?? '—' }}</dd></div>
                    </dl>
                </div>
            </div>
            <div class="p-5 sm:p-7">
                <h2 class="font-semibold text-slate-900">Kabul ettiğinizde ne olur?</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">Bu apartmanın tek yöneticisi olursunuz. Mevcut yöneticilerin bu apartmana erişimi kapanır. Apartman, abonelik numarası ve mevcut ücretli hizmet süresi korunur. Tanımlı yönetim limitiniz varsa bu apartman da limite dahil edilir.</p>
                <form method="POST" action="{{ route('management-offers.accept', $offer) }}" class="mt-5 space-y-5">
                    @csrf
                    @if ($needsResidentData)
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
                            <h2 class="mb-3 text-sm font-semibold text-emerald-950">Devam etmek için onaylarınız</h2>
                            @include('partials.accept-resident-data', ['consentTheme' => 'emerald'])
                        </div>
                    @endif
                    @error('accept')
                        <p class="rounded-xl bg-red-50 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="w-full rounded-xl bg-emerald-700 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">Yöneticiliği kabul et</button>
                </form>
                <div class="mt-5 border-t border-slate-100 pt-4">
                    <p class="text-center text-xs leading-relaxed text-slate-500">Yönetici olmak istemiyorsanız daveti reddedebilirsiniz. Mevcut daire üyeliğiniz devam eder.</p>
                    <form method="POST" action="{{ route('management-offers.reject', $offer) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="w-full rounded-xl border border-emerald-200 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">Reddet, üye olarak kal</button>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
