<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'KapitalOnline Apartman Yönetim Sistemi') }}</title>
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
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="min-h-screen">
        @auth
            {{-- Mobile Overlay --}}
            <div id="mobile-overlay" class="fixed inset-0 bg-black/50 z-20 hidden lg:hidden" onclick="closeMobileSidebar()"></div>

            {{-- Header Bar --}}
            <header class="fixed top-0 left-0 right-0 h-16 bg-white border-b border-slate-200 z-20 flex items-center justify-between px-4 lg:px-6">
                <div class="flex items-center gap-4">
                    {{-- Mobile Menu Toggle --}}
                    <button type="button" onclick="toggleMobileSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-slate-100">
                        <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                        </svg>
                    </button>
                    {{-- Logo --}}
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                            </svg>
                        </div>
                        <span class="font-bold text-slate-950 hidden sm:block">KapitalOnline</span>
                    </a>
                </div>

                {{-- Right Side: User & Apartment Info --}}
                <div class="flex items-center gap-4">
                    @if ($currentApartment)
                        <div class="hidden md:flex items-center gap-2 px-3 py-1.5 bg-slate-100 rounded-lg text-sm">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
                            </svg>
                            <span class="text-slate-700 font-medium">{{ $currentApartment->name }}</span>
                        </div>
                    @endif

                    <div class="flex items-center gap-3">
                        <div class="text-right hidden sm:block">
                            <div class="text-sm font-medium text-slate-900">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-slate-500">{{ auth()->user()->isAdmin() ? 'Süper Yönetici' : 'Apartman Yöneticisi' }}</div>
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
        <aside id="sidebar" class="fixed inset-y-0 left-0 w-72 bg-white border-r border-slate-200 pt-20 pb-6 px-4 sidebar-transition z-40 -translate-x-full lg:translate-x-0">
            @auth
                {{-- Navigation --}}
                <nav class="space-y-1 relative z-50">
                    <a href="{{ route('dashboard') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('dashboard') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z"/>
                        </svg>
                        <span class="sidebar-text">Dashboard</span>
                    </a>

                    <a href="{{ route('accounts.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('accounts.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.592-2.641m-3.958-5.599c.351.351.645.748.876 1.185M9 13.5V9.75a6 6 0 0112 0v3"/>
                        </svg>
                        <span class="sidebar-text">Hesaplar</span>
                    </a>

                    <a href="{{ route('expenses.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('expenses.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 6L9 12.75l4.286-4.286a11.948 11.948 0 011.512.422m17.085 8.407l-3.82-3.82a9.969 9.969 0 011.512-3.686 9.971 9.971 0 013.686-1.512l3.82 3.82m-7.52-2.119l-3.82-3.82a9.969 9.969 0 011.512-3.686 9.971 9.971 0 013.686-1.512l3.82 3.82"/>
                        </svg>
                        <span class="sidebar-text">Giderler</span>
                    </a>

                    <a href="{{ route('dues.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('dues.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="sidebar-text">Aidatlar</span>
                    </a>

                    <a href="{{ route('payments.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('payments.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/>
                        </svg>
                        <span class="sidebar-text">Tahsilatlar</span>
                    </a>

                    <a href="{{ route('cash.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('cash.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                        </svg>
                        <span class="sidebar-text">Kasa</span>
                    </a>

                    <a href="{{ route('ledger.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('ledger.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                        </svg>
                        <span class="sidebar-text">Muhasebe</span>
                    </a>

                    <a href="{{ route('apartments.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('apartments.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
                        </svg>
                        <span class="sidebar-text">Apartman</span>
                    </a>

                    <a href="{{ route('units.index') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-slate-700 font-medium {{ request()->routeIs('units.*') ? 'bg-emerald-50 text-emerald-700' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205l3 1m-3-1l-3-1m.75 3.205l-3 1m3-1l1.5.545M5.25 7.5l-1.5.545M5.25 7.5l3 1m-3-1l-3-1m.75 3.205l-3 1m3-1l1.5.545"/>
                        </svg>
                        <span class="sidebar-text">Daireler</span>
                    </a>
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
        <main class="pt-16 lg:pl-72 sidebar-transition">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                @if (session('status'))
                    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
                @endif
                @yield('content')
            </div>
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
                
                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
                document.body.classList.toggle('overflow-hidden');
            }

            function closeMobileSidebar() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('mobile-overlay');
                
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            // Close mobile sidebar on route change
            document.querySelectorAll('#sidebar a').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 1024) {
                        closeMobileSidebar();
                    }
                });
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
