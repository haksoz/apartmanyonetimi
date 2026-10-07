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
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Hesap oluşturmak ücretsizdir ve kayıt sırasında sipariş açılmaz. 1–100 daireli apartmanlarda temel kullanım süresizdir. 101–150 daire ücretsiz açılamaz. 151 ve üzeri daire için teklif gerekir. Ayrıntılı tarife <a href="{{ route('pricing') }}" class="font-semibold text-emerald-700 hover:text-emerald-800">fiyatlandırma</a> sayfasındadır.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Ücretsiz kullanımda hangi özellikleri kullanabilirim?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Aidat takibi, tahsilat, gider ve kasa, cari ekstre görüntüleme, Daire Sakini ve kullanıcı yönetimi, birden fazla apartman ve temel rapor ekranları ücretsiz kullanımda açıktır.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Ücretli abonelik ne zaman gerekir?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Otomatik aidat planı, giderin dairelere dağıtılması, metrekare ve pay dağıtımı, belge yükleme, PDF/Excel dışa aktarma ve gelişmiş raporlar için ilgili apartmanda ücretli abonelik gerekir. 101–150 daireli apartman da ücretsiz açılamaz.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Ücret nasıl belirleniyor?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Ücret, apartmanın daire sayısının girdiği banta göre belirlenir. Siparişte aylık veya yıllık dönem seçilir. 151 ve üzeri daire için sitede tutar gösterilmez; teklif istenir. Güncel tutarlar <a href="{{ route('pricing') }}" class="font-semibold text-emerald-700 hover:text-emerald-800">fiyatlandırma</a> sayfasındadır.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Birden fazla apartman yönetebilir miyim?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Evet. Tek hesabınızla birden fazla apartman açabilirsiniz. Apartman sayısı hesabınızı sınırlamaz. Her apartmanın ücreti kendi daire sayısına göre ayrı hesaplanır.</p>
            </details>
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-slate-900">Her apartman için ayrı mı ödeme yapılır?</summary>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Evet. Her apartmanın aboneliği ve siparişi ayrıdır. Bir sipariş tek apartmana aittir.</p>
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
