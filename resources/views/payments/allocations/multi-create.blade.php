@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('accounts.index') }}" class="hover:text-slate-600">Hesaplar</a>
                <span>/</span>
                <a href="{{ route('accounts.show', $account) }}" class="hover:text-slate-600">{{ $account->type_label }}</a>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Tahsilattan Aidat Kapama</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $account->name }} &mdash; {{ $payments->count() }} tahsilat seçildi
            </p>
        </div>
        <a href="{{ route('accounts.show', $account) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri Dön</a>
    </div>

    {{-- Seçili Tahsilatlar --}}
    <div class="rounded-2xl bg-white p-4 md:p-6 shadow-sm mb-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-3">Seçili Tahsilatlar</h2>
        <div class="overflow-hidden rounded-xl border border-slate-200">
            <table class="hidden md:table min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Tarih</th>
                        <th class="px-5 py-3">Açıklama</th>
                        <th class="px-5 py-3 text-right">Dağıtılabilir Bakiye</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($payments as $payment)
                        <tr>
                            <td class="px-5 py-3 text-slate-700">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</td>
                            <td class="px-5 py-3 text-slate-700">{{ $payment->description ?: 'Ödeme' }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-slate-900">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50">
                    <tr>
                        <td colspan="2" class="px-5 py-3 text-sm font-semibold text-slate-700">Toplam Bakiye</td>
                        <td class="px-5 py-3 text-right text-sm font-bold text-slate-900">{{ number_format($totalBudget, 2, ',', '.') }} TL</td>
                    </tr>
                </tfoot>
            </table>

            {{-- Mobil: Seçili Tahsilatlar Kartları --}}
            <div class="md:hidden divide-y divide-slate-100">
                @foreach ($payments as $payment)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <div class="text-sm text-slate-700 truncate">{{ $payment->description ?: 'Ödeme' }}</div>
                            <div class="text-xs text-slate-500">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</div>
                        </div>
                        <div class="shrink-0 text-sm font-semibold text-slate-900">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</div>
                    </div>
                @endforeach
                <div class="flex items-center justify-between gap-3 px-4 py-3 bg-slate-50">
                    <div class="text-sm font-semibold text-slate-700">Toplam Bakiye</div>
                    <div class="text-sm font-bold text-slate-900">{{ number_format($totalBudget, 2, ',', '.') }} TL</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tahsis Formu --}}
    <form method="POST" action="{{ route('accounts.payments.multi-allocate.store', $account) }}" class="rounded-2xl bg-white p-4 md:p-6 shadow-sm">
        @csrf
        <input type="hidden" name="payment_ids" value="{{ $payments->pluck('id')->join(',') }}">

        @if ($errors->has('allocations'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first('allocations') }}</div>
        @endif

        <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <button type="button" id="btn-fifo" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Eskiden Yeniye Bakiye Dağıt
                </button>
                <button type="button" id="btn-clear" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Temizle
                </button>
            </div>
            @if ($hasImportedDues)
                <div class="flex items-center gap-3">
                    <button type="button" id="toggle-imported" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        İçe Aktarılanları Göster
                    </button>
                    <span id="imported-count" class="text-xs text-slate-500"></span>
                </div>
            @endif
        </div>

        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Açık Aidatlar</h3>

        <div class="mb-4 overflow-hidden rounded-2xl border border-slate-200">
            <table class="hidden md:table min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Tarih</th>
                        <th class="px-5 py-3">Açıklama</th>
                        <th class="px-5 py-3 text-right">Kalan</th>
                        <th class="px-5 py-3 text-right">Tutar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($dues as $index => $due)
                        <tr data-imported="{{ $due->is_imported ? '1' : '0' }}">
                            <td class="px-5 py-4 text-slate-700">{{ $due->due_date?->format('d.m.Y') ?? '-' }}</td>
                            <td class="px-5 py-4 text-slate-700">
                                {{ $due->description ?: 'Aidat' }}
                                @if ($due->is_imported)
                                    <span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">İçe Aktarma</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right text-slate-900 font-semibold">{{ number_format($payable[$due->id], 2, ',', '.') }} TL</td>
                            <td class="px-5 py-4 text-right">
                                <input type="hidden" name="allocations[{{ $index }}][due_id]" value="{{ $due->id }}">
                                <div class="flex items-center justify-end space-x-2">
                                    <input
                                        id="alloc-{{ $index }}"
                                        data-remaining="{{ $payable[$due->id] }}"
                                        type="number"
                                        name="allocations[{{ $index }}][amount]"
                                        min="0"
                                        step="0.01"
                                        max="{{ $payable[$due->id] }}"
                                        value="{{ old('allocations.'.$index.'.amount') }}"
                                        class="desktop-alloc-input w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none"
                                    >
                                    <button type="button" data-fill-selector="#alloc-{{ $index }}" class="fill-remaining text-sm text-slate-700 hover:underline whitespace-nowrap">Tam Doldur</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-6 text-sm text-slate-500">Bu hesap için eşlenmemiş açık aidat bulunamadı.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Mobil: Açık Aidatlar Kartları --}}
            <div class="md:hidden divide-y divide-slate-100">
                @forelse ($dues as $index => $due)
                    <div class="px-4 py-3" data-imported="{{ $due->is_imported ? '1' : '0' }}">
                        <div class="mb-2">
                            <div class="text-sm text-slate-700">
                                {{ $due->description ?: 'Aidat' }}
                                @if ($due->is_imported)
                                    <span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">İçe Aktarma</span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-500">{{ $due->due_date?->format('d.m.Y') ?? '-' }} &mdash; Kalan: <span class="font-semibold text-slate-700">{{ number_format($payable[$due->id], 2, ',', '.') }} TL</span></div>
                        </div>
                        <input type="hidden" name="allocations[{{ $index }}][due_id]" value="{{ $due->id }}">
                        <div class="flex items-center gap-2">
                            <input
                                id="alloc-mobile-{{ $index }}"
                                data-remaining="{{ $payable[$due->id] }}"
                                type="number"
                                name="allocations[{{ $index }}][amount]"
                                min="0"
                                step="0.01"
                                max="{{ $payable[$due->id] }}"
                                value="{{ old('allocations.'.$index.'.amount') }}"
                                class="mobile-alloc-input flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none"
                                placeholder="Tutar"
                            >
                            <button type="button" data-fill-selector="#alloc-mobile-{{ $index }}" class="fill-remaining text-sm text-slate-700 hover:underline whitespace-nowrap">Tam Doldur</button>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-6 text-sm text-slate-500">Bu hesap için eşlenmemiş açık aidat bulunamadı.</div>
                @endforelse
            </div>

        </div>

        {{-- Canlı özet --}}
        <div id="alloc-summary" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm hidden">
            <div class="flex flex-wrap gap-x-8 gap-y-2">
                <div>
                    <span class="text-slate-500">Kapatılacak Bakiye:</span>
                    <span id="sum-allocated" class="ml-1 font-bold text-slate-900">0,00 TL</span>
                </div>
                <div>
                    <span class="text-slate-500">Toplam Bakiye:</span>
                    <span class="ml-1 font-bold text-slate-900">{{ number_format($totalBudget, 2, ',', '.') }} TL</span>
                </div>
                <div>
                    <span class="text-slate-500">Kalan:</span>
                    <span id="sum-remaining" class="ml-1 font-bold">{{ number_format($totalBudget, 2, ',', '.') }} TL</span>
                </div>
            </div>
        </div>

        <div class="mt-4">
            @if ($history)
                <button type="button" id="preview-allocations" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold">Önizle</button>
                <input type="hidden" name="preview_hash" id="allocation-preview-hash">
            @endif
            <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Aidat Kapat &mdash; Kaydet</button>
        </div>

        @if ($history)
            <section id="allocation-preview" hidden aria-live="polite" class="mt-4 overflow-x-auto rounded-xl border border-slate-200 p-3 text-sm"></section>
        @endif

        <script>
            (function(){
                const budget = {{ $totalBudget }};

                function toFloat(v){
                    const n = parseFloat(String(v).replace(',', '.'));
                    return Number.isFinite(n) ? n : 0;
                }

                function fmt(n){
                    return n.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' TL';
                }

                function updateSummary(){
                    let total = 0;
                    document.querySelectorAll('input[name^="allocations"][name$="[amount]"]:not(:disabled)').forEach(inp => {
                        total += toFloat(inp.value);
                    });

                    const summaryEl = document.getElementById('alloc-summary');
                    const allocEl   = document.getElementById('sum-allocated');
                    const remainEl  = document.getElementById('sum-remaining');
                    const diff      = budget - total;

                    summaryEl.classList.toggle('hidden', total <= 0);
                    allocEl.textContent  = fmt(total);
                    remainEl.textContent = fmt(diff);

                    if (diff < -0.001) {
                        remainEl.classList.remove('text-slate-900', 'text-emerald-600');
                        remainEl.classList.add('text-red-600');
                    } else if (diff < 0.001) {
                        remainEl.classList.remove('text-slate-900', 'text-red-600');
                        remainEl.classList.add('text-emerald-600');
                    } else {
                        remainEl.classList.remove('text-red-600', 'text-emerald-600');
                        remainEl.classList.add('text-slate-900');
                    }
                }

                document.addEventListener('input', function(e){
                    if (e.target.matches('input[name^="allocations"][name$="[amount]"]:not(:disabled)')) {
                        updateSummary();
                    }
                });

                function allocatedTotal(excludeInput){
                    let total = 0;
                    document.querySelectorAll('input[name^="allocations"][name$="[amount]"]:not(:disabled)').forEach(inp => {
                        if (inp !== excludeInput) total += toFloat(inp.value);
                    });
                    return total;
                }

                document.addEventListener('click', function(e){
                    const btn = e.target.closest('[data-fill-selector]');
                    if (btn) {
                        const sel   = btn.getAttribute('data-fill-selector');
                        const input = document.querySelector(sel);
                        if (!input) return;
                        const remaining = toFloat(input.getAttribute('data-remaining'));
                        const available = Math.max(0, budget - allocatedTotal(input));
                        const fill = Math.min(remaining, available);
                        input.value = fill > 0 ? fill.toFixed(2) : '';
                        updateSummary();
                    }
                });

                // FIFO: eskiden yeniye otomatik dağıt
                document.getElementById('btn-fifo').addEventListener('click', function(){
                    const inputs = Array.from(document.querySelectorAll('input[name^="allocations"][name$="[amount]"]:not(:disabled)'));
                    inputs.forEach(inp => inp.value = '');

                    let remaining = budget;
                    inputs.forEach(inp => {
                        if (remaining <= 0) { inp.value = ''; return; }
                        const maxAlloc = toFloat(inp.getAttribute('data-remaining'));
                        const alloc    = Math.min(remaining, maxAlloc);
                        inp.value      = alloc > 0 ? alloc.toFixed(2) : '';
                        remaining      = Math.round((remaining - alloc) * 100) / 100;
                    });

                    updateSummary();
                });

                // Temizle
                document.getElementById('btn-clear').addEventListener('click', function(){
                    document.querySelectorAll('input[name^="allocations"][name$="[amount]"]:not(:disabled)').forEach(inp => inp.value = '');
                    updateSummary();
                });

                // İçe Aktarma toggle
                const toggleBtn = document.getElementById('toggle-imported');
                const countSpan = document.getElementById('imported-count');
                if (toggleBtn) {
                    let showImported = false;
                    const importedRows = document.querySelectorAll('[data-imported="1"]');
                    const importedCount = importedRows.length;

                    importedRows.forEach(row => row.style.display = 'none');
                    countSpan.textContent = `${importedCount} içe aktarma kaydı gizli`;

                    toggleBtn.addEventListener('click', function(){
                        showImported = !showImported;
                        importedRows.forEach(row => row.style.display = showImported ? '' : 'none');
                        toggleBtn.textContent  = showImported ? 'İçe Aktarılanları Gizle' : 'İçe Aktarılanları Göster';
                        countSpan.textContent  = showImported ? `${importedCount} içe aktarma kaydı gösteriliyor` : `${importedCount} içe aktarma kaydı gizli`;
                    });
                }

                function toggleInputsByViewport(){
                    const isDesktop = window.innerWidth >= 768;
                    document.querySelectorAll('.desktop-alloc-input').forEach(i => i.disabled = !isDesktop);
                    document.querySelectorAll('.mobile-alloc-input').forEach(i => i.disabled = isDesktop);
                }

                const previewButton = document.getElementById('preview-allocations');
                if (previewButton) {
                    const form = previewButton.form;
                    const submit = form.querySelector('[type="submit"]');
                    const panel = document.getElementById('allocation-preview');
                    const hashInput = document.getElementById('allocation-preview-hash');
                    let version = 0;
                    const clear = () => {
                        version++;
                        hashInput.value = '';
                        submit.disabled = true;
                        panel.hidden = true;
                        panel.textContent = '';
                    };
                    const read = () => Array.from(form.querySelectorAll('input[name^="allocations"][name$="[amount]"]:not(:disabled)'))
                        .filter(input => toFloat(input.value) > 0)
                        .map(input => ({
                            due_id: form.querySelector('[name="' + input.name.replace('[amount]', '[due_id]') + '"]').value,
                            amount: input.value,
                        }));
                    clear();
                    form.addEventListener('input', clear);
                    form.addEventListener('click', event => {
                        if (event.target.closest('#btn-fifo, #btn-clear, [data-fill-selector]')) clear();
                    });
                    window.addEventListener('resize', clear);
                    previewButton.addEventListener('click', async () => {
                        clear();
                        const current = version;
                        panel.hidden = false;
                        const allocations = read();
                        if (!allocations.length) {
                            panel.textContent = 'Önizleme için tahsis tutarı girin.';
                            return;
                        }
                        panel.textContent = 'Önizleme hesaplanıyor…';
                        try {
                            const response = await fetch(@json(route('accounts.payments.multi-allocate.preview', $account)), {
                                method: 'POST',
                                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements._token.value},
                                body: JSON.stringify({payment_ids: form.elements.payment_ids.value.split(','), allocations}),
                            });
                            const data = await response.json();
                            if (current !== version) return;
                            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message);
                            const table = document.createElement('table');
                            table.className = 'w-full text-sm';
                            const header = table.createTHead().insertRow();
                            ['Ödeme', 'Tahsis', 'Ana para', 'Gecikme Tazminatı', 'Tahsis edilmemiş'].forEach(value => {
                                const cell = document.createElement('th');
                                cell.scope = 'col';
                                cell.className = 'px-2 py-1 text-left';
                                cell.textContent = value;
                                header.append(cell);
                            });
                            const body = table.createTBody();
                            data.payments.forEach(payment => {
                                const row = body.insertRow();
                                [payment.date + ' ' + (payment.reference || ''), ...['amount', 'principal', 'compensation', 'unallocated'].map(key => fmt(payment[key] / 100))]
                                    .forEach(value => {
                                        const cell = row.insertCell();
                                        cell.className = 'px-2 py-1';
                                        cell.textContent = value;
                                    });
                            });
                            panel.replaceChildren(table);
                            hashInput.value = data.hash;
                            submit.disabled = false;
                        } catch (error) {
                            if (current === version) panel.textContent = error.message || 'Önizleme hesaplanamadı.';
                        }
                    });
                }

                window.addEventListener('resize', toggleInputsByViewport);
                toggleInputsByViewport();
            })();
        </script>
    </form>
@endsection
