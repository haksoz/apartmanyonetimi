<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'AidatCep') &mdash; Apartman Yönetimi</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col">

    {{-- Simple Header --}}
    <header class="bg-white border-b border-slate-100">
        <div class="max-w-md mx-auto px-4 py-4 flex items-center justify-center">
            <a href="{{ route('landing') }}" class="flex items-center gap-2">
                <img src="{{ asset('images/logo.png') }}" alt="AidatCep" class="h-8 w-auto">
                <span class="text-lg font-bold">
                    <span style="color:#336633">Aidat</span><span class="text-slate-400">Cep</span>
                </span>
            </a>
        </div>
    </header>

    {{-- Main Content --}}
    <main class="flex-1 flex items-center justify-center p-4">
        @yield('content')
    </main>

    {{-- Simple Footer --}}
    <footer class="bg-white border-t border-slate-100 py-4">
        <div class="max-w-md mx-auto px-4 text-center text-xs text-slate-400">
            &copy; {{ date('Y') }} AidatCep. Tüm hakları saklıdır.
        </div>
    </footer>

</body>
</html>
