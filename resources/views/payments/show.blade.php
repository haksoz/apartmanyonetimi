@extends('layouts.app')



@section('content')

    @php

        $isTahsilat = $payment->account && in_array($payment->account->type, [App\Models\Account::TYPE_OWNER, App\Models\Account::TYPE_TENANT]);

        $label = $isTahsilat ? 'Tahsilat' : 'Ödeme';

        $labelLower = $isTahsilat ? 'tahsilat' : 'ödeme';

        $labelAccusative = $isTahsilat ? 'Tahsilatı' : 'Ödemeyi';

        $hasDueAllocations = $payment->allocations->contains(fn ($allocation) => $allocation->due_id !== null);
        $linkedRecordsLabel = $hasDueAllocations ? 'aidatlar' : 'giderler';
        $linkedToLabel = $hasDueAllocations ? 'tahsilata' : 'ödemeye';

    @endphp

    {{-- Header --}}

    <div class="mb-6 flex flex-row items-center justify-between gap-4">

        <div>

            <h1 class="text-xl font-bold text-slate-950 lg:text-2xl">{{ $label }} Detayı</h1>

            @if ($payment->reference_number)
                <div class="mt-1">
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-3 py-1 text-sm font-semibold text-slate-600">Referans: {{ $payment->reference_number }}</span>
                </div>
            @endif

            @if ($payment->account)
                <div class="mt-1">
                    <a href="{{ route('accounts.show', $payment->account) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-3 py-1 text-sm font-semibold text-slate-600 hover:text-emerald-600 hover:bg-slate-100">
                        {{ $payment->account->name }}
                        @if ($payment->account->unit)
                            - Daire {{ str_pad($payment->account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}
                        @endif
                    </a>
                </div>
            @endif

        </div>

        {{-- Masaüstü butonlar --}}
        <div class="hidden lg:flex flex-wrap gap-2">

            @if ($payment->unallocated_amount > 0)

                <a href="{{ $isTahsilat ? route('payments.allocations.create', $payment) : route('payments.supplier-allocations.create', $payment) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Tahsis Et</a>

            @endif

            <a href="{{ route('payments.edit', $payment) }}" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">{{ $labelAccusative }} Düzenle</a>

            @if ($payment->allocations->isNotEmpty())

                <button type="button" onclick="document.getElementById('delete-payment-modal').classList.remove('hidden')" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ $labelAccusative }} Sil</button>

            @else

                <form method="POST" action="{{ route('payments.destroy', $payment) }}" onsubmit="return confirm('{{ $label }} kaydı silinsin mi? Bu işlem geri alınamaz.')">

                    @csrf

                    @method('DELETE')

                    <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ $labelAccusative }} Sil</button>

                </form>

            @endif

        </div>

        {{-- Mobil işlemler menüsü --}}
        <details class="lg:hidden relative group">
            <summary class="cursor-pointer list-none rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 flex items-center justify-end gap-2 focus:outline-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
                İşlem
            </summary>
            <div class="absolute right-0 top-full mt-2 w-56 rounded-2xl bg-white p-3 shadow-lg ring-1 ring-slate-100 flex flex-col gap-2 z-20">

                @if ($payment->unallocated_amount > 0)

                    <a href="{{ $isTahsilat ? route('payments.allocations.create', $payment) : route('payments.supplier-allocations.create', $payment) }}" class="block w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white text-left hover:bg-emerald-700">Tahsis Et</a>

                @endif

                <a href="{{ route('payments.edit', $payment) }}" class="block w-full rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white text-left hover:bg-amber-600">{{ $labelAccusative }} Düzenle</a>

                @if ($payment->allocations->isNotEmpty())

                    <button type="button" onclick="document.getElementById('delete-payment-modal').classList.remove('hidden')" class="block w-full rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white text-left hover:bg-red-700">{{ $labelAccusative }} Sil</button>

                @else

                    <form method="POST" action="{{ route('payments.destroy', $payment) }}" onsubmit="return confirm('{{ $label }} kaydı silinsin mi? Bu işlem geri alınamaz.')">

                        @csrf

                        @method('DELETE')

                        <button type="submit" class="block w-full rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white text-left hover:bg-red-700">{{ $labelAccusative }} Sil</button>

                    </form>

                @endif

            </div>
        </details>

    </div>



    {{-- Info Card --}}

    @php $cashTx = $payment->cashTransactions->first(); @endphp

    <div class="rounded-2xl bg-white shadow-sm mb-6 overflow-hidden">

        <div class="p-4 md:p-6 border-b border-slate-100">

            <div class="mb-5">
                <div class="text-xs text-slate-500 mb-1">Açıklama</div>
                <div class="text-2xl font-bold text-slate-900">{{ $payment->description ?: '-' }}</div>
            </div>

            <details class="group">
                <summary class="cursor-pointer list-none inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Detaylar
                </summary>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 text-sm">

                    @if ($payment->account)
                        <div class="flex items-center justify-between gap-2 md:col-span-2 md:justify-start">
                            <div class="text-xs text-slate-500 md:w-24 shrink-0">Hesap</div>
                            <div class="font-semibold text-slate-900 text-right md:text-left">
                                <a href="{{ route('accounts.show', $payment->account) }}" class="hover:text-emerald-600 hover:underline">{{ $payment->account->name }}</a>
                                @if ($payment->account->unit)
                                    - Daire {{ str_pad($payment->account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-between gap-2 md:justify-start">
                        <div class="text-xs text-slate-500 md:w-24 shrink-0">{{ $label }} Tarihi</div>
                        <div class="font-semibold text-slate-900 text-right md:text-left">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</div>
                    </div>

                    <div class="flex items-center justify-between gap-2 md:justify-start">
                        <div class="text-xs text-slate-500 md:w-24 shrink-0">Durum</div>
                        <div class="font-semibold text-slate-900 text-right md:text-left">
                            @if ($payment->unallocated_amount <= 0)
                                <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold bg-emerald-50 text-emerald-600 border border-emerald-200">Tam Tahsis</span>
                            @elseif ($payment->allocated_amount > 0)
                                <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold bg-amber-50 text-amber-600 border border-amber-200">Kısmen Tahsis</span>
                            @else
                                <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold bg-slate-50 text-slate-600 border border-slate-200">Tahsis Bekliyor</span>
                            @endif
                        </div>
                    </div>

                    @if ($payment->reference_number)
                        <div class="flex items-center justify-between gap-2 md:justify-start">
                            <div class="text-xs text-slate-500 md:w-24 shrink-0">Referans</div>
                            <div class="font-semibold text-slate-900 text-right md:text-left">{{ $payment->reference_number }}</div>
                        </div>
                    @endif

                    @if ($cashTx)
                        <div class="flex items-center justify-between gap-2 md:justify-start">
                            <div class="text-xs text-slate-500 md:w-24 shrink-0">Kasa Hareketi</div>
                            <div class="font-semibold text-slate-900 text-right md:text-left">
                                <a href="{{ route('cash.show', $cashTx) }}" class="text-blue-700 hover:text-blue-800 hover:underline">
                                    {{ $cashTx->reference_number }} — {{ $cashTx->cashBox?->name }}
                                </a>
                            </div>
                        </div>
                    @endif

                </div>
            </details>

        </div>

        {{-- Alt: Tutar / Bağlanan / Kalan Bakiye --}}
        <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-slate-100">
            <div class="py-3 px-4 md:p-5 flex items-center justify-between gap-1">
                <div class="text-[10px] md:text-xs font-semibold uppercase tracking-wide text-slate-400">Tutar</div>
                <div class="text-lg md:text-xl font-bold text-slate-900 tabular-nums">{{ number_format($payment->amount, 2, ',', '.') }} TL</div>
            </div>
            <div class="py-3 px-4 md:p-5 flex items-center justify-between gap-1">
                <div class="text-[10px] md:text-xs font-semibold uppercase tracking-wide text-slate-400">Bağlanan</div>
                <div class="text-lg md:text-xl font-bold text-emerald-600 tabular-nums">{{ number_format($payment->allocated_amount, 2, ',', '.') }} TL</div>
            </div>
            <div class="py-3 px-4 md:p-5 flex items-center justify-between gap-1">
                <div class="text-[10px] md:text-xs font-semibold uppercase tracking-wide text-slate-400">Kalan Bakiye</div>
                <div class="text-lg md:text-xl font-bold {{ $payment->unallocated_amount > 0 ? 'text-amber-600' : 'text-slate-400' }} tabular-nums">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</div>
            </div>
        </div>

    </div>



    {{-- Allocations Section --}}

    <div class="rounded-2xl bg-white p-4 md:p-6 shadow-sm mb-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
            <h2 class="text-lg font-semibold text-slate-950">{{ $isTahsilat ? 'Bağlı Aidatlar (Borçlar)' : 'Bağlı Giderler' }}</h2>

            <form id="bulk-destroy-allocations-form" method="POST" action="{{ route('payments.allocations.bulk-destroy', $payment) }}" class="md:hidden">
                @csrf
                @method('DELETE')
                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                <button type="submit" id="bulk-destroy-allocations-btn"
                    class="w-full rounded-xl px-4 py-2 text-sm font-semibold transition-colors bg-slate-200 text-slate-400 cursor-not-allowed" disabled
                    onclick="return confirm('Seçili tahsisler geri alınsın mı?')">
                    Seçilenleri Geri Al &mdash; <span id="selected-allocation-count">0</span> tahsis / <span id="selected-allocation-total">0,00</span> TL
                </button>
            </form>
        </div>

        @if ($payment->allocations->isEmpty())

            <div class="py-6 text-sm text-slate-500">Bu {{ $labelLower }} henüz herhangi bir borca tahsis edilmedi.</div>

        @else

            <div class="overflow-hidden rounded-xl border border-slate-200">

                <table class="hidden md:table min-w-full divide-y divide-slate-200 text-sm">

                    <thead class="bg-slate-50 text-left">

                        <tr>

                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Ref / No</th>

                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $isTahsilat ? 'Borç / Açıklama' : 'Gider / Açıklama' }}</th>

                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">Bağlanan Tutar</th>

                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</th>

                            <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500"></th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        @foreach ($payment->allocations as $allocation)

                            <tr class="hover:bg-slate-50 transition-colors">

                                @if ($allocation->due_id && $allocation->due)

                                    <td class="px-5 py-4">

                                        <a href="{{ route('dues.show', $allocation->due) }}" class="font-medium text-slate-900 hover:text-emerald-600">{{ $allocation->due->reference_number ?? '#'.$allocation->due->id }}</a>

                                    </td>

                                    <td class="px-5 py-4 text-slate-700">
                                        <span class="inline-block rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700 mr-1">Aidat</span>
                                        {{ $allocation->due->description ?: 'Aidat' }}
                                    </td>

                                    <td class="px-5 py-4 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($allocation->amount, 2, ',', '.') }} TL</td>

                                    <td class="px-5 py-4">

                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $allocation->due->computed_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($allocation->due->computed_status === 'partial' ? 'bg-amber-100 text-amber-700' : ($allocation->due->computed_status === 'overdue' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700')) }}">

                                            {{ $allocation->due->computed_status === 'paid' ? 'Ödendi' : ($allocation->due->computed_status === 'partial' ? 'Kısmen Ödendi' : ($allocation->due->computed_status === 'overdue' ? 'Gecikti' : 'Bekliyor')) }}

                                        </span>

                                    </td>

                                    <td class="px-5 py-4 text-right">
                                        <form method="POST" action="{{ route('payments.allocations.destroy', [$payment, $allocation]) }}" onsubmit="return confirm('Bu tahsis geri alınsın mı?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Geri Al</button>
                                        </form>
                                    </td>

                                @elseif ($allocation->expense_id && $allocation->expense)

                                    <td class="px-5 py-4">

                                        <a href="{{ route('expenses.show', $allocation->expense) }}" class="font-medium text-slate-900 hover:text-emerald-600">{{ $allocation->expense->reference_number ?? '#'.$allocation->expense->id }}</a>

                                    </td>

                                    <td class="px-5 py-4 text-slate-700">
                                        <span class="inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 mr-1">Gider</span>
                                        {{ $allocation->expense->description ?: ($allocation->expense->category ?? 'Gider') }}
                                    </td>

                                    <td class="px-5 py-4 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($allocation->amount, 2, ',', '.') }} TL</td>

                                    <td class="px-5 py-4">

                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $allocation->expense->is_paid ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">

                                            {{ $allocation->expense->is_paid ? 'Ödendi' : 'Açık' }}

                                        </span>

                                    </td>

                                    <td class="px-5 py-4 text-right">
                                        <form method="POST" action="{{ route('payments.allocations.destroy', [$payment, $allocation]) }}" onsubmit="return confirm('Bu tahsis geri alınsın mı?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Geri Al</button>
                                        </form>
                                    </td>

                                @endif

                            </tr>

                        @endforeach

                    </tbody>

                </table>

                {{-- Mobil: Bağlı Aidatlar/Giderler Kartları --}}
                <div class="md:hidden divide-y divide-slate-100">
                    @foreach ($payment->allocations as $allocation)
                        @if ($allocation->due_id && $allocation->due)
                            <div class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
                                <input type="checkbox" class="allocation-checkbox rounded shrink-0"
                                       name="allocation_ids[]"
                                       value="{{ $allocation->id }}"
                                       data-amount="{{ $allocation->amount }}"
                                       onclick="event.stopPropagation()">
                                <a href="{{ route('dues.show', $allocation->due) }}" class="flex-1 min-w-0 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-xs text-slate-500 mb-0.5">Aidat</div>
                                        <div class="text-sm text-slate-700 truncate">
                                            {{ $allocation->due->description ?: 'Aidat' }}
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-sm font-semibold text-slate-900">
                                        {{ number_format($allocation->amount, 2, ',', '.') }} TL
                                    </div>
                                </a>
                            </div>
                        @elseif ($allocation->expense_id && $allocation->expense)
                            <div class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
                                <input type="checkbox" class="allocation-checkbox rounded shrink-0"
                                       name="allocation_ids[]"
                                       value="{{ $allocation->id }}"
                                       data-amount="{{ $allocation->amount }}"
                                       onclick="event.stopPropagation()">
                                <a href="{{ route('expenses.show', $allocation->expense) }}" class="flex-1 min-w-0 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-xs text-slate-500 mb-0.5">Gider</div>
                                        <div class="text-sm text-slate-700 truncate">
                                            {{ $allocation->expense->description ?: ($allocation->expense->category ?? 'Gider') }}
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-sm font-semibold text-slate-900">
                                        {{ number_format($allocation->amount, 2, ',', '.') }} TL
                                    </div>
                                </a>
                            </div>
                        @endif
                    @endforeach
                </div>

            </div>

        @endif

    </div>

    @if ($payment->allocations->isNotEmpty())
        <div id="delete-payment-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900 mb-1">{{ $labelAccusative }} Sil</h3>
                <p class="text-sm text-slate-500 mb-4">Bu {{ $linkedToLabel }} bağlı {{ $linkedRecordsLabel }}:</p>

                <div class="overflow-hidden rounded-xl border border-slate-200 mb-4">
                    <div class="divide-y divide-slate-100">
                        @foreach ($payment->allocations as $allocation)
                            <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                                <div class="min-w-0">
                                    <div class="font-medium text-slate-900">
                                        @if ($allocation->due)
                                            {{ $allocation->due->reference_number ?? '#'.$allocation->due_id }}
                                        @else
                                            {{ $allocation->expense->reference_number ?? '#'.$allocation->expense_id }}
                                        @endif
                                    </div>
                                    <div class="truncate text-slate-500">
                                        @if ($allocation->due)
                                            {{ $allocation->due->description ?: 'Aidat' }}
                                        @else
                                            {{ $allocation->expense->description ?: 'Gider' }}
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0 font-semibold text-slate-900 tabular-nums">{{ number_format($allocation->amount, 2, ',', '.') }} TL</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 mb-4">
                    Bağlı {{ $hasDueAllocations ? 'aidatların tahsilat kapamaları' : 'giderlerin ödeme kapamaları' }} geri alınacak, ardından {{ $labelLower }} ve ilgili kasa hareketi silinecek. Bu işlem geri alınamaz.
                </div>

                <div class="flex gap-3">
                    <form method="POST" action="{{ route('payments.destroy', $payment) }}" class="flex-1">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700">{{ $labelAccusative }} Sil</button>
                    </form>
                    <button type="button" onclick="document.getElementById('delete-payment-modal').classList.add('hidden')" class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                </div>
            </div>
        </div>

        <script>
            document.getElementById('delete-payment-modal')?.addEventListener('click', function(e) {
                if (e.target === this) this.classList.add('hidden');
            });

            (function(){
                const btn = document.getElementById('bulk-destroy-allocations-btn');
                const countEl = document.getElementById('selected-allocation-count');
                const totalEl = document.getElementById('selected-allocation-total');
                if (!btn) return;

                const updateBtn = () => {
                    const checked = document.querySelectorAll('.allocation-checkbox:checked');
                    let total = 0;
                    checked.forEach(cb => total += parseFloat(cb.dataset.amount));
                    countEl.textContent = checked.length;
                    totalEl.textContent = total.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                    if (checked.length > 0) {
                        btn.disabled = false;
                        btn.classList.remove('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');
                        btn.classList.add('bg-red-600', 'text-white', 'hover:bg-red-700', 'cursor-pointer');
                    } else {
                        btn.disabled = true;
                        btn.classList.remove('bg-red-600', 'text-white', 'hover:bg-red-700', 'cursor-pointer');
                        btn.classList.add('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');
                    }
                };

                document.querySelectorAll('.allocation-checkbox').forEach(cb => {
                    cb.addEventListener('change', updateBtn);
                });
            })();
        </script>
    @endif

@endsection

