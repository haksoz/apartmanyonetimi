<dialog id="expense-type-modal" aria-labelledby="expense-type-title" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl border-0 bg-white p-5 text-slate-900 shadow-xl backdrop:bg-slate-950/50">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 id="expense-type-title" class="text-lg font-bold text-slate-950">Gider Türünü Seçin</h2>
        <button type="button" id="expense-type-cancel" aria-label="Kapat" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">✕</button>
    </div>
    <form method="GET" action="{{ route('expenses.create') }}" class="space-y-2">
        <input type="hidden" name="account_id" id="expense-type-account" disabled>
        @foreach ([
            'aidat' => ['Ortak Kullanım Gideri', 'Temizlik, elektrik, su vb. ortak kullanım ve düzenli işletim giderleri.'],
            'malik_payi' => ['Kat Maliki Gideri', 'Demirbaş, yenileme vb. kat maliklerinin karşılayacağı giderler.'],
        ] as $value => [$label, $description])
            <button type="submit" name="expense_relation" value="{{ $value }}" class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-left hover:border-emerald-400 hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                <span class="block text-sm font-semibold">{{ $label }}</span>
                <span class="mt-1 block text-xs leading-relaxed text-slate-500">{{ $description }}</span>
            </button>
        @endforeach
    </form>
</dialog>
<script>
    (() => {
        const modal = document.getElementById('expense-type-modal');
        const form = modal.querySelector('form');
        const account = document.getElementById('expense-type-account');
        const createUrl = new URL(@json(route('expenses.create')), window.location.origin);
        const open = (url) => {
            form.reset();
            account.value = url.searchParams.get('account_id') || '';
            account.disabled = !account.value;
            modal.showModal();
        };
        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[href]');
            if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
            const url = new URL(link.href, window.location.origin);
            if (url.origin !== createUrl.origin || url.pathname !== createUrl.pathname || url.searchParams.has('expense_relation')) return;
            event.preventDefault();
            open(url);
        });
        document.getElementById('expense-type-cancel').addEventListener('click', () => modal.close());
        window.addEventListener('pageshow', (event) => {
            if (event.persisted && modal.open) modal.close();
        });
        modal.addEventListener('click', (event) => {
            if (event.target !== modal) return;
            const bounds = modal.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) modal.close();
        });
        @if (request()->routeIs('expenses.create') && !isset($selectedRelation))
            open(new URL(window.location.href));
        @endif
    })();
</script>
