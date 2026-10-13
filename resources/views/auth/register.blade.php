@extends('layouts.guest')

@section('title', 'Üye Ol')

@section('content')
    <div class="w-full max-w-md rounded-2xl bg-white p-4 shadow-sm sm:p-6">
        <h1 class="text-xl font-bold text-slate-950">Ücretsiz Hesabınızı Oluşturun</h1>
        <p class="mt-1 text-sm text-slate-500">Hesabınızı oluşturun, apartmanınızı ekleyin ve temel özellikleri ücretsiz kullanmaya başlayın.</p>

        <form method="POST" action="{{ route('register') }}" class="mt-3 space-y-2">
            @csrf
            <div>
                <label for="name" class="text-sm font-medium text-slate-700">Ad Soyad</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus class="mt-0.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('name')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="email" class="text-sm font-medium text-slate-700">E-posta</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-0.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('email')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="password" class="text-sm font-medium text-slate-700">Şifre</label>
                <input id="password" name="password" type="password" required class="mt-0.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('password')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="text-sm font-medium text-slate-700">Şifre Tekrar</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-0.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div class="absolute -left-[9999px] h-0 overflow-hidden" aria-hidden="true">
                <label for="company">Şirket</label>
                <input id="company" name="company" type="text" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <label for="human_answer" class="text-sm font-medium text-slate-700">Güvenlik sorusu: {{ $challengeA }} + {{ $challengeB }} = ?</label>
                <input id="human_answer" name="human_answer" type="number" inputmode="numeric" required class="mt-0.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('human_answer')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                @error('company')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <label class="flex cursor-pointer items-start gap-2 text-xs leading-snug text-slate-600" data-consent-open="membership-dialog">
                <input type="checkbox" name="accept_membership" value="1" class="pointer-events-none mt-0.5" tabindex="-1" @checked(old('accept_membership')) required>
                <span>Üyelik sözleşmesini okudum, kabul ediyorum.</span>
            </label>
            @error('accept_membership')<div class="text-sm text-red-600">{{ $message }}</div>@enderror

            <label class="flex cursor-pointer items-start gap-2 text-xs leading-snug text-slate-600" data-consent-open="privacy-dialog">
                <input type="checkbox" name="accept_privacy" value="1" class="pointer-events-none mt-0.5" tabindex="-1" @checked(old('accept_privacy')) required>
                <span>Gizlilik ve KVKK aydınlatmasını okudum, kabul ediyorum.</span>
            </label>
            @error('accept_privacy')<div class="text-sm text-red-600">{{ $message }}</div>@enderror

            <button type="submit" class="w-full rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ücretsiz Başla</button>
        </form>

        <div id="membership-dialog" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="membership-dialog-title">
            <div class="w-full max-w-md rounded-2xl bg-white p-4 shadow-xl">
                <h2 id="membership-dialog-title" class="text-base font-bold text-slate-950">Üyelik Sözleşmesi</h2>
                <div class="mt-3 max-h-64 overflow-y-auto text-sm leading-relaxed text-slate-600">
                    @include('legal.content.membership')
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" data-consent-close class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Kapat</button>
                    <button type="button" data-consent-accept="accept_membership" class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Okudum, kabul ediyorum</button>
                </div>
            </div>
        </div>

        <div id="privacy-dialog" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="privacy-dialog-title">
            <div class="w-full max-w-md rounded-2xl bg-white p-4 shadow-xl">
                <h2 id="privacy-dialog-title" class="text-base font-bold text-slate-950">Gizlilik ve KVKK</h2>
                <div class="mt-3 max-h-64 overflow-y-auto text-sm leading-relaxed text-slate-600">
                    @include('legal.content.privacy')
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" data-consent-close class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Kapat</button>
                    <button type="button" data-consent-accept="accept_privacy" class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Okudum, kabul ediyorum</button>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const openDialog = function (id) {
                    const dialog = document.getElementById(id);
                    dialog.classList.remove('hidden');
                    dialog.classList.add('flex');
                };
                const closeDialog = function (dialog) {
                    dialog.classList.add('hidden');
                    dialog.classList.remove('flex');
                };
                document.querySelectorAll('[data-consent-open]').forEach(function (label) {
                    label.addEventListener('click', function (event) {
                        event.preventDefault();
                        openDialog(label.getAttribute('data-consent-open'));
                    });
                });
                document.querySelectorAll('[data-consent-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        closeDialog(button.closest('[role="dialog"]'));
                    });
                });
                document.querySelectorAll('[data-consent-accept]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        const input = document.querySelector('[name="' + button.getAttribute('data-consent-accept') + '"]');
                        input.checked = true;
                        closeDialog(button.closest('[role="dialog"]'));
                    });
                });
                document.querySelectorAll('[role="dialog"]').forEach(function (dialog) {
                    dialog.addEventListener('click', function (event) {
                        if (event.target === dialog) closeDialog(dialog);
                    });
                });
            })();
        </script>

        <p class="mt-3 text-center text-sm text-slate-500">
            Zaten hesabınız var mı?
            <a href="{{ route('login') }}" class="font-semibold text-slate-950">Giriş yapın</a>
        </p>
    </div>
@endsection
