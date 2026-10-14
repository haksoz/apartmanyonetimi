@extends('layouts.guest')

@section('title', 'Davet')

@section('content')
    <div class="w-full max-w-md rounded-2xl bg-white p-4 shadow-sm sm:p-6">
        <h1 class="text-xl font-bold text-slate-950">Hesabınızı açın</h1>
        @if (! $invitation)
            <p class="mt-3 text-sm text-slate-600">Davet bağlantısı geçersiz veya süresi dolmuş. Yeni bağlantı için apartman yöneticinize başvurun.</p>
        @else
            <p class="mt-1 text-sm text-slate-500">{{ $invitation->user->name }}, parolanızı belirleyerek AidatCep hesabınızı açın.</p>
            <form method="POST" action="{{ route('invite.accept', $token) }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label for="password" class="text-sm font-medium text-slate-700">Parola</label>
                    <input id="password" name="password" type="password" required class="mt-0.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('password')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="text-sm font-medium text-slate-700">Parola tekrar</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-0.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <label class="flex cursor-pointer items-start gap-2 text-xs leading-snug text-slate-600" data-consent-open="privacy-dialog">
                    <input type="checkbox" name="accept_privacy" value="1" class="pointer-events-none mt-0.5" tabindex="-1" @checked(old('accept_privacy')) required>
                    <span>Gizlilik ve KVKK aydınlatmasını okudum, kabul ediyorum.</span>
                </label>
                @error('accept_privacy')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                @error('token')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                <button type="submit" class="w-full rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Hesabı aç</button>
            </form>
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
                    document.querySelectorAll('[data-consent-open]').forEach(function (label) {
                        label.addEventListener('click', function (event) {
                            event.preventDefault();
                            const dialog = document.getElementById(label.getAttribute('data-consent-open'));
                            dialog.classList.remove('hidden');
                            dialog.classList.add('flex');
                        });
                    });
                    document.querySelectorAll('[data-consent-close]').forEach(function (button) {
                        button.addEventListener('click', function () {
                            const dialog = button.closest('[role="dialog"]');
                            dialog.classList.add('hidden');
                            dialog.classList.remove('flex');
                        });
                    });
                    document.querySelectorAll('[data-consent-accept]').forEach(function (button) {
                        button.addEventListener('click', function () {
                            document.querySelector('[name="' + button.getAttribute('data-consent-accept') + '"]').checked = true;
                            const dialog = button.closest('[role="dialog"]');
                            dialog.classList.add('hidden');
                            dialog.classList.remove('flex');
                        });
                    });
                })();
            </script>
        @endif
    </div>
@endsection
