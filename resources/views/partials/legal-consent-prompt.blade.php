@if (($needsMembershipConsent ?? false) || ($needsPrivacyConsent ?? false))
    <div id="legal-consent-prompt" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/70 p-4" role="dialog" aria-modal="true" aria-labelledby="legal-consent-prompt-title">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <h2 id="legal-consent-prompt-title" class="text-lg font-bold text-slate-950">Onayınız eksik</h2>
            <p class="mt-1 text-sm text-slate-500">Devam etmek için eksik metni okuyup kabul edin.</p>

            <form method="POST" action="{{ route('legal.membership.accept.store') }}" class="mt-4 space-y-3">
                @csrf
                @if ($needsMembershipConsent)
                    <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-600" data-legal-open="legal-membership-dialog">
                        <input type="checkbox" name="accept_membership" value="1" class="pointer-events-none mt-0.5" tabindex="-1" @checked(old('accept_membership')) required>
                        <span>Üyelik sözleşmesini okudum, kabul ediyorum.</span>
                    </label>
                    @error('accept_membership')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                @endif
                @if ($needsPrivacyConsent)
                    <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-600" data-legal-open="legal-privacy-dialog">
                        <input type="checkbox" name="accept_privacy" value="1" class="pointer-events-none mt-0.5" tabindex="-1" @checked(old('accept_privacy')) required>
                        <span>Gizlilik ve KVKK aydınlatmasını okudum, kabul ediyorum.</span>
                    </label>
                    @error('accept_privacy')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                @endif
                <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Kabul edip devam et</button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Çıkış yap</button>
            </form>
        </div>
    </div>

    @if ($needsMembershipConsent)
        <div id="legal-membership-dialog" class="fixed inset-0 z-[90] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="legal-membership-dialog-title">
            <div class="flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl bg-white p-4 shadow-xl">
                <h2 id="legal-membership-dialog-title" class="text-base font-bold text-slate-950">Üyelik Sözleşmesi</h2>
                <div class="mt-3 overflow-y-auto text-sm leading-relaxed text-slate-600">
                    @include('legal.content.membership')
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" data-legal-close class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Kapat</button>
                    <button type="button" data-legal-accept="accept_membership" class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Okudum, kabul ediyorum</button>
                </div>
            </div>
        </div>
    @endif

    @if ($needsPrivacyConsent)
        <div id="legal-privacy-dialog" class="fixed inset-0 z-[90] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="legal-privacy-dialog-title">
            <div class="flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl bg-white p-4 shadow-xl">
                <h2 id="legal-privacy-dialog-title" class="text-base font-bold text-slate-950">Gizlilik ve KVKK</h2>
                <div class="mt-3 overflow-y-auto text-sm leading-relaxed text-slate-600">
                    @include('legal.content.privacy')
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" data-legal-close class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Kapat</button>
                    <button type="button" data-legal-accept="accept_privacy" class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Okudum, kabul ediyorum</button>
                </div>
            </div>
        </div>
    @endif

    <script>
        (function () {
            document.querySelectorAll('[data-legal-open]').forEach(function (label) {
                label.addEventListener('click', function (event) {
                    event.preventDefault();
                    const dialog = document.getElementById(label.getAttribute('data-legal-open'));
                    dialog.classList.remove('hidden');
                    dialog.classList.add('flex');
                });
            });
            document.querySelectorAll('[data-legal-close]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const dialog = button.closest('[role="dialog"]');
                    dialog.classList.add('hidden');
                    dialog.classList.remove('flex');
                });
            });
            document.querySelectorAll('[data-legal-accept]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const input = document.querySelector('#legal-consent-prompt [name="' + button.getAttribute('data-legal-accept') + '"]');
                    if (input) input.checked = true;
                    const dialog = button.closest('[role="dialog"]');
                    dialog.classList.add('hidden');
                    dialog.classList.remove('flex');
                });
            });
        })();
    </script>
@endif
