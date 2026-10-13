<footer class="py-10 px-4 sm:px-6 bg-slate-950 border-t border-slate-800">
    <div class="max-w-6xl mx-auto grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <a href="https://ko.com.tr/" target="_blank" rel="noopener noreferrer" class="inline-flex">
                <img src="https://atmaca.ko.com.tr/images/uploads/firms/ko.png" alt="KO" class="h-7 w-auto opacity-80">
            </a>
            <p class="mt-4 text-sm font-semibold text-white">AidatCep</p>
            <p class="mt-1 text-xs text-slate-400">Kapital Online markasıdır.</p>
        </div>
        <div class="text-sm text-slate-400 space-y-2">
            <p class="font-semibold text-slate-200">İletişim</p>
            <a href="tel:+902163774000" class="block hover:text-slate-200">+90 216 377 4000</a>
            <a href="mailto:info@aidatcep.com" class="block hover:text-slate-200">info@aidatcep.com</a>
            <a href="https://ko.com.tr/" target="_blank" rel="noopener noreferrer" class="block hover:text-slate-200">ko.com.tr</a>
        </div>
        <div class="text-sm text-slate-400 space-y-2">
            <p class="font-semibold text-slate-200">Yasal</p>
            <a href="{{ route('legal.membership') }}" class="block hover:text-slate-200">Üyelik Sözleşmesi</a>
            <a href="{{ route('legal.resident-data') }}" class="block hover:text-slate-200">Daire Sakini Verisi</a>
            <a href="{{ route('legal.distance-sales') }}" class="block hover:text-slate-200">Mesafeli Satış Sözleşmesi</a>
            <a href="{{ route('legal.pre-information') }}" class="block hover:text-slate-200">Ön Bilgilendirme Formu</a>
            <a href="{{ route('legal.privacy') }}" class="block hover:text-slate-200">Gizlilik ve KVKK</a>
            <a href="{{ route('legal.cookies') }}" class="block hover:text-slate-200">Çerez Aydınlatma</a>
            <a href="{{ route('legal.cancellation') }}" class="block hover:text-slate-200">İptal ve İade</a>
        </div>
        <div class="text-sm space-y-2">
            <p class="font-semibold text-slate-200">Site</p>
            <a href="{{ route('pricing') }}" class="block text-slate-400 hover:text-slate-200">Fiyatlandırma</a>
            <a href="{{ route('faq') }}" class="block text-slate-400 hover:text-slate-200">Sık Sorulan Sorular</a>
            <a href="{{ route('login') }}" class="block text-slate-400 hover:text-slate-200">Giriş Yap</a>
            <a href="{{ route('register') }}" class="block text-slate-400 hover:text-slate-200">Ücretsiz Başla</a>
        </div>
    </div>
    <p class="max-w-6xl mx-auto mt-8 text-xs text-slate-500">&copy; {{ date('Y') }} AidatCep — Kapital Online markasıdır.</p>
</footer>
