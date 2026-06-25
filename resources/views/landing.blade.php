@extends('layouts.landing')

@section('content')

{{-- ===== NAVBAR ===== --}}
<header class="fixed top-0 inset-x-0 z-50 bg-white/90 backdrop-blur border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <a href="{{ route('landing') }}" class="flex items-center gap-2">
            <img src="{{ asset('images/logo.png') }}" alt="AidatCep" class="h-8 w-auto">
            <span class="text-lg font-bold">
                <span style="color:#336633">Aidat</span><span class="text-slate-400">Cep</span>
            </span>
        </a>
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}"
               class="text-sm font-semibold text-slate-600 hover:text-slate-900 transition-colors">
                Giriş Yap
            </a>
            <a href="{{ route('register') }}"
               class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors">
                Ücretsiz Başla
            </a>
        </div>
    </div>
</header>

{{-- ===== HERO ===== --}}
<section class="pt-32 pb-24 px-4 sm:px-6 bg-gradient-to-b from-slate-50 to-white">
    <div class="max-w-4xl mx-auto text-center">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 mb-6">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
            Apartman yönetimi artık çok daha kolay
        </span>
        <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold text-slate-950 leading-tight mb-6">
            Apartman Yönetimi,<br>
            <span style="color:#336633">Cebinizde.</span>
        </h1>
        <p class="text-lg sm:text-xl text-slate-500 max-w-2xl mx-auto mb-10 leading-relaxed">
            Aidat takibinden gider yönetimine, tahsilat işlemlerinden sakin raporlarına kadar her şey tek platformda.
            Kâğıt yok, karmaşa yok.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('register') }}"
               class="rounded-2xl bg-emerald-600 px-8 py-3.5 text-base font-bold text-white hover:bg-emerald-700 transition-colors shadow-lg shadow-emerald-100">
                Hemen Ücretsiz Başla
            </a>
            <a href="{{ route('login') }}"
               class="rounded-2xl border-2 border-slate-200 px-8 py-3.5 text-base font-semibold text-slate-700 hover:border-slate-300 hover:bg-slate-50 transition-colors">
                Giriş Yap
            </a>
        </div>
    </div>
</section>

{{-- ===== ÖZELLİKLER ===== --}}
<section class="py-20 px-4 sm:px-6 bg-white">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-14">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-950 mb-3">İhtiyacınız olan her şey</h2>
            <p class="text-slate-500 text-lg max-w-xl mx-auto">Apartman yönetimi için gerekli tüm araçlar, tek bir uygulamada.</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">

            {{-- Aidat Takibi --}}
            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-6 hover:shadow-md transition-shadow">
                <div class="w-11 h-11 rounded-xl bg-emerald-100 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-1.5">Aidat Takibi</h3>
                <p class="text-sm text-slate-500 leading-relaxed">Tekil veya toplu aidat tahakkuku oluşturun. Aidat planları ile otomatik borçlandırma yapın.</p>
            </div>

            {{-- Tahsilat Yönetimi --}}
            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-6 hover:shadow-md transition-shadow">
                <div class="w-11 h-11 rounded-xl bg-blue-100 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-1.5">Tahsilat Yönetimi</h3>
                <p class="text-sm text-slate-500 leading-relaxed">Gelen ödemeleri kaydedin, aidatlara tahsis edin. Dağıtılmamış ödemeleri takip edin.</p>
            </div>

            {{-- Gider Takibi --}}
            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-6 hover:shadow-md transition-shadow">
                <div class="w-11 h-11 rounded-xl bg-rose-100 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-1.5">Gider Takibi</h3>
                <p class="text-sm text-slate-500 leading-relaxed">Apartman giderlerini kategorilere göre kaydedin. Tedarikçi ödemelerini takip edin.</p>
            </div>

            {{-- Hesap Ekstresi --}}
            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-6 hover:shadow-md transition-shadow">
                <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-1.5">Hesap Ekstresi</h3>
                <p class="text-sm text-slate-500 leading-relaxed">Her sakin için tarih aralıklı cari ekstre görüntüleyin. Borç/alacak hareketlerini takip edin.</p>
            </div>

            {{-- Çoklu Apartman --}}
            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-6 hover:shadow-md transition-shadow">
                <div class="w-11 h-11 rounded-xl bg-amber-100 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-1.5">Çoklu Apartman</h3>
                <p class="text-sm text-slate-500 leading-relaxed">Birden fazla apartmanı tek hesaptan yönetin. Kolayca apartmanlar arasında geçiş yapın.</p>
            </div>

            {{-- Kullanıcı Portalı --}}
            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-6 hover:shadow-md transition-shadow">
                <div class="w-11 h-11 rounded-xl bg-teal-100 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-1.5">Kullanıcı Portalı</h3>
                <p class="text-sm text-slate-500 leading-relaxed">Kullanıcılar kendi borç durumunu ve ödeme geçmişini görebilir. Şeffaf yönetim, memnun kullanıcılar.</p>
            </div>

        </div>
    </div>
</section>

