<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurulum — AidatCep</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen flex flex-col">

    <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <img src="{{ asset('images/logo.png') }}" alt="AidatCep" class="h-8 w-auto">
            <span class="text-lg font-bold"><span style="color:#336633">Aidat</span><span class="text-slate-400">Cep</span></span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-slate-400 hover:text-slate-700">Çıkış Yap</button>
        </form>
    </header>

    <main class="flex-1 flex items-start justify-center px-4 py-12">
        <div class="w-full max-w-xl">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
            @endif
            @yield('content')
        </div>
    </main>

    <footer class="text-center py-4 text-xs text-slate-400">
        © {{ date('Y') }} AidatCep · Apartman Yönetim Sistemleri
    </footer>

</body>
</html>
