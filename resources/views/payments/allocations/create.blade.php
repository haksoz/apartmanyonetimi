@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">{{ $isSupplier ? 'Ödemeyi Giderlere Tahsis Et' : 'Ödemeyi Borçlara Tahsis Et' }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $isSupplier ? 'Bu ödemeyi tedarikçinin açık giderlerine tahsis edin.' : 'Bu ödemeyi hesabın açık kalan aidatlarına tahsis edin.' }}</p>
        </div>
        <a href="{{ $redirectTo ?? route('dues.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri Dön</a>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
        <div class="grid gap-4 lg:grid-cols-2">
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ödeme</h2>
                <p class="mt-2 text-sm text-slate-900">#{{ $payment->id }} - {{ $payment->description ?: 'Ödeme' }}</p>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tahsis Edilebilir Bakiye</h2>
                <p class="mt-2 text-sm font-semibold text-slate-900">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</p>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hesap</h2>
                <p class="mt-2 text-sm text-slate-900">{{ $payment->account?->name ?? '-' }}</p>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ödeme Tarihi</h2>
                <p class="mt-2 text-sm text-slate-900">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('payments.allocations.store', $payment) }}" class="rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        @if (!empty($redirectTo))
            <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
        @endif

        @if ($errors->has('allocations'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first('allocations') }}</div>
        @endif

        {{-- FIFO & Filtre Butonları --}}
        <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex gap-2">
                <button type="button" id="btn-fifo"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    ↑ Eskiden Yeniye Dağıt
                </button>
            </div>
            @if ($hasImportedDues)
                <div class="flex items-center gap-2">
                    <button type="button" id="toggle-imported" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Devir Öncesi Göster
                    </button>
                    <span id="imported-count" class="text-xs text-slate-500"></span>
                </div>
            @endif
        </div>

        @if ($isSupplier)
            {{-- Tedarikçi: Giderler --}}
            <div class="mb-4 overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Gider Tarihi</th>
                            <th class="px-5 py-3">Açıklama</th>
                            <th class="px-5 py-3 text-right">Tutar</th>
                            <th class="px-5 py-3 text-right">Tahsis Et</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($expenses as $index => $expense)
                            <tr>
                                <td class="px-5 py-4 text-slate-700">{{ $expense->expense_date?->format('d.m.Y') ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-700">
                                    {{ $expense->description ?: ($expense->category ?: 'Gider') }}
                                    @if($expense->categoryRelation)
                                        <span class="ml-1 text-xs text-slate-400">({{ $expense->categoryRelation->name }})</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right text-slate-900 font-semibold">{{ number_format($expense->amount, 2, ',', '.') }} TL</td>
                                <td class="px-5 py-4 text-right">
                                    <input type="hidden" name="expense_allocations[{{ $index }}][expense_id]" value="{{ $expense->id }}">
                                    <div class="flex items-center justify-end space-x-2">
                                        <input
                                            id="alloc-{{ $index }}"
                                            data-remaining="{{ $expense->amount }}"
                                            type="number"
                                            name="expense_allocations[{{ $index }}][amount]"
                                            min="0"
                                            step="0.01"
                                            max="{{ $expense->amount }}"
                                            value="{{ old('expense_allocations.'.$index.'.amount') }}"
                                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none"
                                        >
                                        <button type="button" data-fill-selector="#alloc-{{ $index }}" class="fill-remaining text-sm text-slate-700 hover:underline">Tamamını Doldur</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-6 text-sm text-slate-500">Bu tedarikçi için ödenmemiş gider bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            {{-- Normal hesap: Aidatlar --}}
            <div class="mb-4 overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Aidat</th>
                            <th class="px-5 py-3">Açıklama</th>
                            <th class="px-5 py-3 text-right">Kalan</th>
                            <th class="px-5 py-3 text-right">Tahsis Et</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($dues as $index => $due)
                            <tr data-imported="{{ $due->is_imported ? '1' : '0' }}">
                                <td class="px-5 py-4 text-slate-700">{{ $due->due_date?->format('d.m.Y') ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $due->description ?: 'Aidat' }}</td>
                                <td class="px-5 py-4 text-right text-slate-900 font-semibold">{{ number_format($due->remaining_amount, 2, ',', '.') }} TL</td>
                                <td class="px-5 py-4 text-right">
                                    <input type="hidden" name="allocations[{{ $index }}][due_id]" value="{{ $due->id }}">
                                    <div class="flex items-center justify-end space-x-2">
                                        <input
                                            id="alloc-{{ $index }}"
                                            data-remaining="{{ $due->remaining_amount }}"
                                            type="number"
                                            name="allocations[{{ $index }}][amount]"
                                            min="0"
                                            step="0.01"
                                            max="{{ $due->remaining_amount }}"
                                            value="{{ old('allocations.'.$index.'.amount') }}"
                                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none"
                                        >
                                        <button type="button" data-fill-selector="#alloc-{{ $index }}" class="fill-remaining text-sm text-slate-700 hover:underline">Tamamını Doldur</button>
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
            </div>
        @endif
        {{-- Live summary --}}
        <div id="alloc-summary" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm hidden">
            <div class="flex flex-wrap gap-x-8 gap-y-2">
                <div>
                    <span class="text-slate-500">Tahsis Edilecek:</span>
                    <span id="sum-allocated" class="ml-1 font-bold text-slate-900">0,00 TL</span>
                </div>
                <div>
                    <span class="text-slate-500">Tahsis Edilebilir Bakiye:</span>
                    <span class="ml-1 font-bold text-slate-900">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</span>
                </div>
                <div>
                    <span class="text-slate-500">Kalan:</span>
                    <span id="sum-remaining" class="ml-1 font-bold">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</span>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Tahsis Et</button>
        </div>

        <script>
            (function(){
                const budget = {{ $payment->unallocated_amount }};

                function toFloat(v){
                    const n = parseFloat(String(v).replace(',', '.'));
                    return Number.isFinite(n) ? n : 0;
                }

                function fmt(n){
                    return n.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' TL';
                }

                function updateSummary(){
                    let total = 0;
                    document.querySelectorAll('input[name$="[amount]"]').forEach(inp => {
                        total += toFloat(inp.value);
                    });

                    const summaryEl  = document.getElementById('alloc-summary');
                    const allocEl    = document.getElementById('sum-allocated');
                    const remainEl   = document.getElementById('sum-remaining');
                    const diff       = budget - total;

                    if (total > 0) {
                        summaryEl.classList.remove('hidden');
                    } else {
                        summaryEl.classList.add('hidden');
                    }

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
                    if (e.target.matches('input[name$="[amount]"]')) {
                        updateSummary();
                    }
                });

                document.addEventListener('click', function(e){
                    const btn = e.target.closest('[data-fill-selector]');
                    if(btn){
                        const sel = btn.getAttribute('data-fill-selector');
                        const input = document.querySelector(sel);
                        if(!input) return;
                        const remaining = toFloat(input.getAttribute('data-remaining'));
                        input.value = remaining ? remaining.toFixed(2) : '';
                        updateSummary();
                    }
                });

                // Toggle imported dues visibility
                const toggleBtn = document.getElementById('toggle-imported');
                const countSpan = document.getElementById('imported-count');
                if(toggleBtn){
                    let showImported = false;
                    const importedRows = document.querySelectorAll('tr[data-imported="1"]');
                    const normalRows = document.querySelectorAll('tr[data-imported="0"]');
                    const importedCount = importedRows.length;
                    const normalCount = normalRows.length;

                    // Initially hide imported rows
                    importedRows.forEach(row => row.style.display = 'none');
                    countSpan.textContent = `${importedCount} devir öncesi gizli`;

                    toggleBtn.addEventListener('click', function(){
                        showImported = !showImported;
                        importedRows.forEach(row => {
                            row.style.display = showImported ? '' : 'none';
                        });
                        toggleBtn.textContent = showImported ? 'Devir Öncesi Gizle' : 'Devir Öncesi Göster';
                        countSpan.textContent = showImported
                            ? `${importedCount} devir öncesi gösteriliyor, ${normalCount} normal`
                            : `${importedCount} devir öncesi gizli, ${normalCount} normal gösteriliyor`;
                    });
                }
                // FIFO: eskiden yeniye inputları doldur
                const fifoBtn = document.getElementById('btn-fifo');
                if (fifoBtn) {
                    fifoBtn.addEventListener('click', function(){
                        // Tüm amount inputlarını sıfırla
                        const inputs = Array.from(document.querySelectorAll('input[name$="[amount]"]'));
                        inputs.forEach(inp => { inp.value = ''; });

                        let remaining = budget;
                        for (const inp of inputs) {
                            if (remaining <= 0.001) break;
                            const max = toFloat(inp.getAttribute('data-remaining') || inp.getAttribute('max'));
                            if (max <= 0) continue;
                            const alloc = Math.min(remaining, max);
                            inp.value = alloc.toFixed(2);
                            remaining -= alloc;
                        }
                        updateSummary();
                    });
                }
            })();
        </script>
    </form>
@endsection
