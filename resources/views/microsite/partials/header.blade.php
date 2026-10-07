<header class="fixed top-0 inset-x-0 z-50 bg-white/90 backdrop-blur border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
        <a href="{{ route('landing') }}" class="flex items-center gap-2 min-w-0">
            <img src="{{ asset('images/logo.png') }}" alt="AidatCep" class="h-8 w-auto">
            <span class="text-lg font-bold truncate">
                <span style="color:#336633">Aidat</span><span class="text-slate-400">Cep</span>
            </span>
        </a>
        <div class="flex items-center gap-3 shrink-0">
            <a href="{{ route('pricing') }}" class="hidden sm:inline text-sm font-semibold text-slate-600 hover:text-slate-900 transition-colors">
                Fiyatlandırma
            </a>
            <a href="{{ route('login') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900 transition-colors">
                Giriş Yap
            </a>
            <a href="{{ route('register') }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors">
                Ücretsiz Başla
            </a>
        </div>
    </div>
</header>
