<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AidatCep &mdash; Apartman Yönetimi, Cebinizde</title>
    <meta name="description" content="Apartmanınızın aidat takibini, gider yönetimini ve sakin iletişimini tek platformda yönetin.">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-white text-slate-900 antialiased">
    @yield('content')
</body>
</html>
