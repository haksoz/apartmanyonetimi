<dialog id="batch-method-dialog" aria-labelledby="batch-method-title" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl border-0 bg-white p-5 text-slate-900 shadow-xl backdrop:bg-slate-950/50">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 id="batch-method-title" class="text-lg font-bold">Toplu Borçlandır</h2>
        <button type="button" onclick="this.closest('dialog').close()" aria-label="Kapat" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">✕</button>
    </div>
    <p class="mb-4 text-sm text-slate-500">Borçlandırma yöntemini seçin.</p>
    <div class="space-y-2">
        @foreach (['fixed' => ['Sabit Tutar', 'Her daireye aynı tutarı uygula'], 'manual' => ['Tutardan Hesapla', 'Toplam tutarı dairelere dağıt'], 'expenses' => ['Giderden Hesapla', 'Dönem giderlerini dairelere dağıt']] as $method => [$title, $description])
            <a href="{{ route('dues.batch.create', ['source_type' => $method]) }}" onclick="this.closest('dialog').close()" class="block rounded-xl border border-slate-200 px-4 py-3 hover:border-emerald-400 hover:bg-emerald-50">
                <span class="block text-sm font-semibold">{{ $title }}</span>
                <span class="mt-1 block text-xs text-slate-500">{{ $description }}</span>
            </a>
        @endforeach
    </div>
</dialog>
<script>
    window.addEventListener('pageshow', () => {
        const dialog = document.getElementById('batch-method-dialog');
        if (dialog.open) dialog.close();
    });
</script>
