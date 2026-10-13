@extends('layouts.landing')

@section('title', 'AidatCep — Apartman Yönetimi')

@section('content')

@include('microsite.partials.header')

<section class="pt-28 pb-16 px-4 sm:px-6 bg-slate-50">
    <div class="max-w-6xl mx-auto grid lg:grid-cols-2 gap-12 items-center">
        <div>
            <p class="text-sm font-semibold tracking-wide uppercase" style="color:#336633">Apartman yönetimi yazılımı</p>
            <h1 class="mt-3 text-4xl sm:text-5xl font-extrabold text-slate-950 leading-tight">
                Apartmanınızın hesabını tek yerde tutun.
            </h1>
            <p class="mt-5 text-lg text-slate-600 leading-relaxed">
                Aidat, tahsilat, gider, kasa ve hesap takibini apartman yöneticisi olarak tek ekrandan yürütün.
            </p>
            <div class="mt-8 flex flex-col sm:flex-row gap-3">
                <a href="{{ route('register') }}" class="inline-flex justify-center rounded-xl bg-emerald-700 px-6 py-3.5 text-base font-bold text-white hover:bg-emerald-800">Ücretsiz Başla</a>
                <a href="{{ route('login') }}" class="inline-flex justify-center rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-base font-semibold text-slate-700 hover:bg-slate-50">Giriş Yap</a>
            </div>
            <p class="mt-5 text-sm text-slate-600 leading-relaxed">
                Bir apartmanınızla ücretsiz başlayın. Birden fazla apartman için
                <a href="mailto:info@aidatcep.com" class="font-semibold text-emerald-800 underline">bizimle iletişime geçin</a>.
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden" aria-hidden="true">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
                <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                <span class="ml-2 text-xs font-medium text-slate-500">AidatCep paneli</span>
            </div>
            <div class="grid grid-cols-[7.5rem_1fr] min-h-72">
                <div class="border-r border-slate-100 bg-slate-50 p-3 space-y-2 text-xs font-semibold text-slate-600">
                    <div class="rounded-lg px-2 py-2 text-white" style="background:#336633">Aidatlar</div>
                    <div class="rounded-lg px-2 py-2">Tahsilatlar</div>
                    <div class="rounded-lg px-2 py-2">Giderler</div>
                    <div class="rounded-lg px-2 py-2">Kasa</div>
                    <div class="rounded-lg px-2 py-2">Cari hesap</div>
                </div>
                <div class="p-4">
                    <p class="text-sm font-bold text-slate-900">Aidat takibi</p>
                    <p class="mt-1 text-xs text-slate-500">Dairelerin borç durumu</p>
                    <div class="mt-4 space-y-2">
                        @foreach (['Daire 1', 'Daire 2', 'Daire 3', 'Daire 4'] as $unit)
                            <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 text-xs">
                                <span class="font-medium text-slate-800">{{ $unit }}</span>
                                <span class="text-slate-500">Aidat kaydı</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-16 px-4 sm:px-6 bg-white">
    <div class="max-w-6xl mx-auto">
        <h2 class="text-3xl font-extrabold text-slate-950">Ücretsiz temel kullanım</h2>
        <p class="mt-3 max-w-2xl text-slate-600">Hesap oluşturmak ücretsizdir. Kayıt sırasında abonelik veya sipariş açılmaz. 1–100 daireli apartmanda temel kullanım süresizdir.</p>
        <div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ([
                ['Aidat takibi', 'Aidat kayıtlarını oluşturun ve dairelerin borç durumunu izleyin.'],
                ['Tahsilat takibi', 'Gelen ödemeleri kaydedin ve aidatlara bağlayın.'],
                ['Gider ve kasa', 'Apartman giderlerini ve kasa hareketlerini kaydedin.'],
                ['Cari hesap', 'Hesap hareketlerini görüntüleyin ve ekstreye bakın.'],
                ['Daire Sakini', 'Daire hesaplarını açın ve kullanıcıları bağlayın.'],
                ['Temel raporlar', 'Gelir-gider, borç, alacak ve ekstre ekranlarını görüntüleyin.'],
            ] as [$title, $text])
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <h3 class="font-bold text-slate-900">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $text }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="py-16 px-4 sm:px-6 bg-slate-50">
    <div class="max-w-6xl mx-auto">
        <h2 class="text-3xl font-extrabold text-slate-950">Ücretli abonelik</h2>
        <p class="mt-3 max-w-2xl text-slate-600">Bu araçlar, ilgili apartmanın ücretli aboneliği onaylandığında açılır. Tarife daire sayısına göredir.</p>
        <div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ([
                ['Otomatik aidat planlama', 'Aidat planı tanımlayın ve dönemsel tahakkuku plandan üretin.'],
                ['Gider dağıtımı', 'Kaydettiğiniz gideri dairelere borç olarak dağıtın.'],
                ['Metrekare ve pay', 'Dağıtımda metrekare ve pay çarpanını kullanın.'],
                ['Belge yükleme', 'Gider kaydına belge ekleyin.'],
                ['PDF ve Excel', 'Listeleri ve raporları PDF veya Excel olarak indirin.'],
                ['Gelişmiş raporlar', 'Tahsilat matrisi, gecikme, yıllık faaliyet, bütçe ve aylık pano.'],
            ] as [$title, $text])
                <article class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h3 class="font-bold text-slate-900">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $text }}</p>
                </article>
            @endforeach
        </div>
        <a href="{{ route('pricing') }}" class="inline-flex mt-8 text-sm font-semibold text-emerald-800 underline">Fiyatlandırmayı inceleyin</a>
    </div>
