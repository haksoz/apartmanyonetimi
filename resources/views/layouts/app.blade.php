<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AidatCep &mdash; Apartman Yönetim Sistemi</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <script>
        // Sidebar state management
        (function() {
            const sidebarState = localStorage.getItem('sidebar-collapsed');
            if (sidebarState === 'true') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        })();
    </script>
    <style>
        .sidebar-transition {
            transition: width 0.3s ease-in-out;
        }
        .sidebar-collapsed .sidebar-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
            white-space: nowrap;
        }
        .sidebar-collapsed aside {
            width: 5rem;
        }
        .sidebar-collapsed main {
            padding-left: 5rem;
        }
        @media (max-width: 1023px) {
            .sidebar-collapsed aside {
                width: 18rem;
            }
            .sidebar-collapsed main {
                padding-left: 0;
            }
            .sidebar-collapsed .sidebar-text {
                opacity: 1;
                width: auto;
                overflow: visible;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="min-h-screen">
        @auth
            {{-- Mobile Overlay --}}
            <div id="mobile-overlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="closeMobileSidebar()"></div>

            {{-- Header Bar --}}
            <header class="fixed top-0 left-0 right-0 h-16 bg-white border-b border-slate-200 z-50 flex items-center justify-between px-4 lg:px-6">
                <div class="flex items-center gap-4">
                    {{-- Mobile Menu Toggle --}}
                    <button type="button" onclick="toggleMobileSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-slate-100">
                        <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                        </svg>
                    </button>
                    {{-- Logo --}}
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <img src="{{ asset('images/logo.png') }}" alt="AidatCep" class="h-8 w-auto">
                        <span class="text-lg font-bold"><span style="color:#336633">Aidat</span><span class="text-slate-400">Cep</span></span>
                    </a>
                </div>

                {{-- Right Side: User & Apartment Info --}}
                <div class="flex items-center gap-4">
                    @if ($currentApartment)
                        <div class="relative hidden md:block" id="apt-switcher">
                            <button type="button" onclick="document.getElementById('apt-dropdown').classList.toggle('hidden')" class="flex items-center gap-2 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded-lg text-sm transition-colors">
                                <svg class="w-4 h-4 text-slate-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
                                </svg>
                                <span class="text-slate-700 font-medium max-w-36 truncate">{{ $currentApartment->name }}</span>
                                @if ($availableApartments->count() > 1)
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                    </svg>
                                @endif
                            </button>

                            @if ($availableApartments->count() > 1)
                                <div id="apt-dropdown" class="hidden absolute right-0 top-full mt-1 w-56 rounded-xl bg-white shadow-lg border border-slate-200 py-1 z-50">
                                    @foreach ($availableApartments as $apt)
                                        <form method="POST" action="{{ route('current-apartment.update') }}">
                                            @csrf
                                            <input type="hidden" name="apartment_id" value="{{ $apt->id }}">
                                            <button type="submit" class="w-full text-left px-4 py-2.5 text-sm hover:bg-slate-50 flex items-center gap-2 {{ $apt->id === $currentApartment->id ? 'font-semibold text-emerald-700' : 'text-slate-700' }}">
                                                @if ($apt->id === $currentApartment->id)
                                                    <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5"/>
                                                    </svg>
                                                @else
                                                    <span class="w-4 flex-shrink-0"></span>
                                                @endif
                                                <span class="truncate">{{ $apt->name }}</span>
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-center gap-3">
                        <div class="text-right hidden sm:block">
                            <div class="text-sm font-medium text-slate-900">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-slate-500">
                                @if(auth()->user()->isAdmin())
                                    Süper Yönetici
                                @elseif(isset($navIsOwner) && $navIsOwner)
                                    Apartman Yöneticisi
                                @elseif(isset($currentApartment) && $currentApartment)
                                    Apartman Üyesi
                                @else
                                    Kullanıcı
                                @endif
                            </div>
                        </div>
                        <div class="w-8 h-8 bg-emerald-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                            </svg>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                            @csrf
                            <button type="submit" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors" title="Çıkış Yap">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </header>
        @endauth

        {{-- Sidebar --}}
        <aside id="sidebar" class="fixed inset-y-0 left-0 w-72 bg-white border-r border-slate-200 pt-20 pb-6 px-4 sidebar-transition z-30 lg:z-30 -translate-x-full lg:translate-x-0">
            @auth
                {{-- Mobile Close Button --}}
                <button type="button" onclick="closeMobileSidebar()" class="lg:hidden absolute top-4 right-4 p-2 rounded-lg hover:bg-slate-100 text-slate-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                {{-- Navigation --}}
                <nav class="space-y-1">
                    <a href="{{ route('dashboard') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('dashboard') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z"/>
                        </svg>
                        <span class="sidebar-text">Dashboard</span>
                    </a>

                    @if($navIsOwner && $currentApartment)
                    <a href="{{ route('accounts.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('accounts.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.592-2.641m-3.958-5.599c.351.351.645.748.876 1.185M9 13.5V9.75a6 6 0 0112 0v3"/>
                        </svg>
                        <span class="sidebar-text">Hesaplar</span>
                    </a>
                    @endif

                    @if($currentApartment)
                    <a href="{{ route('expenses.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('expenses.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 6L9 12.75l4.286-4.286a11.948 11.948 0 011.512.422m17.085 8.407l-3.82-3.82a9.969 9.969 0 011.512-3.686 9.971 9.971 0 013.686-1.512l3.82 3.82m-7.52-2.119l-3.82-3.82a9.969 9.969 0 011.512-3.686 9.971 9.971 0 013.686-1.512l3.82 3.82"/>
                        </svg>
                        <span class="sidebar-text">Giderler</span>
                    </a>
                    @endif

                    @if($currentApartment)
                    <a href="{{ route('dues.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('dues.*') && !request()->routeIs('due-plans.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="sidebar-text">Aidatlar</span>
                    </a>
                    @endif

                    @if($navIsOwner && $currentApartment)
                    <a href="{{ route('due-plans.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('due-plans.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="sidebar-text">Aidat Planlama</span>
                    </a>
                    @endif

                    @if($navIsOwner && $currentApartment)
                    <a href="{{ route('cash.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('cash.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                        </svg>
                        <span class="sidebar-text">Kasa</span>
                    </a>
                    @endif

                    @if($navIsOwner && $currentApartment)
                    <a href="{{ route('ledger.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('ledger.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                        </svg>
                        <span class="sidebar-text">Muhasebe</span>
                    </a>
                    @endif

                    @if($navIsOwner && $currentApartment)
                    {{-- Ayarlar Grubu --}}
                    <div class="sidebar-text pt-3 pb-1 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Ayarlar</div>
                    @endif

                    @if($navIsOwner && $currentApartment)
                    <a href="{{ route('apartments.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('apartments.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
                        </svg>
                        <span class="sidebar-text">Apartman</span>
                    </a>
                    @endif

                    @if($navIsOwner && $currentApartment)
                    <a href="{{ route('units.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('units.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205l3 1m-3-1l-3-1m.75 3.205l-3 1m3-1l1.5.545M5.25 7.5l-1.5.545M5.25 7.5l3 1m-3-1l-3-1m.75 3.205l-3 1m3-1l1.5.545"/>
                        </svg>
                        <span class="sidebar-text">Daireler</span>
                    </a>
                    @endif

                    @if($navIsOwner && $currentApartment)
                    <a href="{{ route('categories.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('categories.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6h.008v.008H6V6z"/>
                        </svg>
                        <span class="sidebar-text">Kategoriler</span>
                    </a>
                    @endif

                    @if($navIsOwner && $currentApartment)
                    <a href="{{ route('users.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('users.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                        </svg>
                        <span class="sidebar-text">Kullanıcılar</span>
                    </a>
                    @endif
                </nav>

                {{-- Mobile Logout --}}
                <form method="POST" action="{{ route('logout') }}" class="mt-6 lg:hidden">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-500 font-medium">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                        </svg>
                        <span>Çıkış Yap</span>
                    </button>
                </form>

                {{-- Mobile Brand & Contact --}}
                <div class="lg:hidden mt-6 pt-5 border-t border-slate-100">
                    <div class="flex items-center gap-2 mb-1">
                        <img src="{{ asset('images/logo.png') }}" alt="AidatCep" class="h-7 w-auto">
                        <span class="font-bold text-base"><span style="color:#336633">Aidat</span><span class="text-slate-400">Cep</span></span>
                    </div>
                    <p class="text-xs text-slate-400 mb-4 italic">Apartman yönetimi, cebinizde.</p>
                    <div class="space-y-2 text-xs text-slate-500">
                        <a href="tel:+902163774000" class="flex items-center gap-2 hover:text-slate-700">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                            </svg>
                            +90 216 377 4000
                        </a>
                        <a href="mailto:info@aidatcep.com" class="flex items-center gap-2 hover:text-slate-700">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                            </svg>
                            info@aidatcep.com
                        </a>
                        <div class="flex items-start gap-2">
                            <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                            </svg>
                            <span>Orta Mah. Ordu Sok. Ozan İş Merkezi No:21/8 Kartal / İstanbul</span>
                        </div>
                    </div>
                </div>

                {{-- Collapse Toggle (Desktop only) --}}
                <div class="hidden lg:block mt-auto pt-4 border-t border-slate-200">
                    <button type="button" onclick="toggleSidebar()" class="w-full flex items-center justify-center gap-2 p-2 rounded-lg hover:bg-slate-100 text-slate-500 transition-colors" title="Menüyü Daralt/Genişlet">
                        <svg id="collapse-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.25 4.5l7.5 7.5-7.5 7.5m-6-15l7.5 7.5-7.5 7.5"/>
                        </svg>
                        <span class="sidebar-text text-sm">Daralt</span>
                    </button>
                </div>
            @endauth
        </aside>

        {{-- Main Content --}}
        <main class="pt-16 lg:pl-72 sidebar-transition min-h-screen flex flex-col">
            <div class="mx-auto max-w-7xl w-full px-4 py-8 sm:px-6 lg:px-8 flex-1">
                @if (session('status'))
                    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
                @endif
                @if (session('error_html'))
                    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{!! session('error_html') !!}</div>
                @endif
                @yield('content')
            </div>

            {{-- Desktop Footer --}}
            <footer class="hidden lg:block border-t border-slate-200 bg-white mt-8">
                <div class="mx-auto max-w-7xl px-6 lg:px-8 py-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <img src="{{ asset('images/logo.png') }}" alt="AidatCep" class="h-8 w-auto">
                        <div>
                            <div class="font-bold text-sm leading-tight"><span style="color:#336633">Aidat</span><span class="text-slate-400">Cep</span></div>
                            <div class="text-xs text-slate-400">Apartman Yönetim Sistemleri</div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-slate-500">
                        <a href="tel:+902163774000" class="flex items-center gap-1.5 hover:text-slate-700 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                            </svg>
                            +90 216 377 4000
                        </a>
                        <a href="mailto:info@aidatcep.com" class="flex items-center gap-1.5 hover:text-slate-700 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                            </svg>
                            info@aidatcep.com
                        </a>
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                            </svg>
                            Orta Mah. Ordu Sok. Ozan İş Merkezi No:21/8 Kartal / İstanbul
                        </span>
                    </div>
                    <div class="text-xs text-slate-400">© {{ date('Y') }} AidatCep</div>
                </div>
            </footer>
        </main>
    </div>

    @auth
        <script>
            function toggleSidebar() {
                const isCollapsed = document.documentElement.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebar-collapsed', isCollapsed);
                
                // Update collapse icon direction
                const icon = document.getElementById('collapse-icon');
                if (icon) {
                    if (isCollapsed) {
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>';
                    } else {
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.25 4.5l7.5 7.5-7.5 7.5m-6-15l7.5 7.5-7.5 7.5"/>';
                    }
                }
            }

            function toggleMobileSidebar() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('mobile-overlay');
                const isOpen = !sidebar.classList.contains('-translate-x-full');

                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
                document.body.classList.toggle('overflow-hidden');

                if (isOpen) {
                    sidebar.classList.remove('z-50');
                    sidebar.classList.add('z-30');
                } else {
                    sidebar.classList.remove('z-30');
                    sidebar.classList.add('z-50');
                }
            }

            function closeMobileSidebar() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('mobile-overlay');
                
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                sidebar.classList.remove('z-50');
                sidebar.classList.add('z-30');
            }

            // Close mobile sidebar on route change
            document.querySelectorAll('#sidebar a').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 1024) {
                        closeMobileSidebar();
                    }
                });
            });

            // Close apartment dropdown on outside click
            document.addEventListener('click', function(e) {
                const switcher = document.getElementById('apt-switcher');
                const dropdown = document.getElementById('apt-dropdown');
                if (switcher && dropdown && !switcher.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });

            // Update collapse icon on page load
            document.addEventListener('DOMContentLoaded', function() {
                const isCollapsed = document.documentElement.classList.contains('sidebar-collapsed');
                const icon = document.getElementById('collapse-icon');
                if (icon && isCollapsed) {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>';
                }
            });
        </script>
    @endauth
</body>
</html>
