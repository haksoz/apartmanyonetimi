@extends('layouts.guest')

@section('title', 'Üyelik Sözleşmesi')

@section('content')
    <div class="w-full max-w-md rounded-2xl bg-white p-4 shadow-sm sm:p-6">
        <h1 class="text-xl font-bold text-slate-950">Üyelik sözleşmesi</h1>
        <p class="mt-1 text-sm text-slate-500">Bu apartmanın yöneticisisiniz. Devam etmek için üyelik sözleşmesini okuyup kabul edin.</p>

        <form method="POST" action="{{ route('legal.membership.accept.store') }}" class="mt-4 space-y-3">
            @csrf
            <label class="flex cursor-pointer items-start gap-2 text-xs leading-snug text-slate-600" data-consent-open="membership-dialog">
                <input type="checkbox" name="accept_membership" value="1" class="pointer-events-none mt-0.5" tabindex="-1" @checked(old('accept_membership')) required>
                <span>Üyelik sözleşmesini okudum, kabul ediyorum.</span>
            </label>
            @error('accept_membership')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
            <button type="submit" class="w-full rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Kabul edip devam et</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button type="submit" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Çıkış yap</button>
        </form>
    </div>

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
@endsection
