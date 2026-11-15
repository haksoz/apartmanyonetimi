<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('setup_title', 'Apartman Kurulumu') — AidatCep</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        #apartment-setup-dialog { width: min(@yield('setup_width', '44rem'), calc(100% - 2rem)); max-width: none; max-height: calc(100dvh - 2rem); margin: auto; padding: 0; border: 0; overflow-y: auto; overscroll-behavior: contain; }
        #apartment-setup-dialog::backdrop { background: rgb(15 23 42 / .55); }
        @media (max-width: 640px) {
            #apartment-setup-dialog { width: calc(100% - 1rem); max-height: calc(100dvh - 1rem); border-radius: 1rem; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <dialog id="apartment-setup-dialog" aria-labelledby="apartment-setup-title" aria-describedby="apartment-setup-description" class="rounded-2xl bg-slate-50 shadow-xl">
        <header class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-slate-200 bg-white p-4 sm:px-6">
            <div class="min-w-0">
                <div class="mb-2 flex items-center gap-2">
                    <img src="{{ asset('images/logo.png') }}" alt="" class="h-8 w-auto">
                    <span class="text-xl font-bold"><span style="color:#336633">Aidat</span><span class="text-slate-400">Cep</span></span>
                </div>
                <h1 id="apartment-setup-title" tabindex="-1" class="text-lg font-bold text-slate-950">@yield('setup_title', 'Apartman Kurulumu')</h1>
                <p id="apartment-setup-description" class="mt-1 text-xs text-slate-500">@hasSection('setup_description') @yield('setup_description') @else {{ $apartment->name ?? 'Yeni apartman' }} · Kaydettiğiniz adımlara daha sonra devam edebilirsiniz. @endif</p>
            </div>
            <a id="apartment-setup-close" href="{{ route('subscriber.dashboard') }}" aria-label="Kurulumu kapat ve abone paneline dön" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Kapat</a>
        </header>
        <main class="p-4 sm:p-6">
            @yield('wizard_steps')
            @if (session('status'))
                <p class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>
            @endif
            @if (session('error'))
                <p class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</p>
            @endif
            @if ($errors->any())
                <ul role="alert" class="mb-4 list-inside list-disc rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif
            @yield('content')
        </main>
    </dialog>
    <script>
        (() => {
            const dialog = document.getElementById('apartment-setup-dialog');
            dialog.showModal();
            document.getElementById('apartment-setup-title').focus();
            dialog.addEventListener('cancel', event => {
                event.preventDefault();
                // Escape first dismisses an open consent text within the setup dialog.
                const openConsent = Array.from(dialog.querySelectorAll('[role="dialog"]')).find(node => !node.classList.contains('hidden'));
                if (openConsent) {
                    openConsent.querySelector('[data-consent-close]')?.click();
                    return;
                }
                window.location.assign(document.getElementById('apartment-setup-close').href);
            });
        })();
    </script>
</body>
</html>
