<header class="fixed top-0 inset-x-0 z-50 bg-white/95 backdrop-blur border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
        <a href="{{ route('landing') }}" class="flex items-center gap-2 min-w-0">
            <img src="{{ asset('images/logo.png') }}" alt="" class="h-8 w-auto" width="32" height="32">
            <span class="text-lg font-bold truncate">
                <span style="color:#336633">Aidat</span><span class="text-slate-400">Cep</span>
            </span>
        </a>
        <nav class="hidden md:flex items-center gap-6" aria-label="Site">
            <a href="{{ route('pricing') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Fiyatlandırma</a>
            <a href="{{ route('faq') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Sık Sorulan Sorular</a>
            <a href="{{ route('login') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-900">Giriş Yap</a>
            <a href="{{ route('register') }}" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Ücretsiz Başla</a>
        </nav>
        <details class="md:hidden relative">
            <summary class="list-none cursor-pointer rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 [&::-webkit-details-marker]:hidden">
                Menü
            </summary>
            <nav class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg" aria-label="Mobil">
                <a href="{{ route('pricing') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fiyatlandırma</a>
                <a href="{{ route('faq') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Sık Sorulan Sorular</a>
                <a href="{{ route('login') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Giriş Yap</a>
                <a href="{{ route('register') }}" class="mt-1 block rounded-lg bg-emerald-700 px-3 py-2 text-center text-sm font-semibold text-white hover:bg-emerald-800">Ücretsiz Başla</a>
            </nav>
        </details>
    </div>
</header>
