<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'AidatCep — Apartman Yönetimi')</title>
    <meta name="description" content="@yield('description', 'AidatCep, apartman yöneticileri için aidat, tahsilat, gider, kasa, cari hesap ve Daire Sakini yönetimini tek yerde toplar.')">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-white text-slate-900 antialiased">
    @yield('content')
</body>
</html>