</section>

<section class="py-16 px-4 sm:px-6 bg-white">
    <div class="max-w-6xl mx-auto">
        <h2 class="text-3xl font-extrabold text-slate-950">Nasıl çalışır?</h2>
        <ol class="mt-8 grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach ([
                ['Hesabınızı oluşturun', 'Ücretsiz hesabınızı açın. Kayıtta sipariş oluşmaz.'],
                ['Apartmanınızı ekleyin', 'Apartman bilgilerinizi ve dairelerinizi tanımlayın.'],
                ['Temel kullanımı başlatın', 'Aidat, tahsilat, gider ve kasayı kaydetmeye başlayın.'],
                ['Gelişmiş özelliklere geçin', 'İhtiyaç olursa o apartman için ücretli abonelik açın.'],
            ] as $index => [$title, $text])
                <li>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl text-sm font-bold text-white" style="background:#336633">{{ $index + 1 }}</div>
                    <h3 class="mt-4 font-bold text-slate-900">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

<section class="py-16 px-4 sm:px-6 bg-slate-950 text-white">
    <div class="max-w-6xl mx-auto grid gap-8 md:grid-cols-2">
        <div>
            <h2 class="text-3xl font-extrabold">Ücretsiz hesabınızı oluşturun</h2>
            <p class="mt-3 text-slate-300">Apartmanınızı ekleyin ve temel özellikleri kullanmaya başlayın.</p>
            <div class="mt-6 flex flex-col sm:flex-row gap-3">
                <a href="{{ route('register') }}" class="inline-flex justify-center rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white hover:bg-emerald-500">Ücretsiz Başla</a>
                <a href="{{ route('faq') }}" class="inline-flex justify-center rounded-xl border border-slate-600 px-6 py-3 text-sm font-semibold text-slate-100 hover:bg-slate-900">Sık Sorulan Sorular</a>
            </div>
        </div>
        <div class="text-sm text-slate-300 space-y-2">
            <p class="font-semibold text-white">Kapital Online</p>
            <p>AidatCep, Kapital Online markasıdır.</p>
            <a href="tel:+902163774000" class="block text-white hover:underline">+90 216 377 4000</a>
            <a href="mailto:info@aidatcep.com" class="block text-white hover:underline">info@aidatcep.com</a>
            <a href="https://ko.com.tr/" target="_blank" rel="noopener noreferrer" class="block text-white hover:underline">ko.com.tr</a>
        </div>
    </div>
</section>

@include('microsite.partials.footer')

@endsection
