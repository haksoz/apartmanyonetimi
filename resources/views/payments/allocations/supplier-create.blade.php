@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('accounts.index') }}" class="hover:text-slate-600">Hesaplar</a>
                <span>/</span>
                <span>{{ $payment->account?->type_label ?? '' }}</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Ödemeyi Açık Giderlere Bağla</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $payment->account?->name ?? '-' }}
                @if ($payment->account?->unit)
                    — Daire No: {{ str_pad($payment->account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}
                @endif
            </p>
        </div>
        <a href="{{ route('accounts.show', $payment->account_id) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri Dön</a>
    </div>

    {{-- Ödeme Özeti --}}
    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Ödeme Tutarı</div>
            <div class="mt-2 text-xl font-bold text-slate-900">{{ number_format($payment->amount, 2, ',', '.') }} TL</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Tahsis Edilmiş</div>
            <div class="mt-2 text-xl font-bold text-slate-900">{{ number_format($payment->amount - $payment->unallocated_amount, 2, ',', '.') }} TL</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Dağıtılabilir Bakiye</div>
            <div class="mt-2 text-xl font-bold text-emerald-600">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</div>
        </div>
    </div>

    <form method="POST" action="{{ route('payments.supplier-allocations.store', $payment) }}" class="rounded-2xl bg-white p-6 shadow-sm">
        @csrf

        @if ($errors->has('allocations'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first('allocations') }}</div>
        @endif

        <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <button type="button" id="btn-fifo" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Eskiden Yeniye Otomatik Dağıt (FIFO)
                </button>
                <button type="button" id="btn-clear" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Temizle
                </button>
            </div>
            @if ($hasImportedExpenses)
                <div class="flex items-center gap-3">
                    <button type="button" id="toggle-imported" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Devir Öncesi Göster
                    </button>
                    <span id="imported-count" class="text-xs text-slate-500"></span>
                </div>
            @endif
        </div>

        <div class="mb-4 overflow-hidden rounded-2xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Tarih</th>
                        <th class="px-5 py-3">Açıklama</th>
                        <th class="px-5 py-3 text-right">Kalan</th>
                        <th class="px-5 py-3 text-right">Tahsis Et</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($expenses as $index => $expense)
                        <tr data-imported="{{ $expense->is_imported ? '1' : '0' }}">
                            <td class="px-5 py-4 text-slate-700">{{ $expense->expense_date?->format('d.m.Y') ?? ($expense->period_month?->format('d.m.Y') ?? '-') }}</td>
                            <td class="px-5 py-4 text-slate-700">
                                {{ $expense->description ?: $expense->category }}
                                @if ($expense->is_imported)
                                    <span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right text-slate-900 font-semibold">{{ number_format($expense->remaining_amount, 2, ',', '.') }} TL</td>
                            <td class="px-5 py-4 text-right">
                                <input type="hidden" name="allocations[{{ $index }}][expense_id]" value="{{ $expense->id }}">
                                <div class="flex items-center justify-end space-x-2">
                                    <input
                                        id="alloc-{{ $index }}"
                                        data-remaining="{{ $expense->remaining_amount }}"
                                        type="number"
                                        name="allocations[{{ $index }}][amount]"
                                        min="0"
                                        step="0.01"
                                        max="{{ $expense->remaining_amount }}"
                                        value="{{ old('allocations.'.$index.'.amount') }}"
                                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none"
                                    >
                                    <button type="button" data-fill-selector="#alloc-{{ $index }}" class="text-sm text-slate-700 hover:underline whitespace-nowrap">Tamamını Doldur</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-6 text-sm text-slate-500">Bu tedarikçi için tahsis bekleyen açık gider bulunamadı.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Canlı özet --}}
        <div id="alloc-summary" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm hidden">
            <div class="flex flex-wrap gap-x-8 gap-y-2">
                <div>
                    <span class="text-slate-500">Tahsis Edilecek:</span>
                    <span id="sum-allocated" class="ml-1 font-bold text-slate-900">0,00 TL</span>
                </div>
                <div>
                    <span class="text-slate-500">Dağıtılabilir Bakiye:</span>
                    <span class="ml-1 font-bold text-slate-900">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</span>
                </div>
                <div>
                    <span class="text-slate-500">Kalan:</span>
                    <span id="sum-remaining" class="ml-1 font-bold text-slate-900">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</span>
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
                    document.querySelectorAll('input[name^="allocations"][name$="[amount]"]').forEach(inp => {
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
                    if (e.target.matches('input[name^="allocations"][name$="[amount]"]')) {
                        updateSummary();
                    }
                });

                document.addEventListener('click', function(e){
                    const btn = e.target.closest('[data-fill-selector]');
                    if (btn) {
                        const sel   = btn.getAttribute('data-fill-selector');
                        const input = document.querySelector(sel);
                        if (!input) return;
                        const remaining = toFloat(input.getAttribute('data-remaining'));
                        input.value = remaining ? remaining.toFixed(2) : '';
                        updateSummary();
                    }
                });

                // FIFO
                document.getElementById('btn-fifo').addEventListener('click', function(){
                    const inputs = Array.from(document.querySelectorAll('input[name^="allocations"][name$="[amount]"]'));
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
                    document.querySelectorAll('input[name^="allocations"][name$="[amount]"]').forEach(inp => inp.value = '');
                    updateSummary();
                });

                // Devir Öncesi toggle
                const toggleBtn = document.getElementById('toggle-imported');
                const countSpan = document.getElementById('imported-count');
                if (toggleBtn) {
                    let showImported = false;
                    const importedRows  = document.querySelectorAll('tr[data-imported="1"]');
                    const importedCount = importedRows.length;

                    importedRows.forEach(row => row.style.display = 'none');
                    countSpan.textContent = `${importedCount} devir öncesi gizli`;

                    toggleBtn.addEventListener('click', function(){
                        showImported = !showImported;
                        importedRows.forEach(row => row.style.display = showImported ? '' : 'none');
                        toggleBtn.textContent = showImported ? 'Devir Öncesi Gizle' : 'Devir Öncesi Göster';
                        countSpan.textContent = showImported ? `${importedCount} devir öncesi gösteriliyor` : `${importedCount} devir öncesi gizli`;
                    });
                }
            })();
        </script>
    </form>
@endsection
