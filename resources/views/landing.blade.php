@extends('layouts.landing')

@section('title', 'AidatCep — Apartman Yönetimi')

@section('content')

@include('microsite.partials.header')

<section class="pt-32 pb-20 px-4 sm:px-6 bg-gradient-to-b from-slate-50 to-white">
    <div class="max-w-3xl mx-auto text-center">
        <h1 class="text-4xl sm:text-5xl font-extrabold text-slate-950 leading-tight mb-6">
            Apartman yönetimini<br>
            <span style="color:#336633">kolaylaştırın.</span>
        </h1>
        <p class="text-lg text-slate-500 mb-10 leading-relaxed">
            AidatCep, apartman yöneticileri için aidat, tahsilat, gider, kasa, cari hesap ve Daire Sakini yönetimini tek bir yerde sunar.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('register') }}"
               class="rounded-2xl bg-emerald-600 px-8 py-3.5 text-base font-bold text-white hover:bg-emerald-700 transition-colors">
                Ücretsiz Başla
            </a>
            <a href="{{ route('login') }}"
               class="rounded-2xl border-2 border-slate-200 px-8 py-3.5 text-base font-semibold text-slate-700 hover:border-slate-300 hover:bg-slate-50 transition-colors">
                Giriş Yap
            </a>
        </div>
        <a href="{{ route('pricing') }}" class="inline-block mt-6 text-sm font-semibold text-slate-600 hover:text-slate-900">
            Fiyatlandırmayı İncele
        </a>
    </div>
</section>

<section class="py-20 px-4 sm:px-6 bg-white">
    <div class="max-w-3xl mx-auto">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-950 mb-4">AidatCep nedir?</h2>
        <p class="text-slate-600 leading-relaxed">
            AidatCep, apartman yöneticilerinin aidat, tahsilat, gider, kasa, cari hesap ve Daire Sakini yönetimini tek bir platformdan yürütmesini sağlayan bir yönetim uygulamasıdır.
        </p>
        <p class="mt-4 text-slate-600 leading-relaxed">
            Hesap oluşturmak ücretsizdir. Kayıt sırasında abonelik veya sipariş açılmaz.
        </p>
    </div>
</section>

<section class="py-20 px-4 sm:px-6 bg-slate-50">
    <div class="max-w-6xl mx-auto">
        <div class="max-w-3xl mb-12">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-950 mb-3">Özellikler</h2>
            <p class="text-slate-500 text-lg">Temel yönetim ücretsizdir. Gelişmiş araçlar, apartmanın ücretli aboneliğiyle açılır.</p>
        </div>

        <h3 class="text-lg font-bold text-slate-950 mb-5">Temel kullanım</h3>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="rounded-2xl border border-slate-100 bg-white p-6">
                <h4 class="text-lg font-bold text-slate-900 mb-1.5">Aidat takibi</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Aidat kayıtlarını oluşturun ve dairelerin borç durumunu takip edin.</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-6">
                <h4 class="text-lg font-bold text-slate-900 mb-1.5">Tahsilat takibi</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Gelen ödemeleri kaydedin ve aidatlara bağlayın.</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-6">
                <h4 class="text-lg font-bold text-slate-900 mb-1.5">Gider ve kasa takibi</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Apartman giderlerini ve kasa hareketlerini kaydedin.</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-6">
                <h4 class="text-lg font-bold text-slate-900 mb-1.5">Cari hesap / ekstre</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Hesap hareketlerini görüntüleyin ve ekstreye bakın.</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-6">
                <h4 class="text-lg font-bold text-slate-900 mb-1.5">Daire Sakini ve kullanıcı yönetimi</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Daire hesaplarını açın ve kullanıcılara bağlayın.</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-6">
                <h4 class="text-lg font-bold text-slate-900 mb-1.5">Birden fazla apartman</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Tek hesaptan birden fazla apartman açın ve aralarında geçiş yapın.</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-6">
                <h4 class="text-lg font-bold text-slate-900 mb-1.5">Temel raporlar</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Gelir-gider, borç, alacak ve ekstre ekranlarını görüntüleyin.</p>
            </div>
        </div>

        <h3 class="text-lg font-bold text-slate-950 mt-14 mb-2">Ücretli özellikler</h3>
        <p class="text-sm text-slate-500 mb-5">Bu araçlar, ilgili apartmanın ücretli aboneliği onaylandığında açılır.</p>
        <div class="grid sm:grid-cols-2 gap-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h4 class="font-bold text-slate-900 mb-1">Otomatik aidat planlama</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Aidat planı tanımlayın ve dönemsel tahakkuku plandan üretin.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h4 class="font-bold text-slate-900 mb-1">Giderlerin dairelere dağıtılması</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Kaydettiğiniz gideri dairelere borç olarak dağıtın.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h4 class="font-bold text-slate-900 mb-1">Metrekare ve pay dağıtımı</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Dağıtımda metrekare ve pay çarpanını kullanın.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h4 class="font-bold text-slate-900 mb-1">Belge yükleme</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Gider kaydına belge ekleyin.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h4 class="font-bold text-slate-900 mb-1">PDF ve Excel dışa aktarma</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Listeleri ve raporları PDF veya Excel olarak indirin.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h4 class="font-bold text-slate-900 mb-1">Aidat tahsilat matrisi</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Dairelerin dönemsel tahsilat durumunu matris olarak görün.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h4 class="font-bold text-slate-900 mb-1">Gecikme raporu</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Geciken borçları ayrı bir raporda izleyin.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h4 class="font-bold text-slate-900 mb-1">Yıllık faaliyet raporu</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Yılın gelir ve gider özetini görüntüleyin.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h4 class="font-bold text-slate-900 mb-1">Bütçe raporu</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Bütçe raporunu görüntüleyin.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h4 class="font-bold text-slate-900 mb-1">Aylık aidat panosu</h4>
                <p class="text-sm text-slate-500 leading-relaxed">Ayın aidat durumunu pano görünümünde izleyin.</p>
            </div>
        </div>
    </div>