{{-- ===== NASIL ÇALIŞIR ===== --}}
<section class="py-20 px-4 sm:px-6 bg-slate-50">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-14">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-950 mb-3">3 Adımda Başlayın</h2>
            <p class="text-slate-500 text-lg">Dakikalar içinde apartmanınızı sisteme ekleyin.</p>
        </div>

        <div class="grid sm:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-14 h-14 rounded-2xl bg-emerald-600 text-white text-2xl font-black flex items-center justify-center mx-auto mb-4 shadow-lg shadow-emerald-100">1</div>
                <h3 class="font-bold text-slate-900 mb-2">Hesap Oluşturun</h3>
                <p class="text-sm text-slate-500">E-posta adresinizle ücretsiz kaydolun, dakikalar içinde hazır olun.</p>
            </div>
            <div class="text-center">
                <div class="w-14 h-14 rounded-2xl bg-emerald-600 text-white text-2xl font-black flex items-center justify-center mx-auto mb-4 shadow-lg shadow-emerald-100">2</div>
                <h3 class="font-bold text-slate-900 mb-2">Apartmanınızı Ekleyin</h3>
                <p class="text-sm text-slate-500">Apartman bilgilerini, daireleri ve sakinleri sisteme girin.</p>
            </div>
            <div class="text-center">
                <div class="w-14 h-14 rounded-2xl bg-emerald-600 text-white text-2xl font-black flex items-center justify-center mx-auto mb-4 shadow-lg shadow-emerald-100">3</div>
                <h3 class="font-bold text-slate-900 mb-2">Yönetmeye Başlayın</h3>
                <p class="text-sm text-slate-500">Aidat takibi, tahsilat ve raporlar hemen kullanıma hazır.</p>
            </div>
        </div>
    </div>
</section>

{{-- ===== FİYATLANDIRMA ===== --}}
<section class="py-20 px-4 sm:px-6 bg-white">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-950 mb-3">Basit ve Şeffaf Fiyat</h2>
            <p class="text-slate-500 text-lg">İhtiyacınıza uygun paketi seçin. Sürpriz yok.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-6 items-start">
            @foreach($packages as $package)
                @php
                    $isRecommended = $package->sort_order === 2;
                    $borderClass = $isRecommended ? 'border-emerald-500 shadow-xl shadow-emerald-50' : 'border-slate-200';
                    $buttonClass = $isRecommended ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'border-2 border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-slate-50';
                    $priceDisplay = $package->monthly_price > 0 ? number_format($package->monthly_price, 0, ',', '.') . ' TL' : 'Ücretsiz';
                    $priceSuffix = $package->monthly_price > 0 ? ' / ay' : '';
                @endphp

                <div class="rounded-3xl border-2 {{ $borderClass }} bg-white p-8 text-center relative">
                    @if($isRecommended)
                        <span class="absolute -top-4 left-1/2 -translate-x-1/2 bg-emerald-600 text-white text-xs font-bold px-4 py-1.5 rounded-full">Tavsiye Edilen</span>
                    @endif

                    <h3 class="text-xl font-bold text-slate-950 mb-2">{{ $package->name }}</h3>
                    <p class="text-slate-500 text-sm mb-6">{{ $package->description }}</p>

                    <div class="mb-8">
                        <span class="text-5xl font-extrabold text-slate-950">{{ $priceDisplay }}</span>
                        <span class="text-slate-400 text-base">{{ $priceSuffix }}</span>
                    </div>

                    <ul class="text-sm text-slate-600 space-y-3 mb-8 text-left">
                        @foreach($package->features as $feature)
                            <li class="flex items-center gap-2.5 {{ !$feature->is_enabled ? 'opacity-40' : '' }}">
                                @if($feature->is_enabled)
                                    <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                @else
                                    <svg class="w-5 h-5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                @endif
                                {{ $feature->feature_key }}
                            </li>
                        @endforeach
                    </ul>

                    <a href="{{ route('register', ['package' => $package->slug]) }}"
                       class="block w-full rounded-2xl {{ $buttonClass }} py-3.5 text-base font-bold transition-colors">
                        Ücretsiz Başla
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===== CTA BANT ===== --}}
<section class="py-20 px-4 sm:px-6 bg-slate-950">
    <div class="max-w-3xl mx-auto text-center">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-white mb-4">
            Apartmanınızı bugün dijitale taşıyın
        </h2>
        <p class="text-slate-400 text-lg mb-8">
            Dakikalar içinde kurulum, anında kullanım.
        </p>
        <a href="{{ route('register') }}"
           class="inline-block rounded-2xl bg-emerald-500 px-10 py-4 text-base font-bold text-white hover:bg-emerald-400 transition-colors">
            Ücretsiz Hesap Oluştur
        </a>
    </div>
</section>

{{-- ===== FOOTER ===== --}}
<footer class="py-10 px-4 sm:px-6 bg-slate-950 border-t border-slate-800">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <a href="https://ko.com.tr/" target="_blank" rel="noopener noreferrer">
                <img src="https://atmaca.ko.com.tr/images/uploads/firms/ko.png" alt="KO" class="h-7 w-auto opacity-80">
            </a>
        </div>
        <div class="flex items-center gap-6 text-sm text-slate-500">
            <a href="tel:+902163774000" class="hover:text-slate-300 transition-colors">+90 216 377 4000</a>
            <a href="mailto:info@aidatcep.com" class="hover:text-slate-300 transition-colors">info@aidatcep.com</a>
        </div>
        <p class="text-xs text-slate-600">&copy; {{ date('Y') }} AidatCep — Kapital Online markasıdır.</p>
    </div>
</footer>

@endsection
