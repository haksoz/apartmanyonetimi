<p class="{{ isset($inviteEmail) ? 'rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700' : 'text-xs text-slate-500' }}">{{ $inviteEmail ?? $user->email }} için Yeni davet bağlantısı hazır. Linki kopyalayıp kullanıcıya işlem yapması için iletebilirsiniz.</p>
<div class="mt-2 flex min-w-0 items-center gap-2">
    <input readonly value="{{ session('invite_url') }}" onclick="this.select()" aria-label="Davet bağlantısı" class="w-full min-w-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none">
    <button type="button" class="shrink-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" onclick="
        const input = this.previousElementSibling;
        const button = this;
        (async () => {
            try {
                if (navigator.clipboard &amp;&amp; window.isSecureContext) {
                    await navigator.clipboard.writeText(input.value);
                } else {
                    input.select();
                    if (!document.execCommand('copy')) throw new Error('copy');
                }
                button.textContent = 'Kopyalandı';
            } catch {
                input.select();
                button.textContent = 'Seçip kopyalayın';
            }
        })();
    ">Kopyala</button>
</div>
