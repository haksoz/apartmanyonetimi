@extends('layouts.landing')

@section('title', 'Sık Sorulan Sorular — AidatCep')

@section('content')

@include('microsite.partials.header')

<main class="pt-32 pb-20 px-4 sm:px-6">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-950 mb-8">Sık sorulan sorular</h1>
        <div class="divide-y divide-slate-200 border-y border-slate-200">
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">AidatCep ücretsiz mi?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Evet. Bir apartman için daire sayısı sınırı olmadan temel özellikleri süresiz ve ücretsiz kullanabilirsiniz. Gelişmiş özellikler için ücretli abonelik seçeneklerimiz bulunmaktadır. Ayrıntılı tarife <a href="{{ route('pricing') }}" class="font-semibold text-emerald-700 hover:text-emerald-800">fiyatlandırma</a> sayfasındadır.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Ücretsiz kullanımda hangi özellikleri kullanabilirim?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Aidat takibi, tahsilat, gider ve kasa, cari ekstre görüntüleme, Daire Sakini ve kullanıcı yönetimi ile temel rapor ekranları ücretsiz kullanımda açıktır.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Ücretli abonelik ne zaman gerekir?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Otomatik aidat planı, giderin dairelere dağıtılması, metrekare ve pay dağıtımı, belge yükleme, PDF/Excel dışa aktarma ve gelişmiş raporlar için ilgili apartmanda ücretli abonelik gerekir.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Ücret nasıl belirleniyor?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Ücretli abonelik bedeli apartmanın daire sayısına ve seçilen abonelik dönemine göre belirlenir. 101 ve üzeri daireli apartmanlarda daire başına birim fiyat uygulanır. Güncel ücretler <a href="{{ route('pricing') }}" class="font-semibold text-emerald-700 hover:text-emerald-800">fiyatlandırma</a> sayfasındadır.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Birden fazla apartman yönetebilir miyim?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Evet. Birden fazla apartmanı aynı hesabınız üzerinden yönetebilirsiniz. Ek apartman oluşturma talepleriniz için <a href="mailto:info@aidatcep.com" class="font-semibold text-emerald-800 underline">info@aidatcep.com</a> adresinden bizimle iletişime geçebilirsiniz.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Her apartman için ayrı mı ödeme yapılır?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Her apartmanın ücretli abonelik ve sipariş işlemleri ayrı yürütülür. Bir ücretli abonelik siparişi tek apartmana aittir.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Aylık ve yıllık abonelik seçenekleri var mı?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Evet. Ücretli siparişi oluştururken dönemi aylık veya yıllık olarak seçersiniz.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Aboneliğimi nasıl yenilerim?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Yenileme, aynı apartman için yeni bir sipariş oluşturularak yapılır. Ücretli dönem sürüyorsa yeni dönem, mevcut dönemin bitişinden itibaren başlar.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Ödeme nasıl yapılıyor?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Ödeme havale/EFT ile yapılır. Siparişten sonra banka bilgileri hesabınızda görünür. Dekont veya ödeme referansı gönderildikten sonra sipariş admin onayını bekler ve bu aşamada sizin tarafınızdan değiştirilemez.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Abonelik süresi bittiğinde verilerim silinir mi?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Ücretli özelliklere erişiminiz sona erer; apartman ve mevcut verileriniz silinmez. İhtiyaç duyduğunuzda aynı apartman için yeniden ücretli abonelik başlatabilirsiniz.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Daire Sakini sisteme nasıl eklenir?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Yönetici, apartman içinden daire hesabına kullanıcı ekler. Halka açık kayıt ekranı yönetici hesabı açar.</p>
            </details>
        </div>
    </div>
</main>

@include('microsite.partials.footer')

@endsection
