<p class="text-xs text-slate-500">{{ $user->email }} için Yeni davet bağlantısı hazır. Linki kopyalayıp kullanıcıya işlem yapması için iletebilirsiniz.</p>
<div class="mt-2 flex min-w-0 items-center gap-2">
    <input readonly value="{{ session('invite_url') }}" onclick="this.select()" aria-label="Davet bağlantısı" class="w-full min-w-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none">
    <button type="button" class="shrink-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" onclick="const input = this.previousElementSibling; navigator.clipboard.writeText(input.value); this.textContent = 'Kopyalandı';">Kopyala</button>
</div>