</section>

<section class="py-20 px-4 sm:px-6 bg-white">
    <div class="max-w-6xl mx-auto">
        <div class="max-w-3xl mb-12">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-950 mb-3">Nasıl çalışır?</h2>
            <p class="text-slate-500 text-lg">Hesabınızı açın, apartmanınızı ekleyin, temel kullanımı hemen başlatın.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white text-lg font-black flex items-center justify-center mb-4">1</div>
                <h3 class="font-bold text-slate-900 mb-2">Hesabınızı oluşturun</h3>
                <p class="text-sm text-slate-500 leading-relaxed">Ücretsiz hesabınızı oluşturun.</p>
            </div>
            <div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white text-lg font-black flex items-center justify-center mb-4">2</div>
                <h3 class="font-bold text-slate-900 mb-2">Apartmanınızı ekleyin</h3>
                <p class="text-sm text-slate-500 leading-relaxed">Apartman bilgilerinizi ve dairelerinizi tanımlayın.</p>
            </div>
            <div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white text-lg font-black flex items-center justify-center mb-4">3</div>
                <h3 class="font-bold text-slate-900 mb-2">Ücretsiz başlayın</h3>
                <p class="text-sm text-slate-500 leading-relaxed">Temel özellikleri ücretsiz kullanmaya başlayın.</p>
            </div>
            <div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white text-lg font-black flex items-center justify-center mb-4">4</div>
                <h3 class="font-bold text-slate-900 mb-2">İhtiyacınız olduğunda ücretli özelliklere geçin</h3>
                <p class="text-sm text-slate-500 leading-relaxed">Apartmanınız için uygun ücretli aboneliği seçin, ödeme sürecini tamamlayın ve gelişmiş özellikleri kullanmaya başlayın.</p>
            </div>
        </div>
    </div>
</section>

<section class="py-20 px-4 sm:px-6 bg-slate-50">
    <div class="max-w-3xl mx-auto">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-950 mb-4">Ücretlendirme</h2>
        <p class="text-slate-600 leading-relaxed">
            Temel kullanım ücretsiz başlar. Ücretli abonelik, her apartman için ayrıca açılır. Ayrıntılı tarife ve ödeme adımları fiyatlandırma sayfasındadır.
        </p>
        <div class="mt-8 flex flex-col sm:flex-row gap-3">
            <a href="{{ route('pricing') }}" class="inline-flex justify-center rounded-2xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white hover:bg-emerald-700 transition-colors">Fiyatlandırmayı İncele</a>
            <a href="{{ route('faq') }}" class="inline-flex justify-center rounded-2xl border-2 border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 hover:border-slate-300 hover:bg-slate-50 transition-colors">Sık Sorulan Sorular</a>
        </div>
    </div>
</section>

<section class="py-20 px-4 sm:px-6 bg-white">
    <div class="max-w-3xl mx-auto">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-950 mb-4">Bir hesabınızla birden fazla apartmanı yönetin.</h2>
        <p class="text-slate-600 leading-relaxed">
            Tek hesabınız üzerinden yönettiğiniz apartmanları ayrı ayrı takip edebilirsiniz. Her apartmanın aboneliği ve ödemesi ayrıdır.
        </p>
        <p class="mt-4 text-slate-600 leading-relaxed">
            Apartman sayısı hesabınızı sınırlamaz. Her apartman ücretli aboneliğe kendi siparişiyle geçer.
        </p>
    </div>
</section>

<section class="py-16 px-4 sm:px-6 bg-slate-50 border-t border-slate-100">
    <div class="max-w-3xl mx-auto text-center">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-950 mb-3">Hesabınızı oluşturun</h2>
        <p class="text-slate-500 mb-8">Apartmanınızı ekleyin ve temel özellikleri kullanmaya başlayın.</p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('register') }}" class="rounded-2xl bg-emerald-600 px-8 py-3.5 text-base font-bold text-white hover:bg-emerald-700 transition-colors">Ücretsiz Başla</a>
            <a href="{{ route('login') }}" class="rounded-2xl border-2 border-slate-200 bg-white px-8 py-3.5 text-base font-semibold text-slate-700 hover:border-slate-300 hover:bg-slate-50 transition-colors">Giriş Yap</a>
        </div>
    </div>
</section>

@include('microsite.partials.footer')

@endsection
