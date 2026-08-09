@extends('layouts.app')



@section('content')

    @php

        $expensePayment = $expense->paymentAllocations->first()?->payment;

        $paymentTx = $expensePayment

            ? \App\Models\AccountTransaction::where('transactionable_type', \App\Models\Payment::class)

                ->where('transactionable_id', $expensePayment->id)

                ->where('type', 'debit')

                ->first()

            : $expense->transactions->firstWhere('type', 'debit');

        $months = ['January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart', 'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',

                   'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül', 'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'];

        $periodText = $expense->period_month ? $expense->period_month->format('F Y') : null;

        if ($periodText) { foreach ($months as $en => $tr) { $periodText = str_replace($en, $tr, $periodText); } }

    @endphp



    {{-- Header --}}

    <div class="mb-6 flex flex-row items-center justify-between gap-4">

        <div>

            <h1 class="text-xl font-bold text-slate-950 lg:text-2xl">Gider Detayı</h1>

            @if ($expense->reference_number)
                <div class="mt-1 text-sm text-slate-500">{{ $expense->reference_number }}</div>
            @endif

            @if ($expense->account)
                <div class="mt-1 text-sm text-slate-500">
                    <a href="{{ route('accounts.show', $expense->account) }}" class="hover:text-emerald-600 hover:underline">{{ $expense->account->name }}</a>
                </div>
            @endif

        </div>

        <div class="flex items-center gap-2">
            <button type="button" onclick="history.back()" aria-label="Geri dön"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full overflow-hidden transition-transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-slate-300">
                <img src="{{ asset('images/back-button.png') }}" srcset="{{ asset('images/back-button@2x.png') }} 2x" alt="" class="h-10 w-10 object-cover" aria-hidden="true">
            </button>

            <div class="hidden lg:flex flex-wrap items-center gap-2">
                @unless ($expense->is_paid)
                    <a href="{{ route('expenses.payment.create', $expense) }}" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Ödeme Ekle</a>
                @endunless

                <a href="{{ route('expenses.edit', $expense) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>

                @if ($expense->paymentAllocations->isNotEmpty())
                    <button type="button" onclick="document.getElementById('delete-expense-modal').classList.remove('hidden')" class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Sil</button>
                @else
                    <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('Gider kaydı silinsin mi?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Sil</button>
                    </form>
                @endif
            </div>

            <details class="lg:hidden relative group">
                <summary class="cursor-pointer list-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 flex items-center justify-end gap-2 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    İşlem
                </summary>
                <div class="absolute right-0 top-full mt-2 w-56 rounded-2xl bg-white p-3 shadow-lg ring-1 ring-slate-100 flex flex-col gap-2 z-20">

                    @unless ($expense->is_paid)

                        <a href="{{ route('expenses.payment.create', $expense) }}" class="block w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white text-left hover:bg-emerald-700">Ödeme Ekle</a>

                    @endunless

                    <a href="{{ route('expenses.edit', $expense) }}" class="block w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-left hover:bg-slate-50">Düzenle</a>

                    @if ($expense->paymentAllocations->isNotEmpty())

                        <button type="button" onclick="document.getElementById('delete-expense-modal').classList.remove('hidden')" class="block w-full rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 text-left hover:bg-red-50">Sil</button>

                    @else

                        <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('Gider kaydı silinsin mi?')">

                            @csrf

                            @method('DELETE')

                            <button type="submit" class="block w-full rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 text-left hover:bg-red-50">Sil</button>

                        </form>

                    @endif

                </div>
            </details>
        </div>

    </div>



    {{-- Info Card --}}

    <div class="rounded-2xl bg-white shadow-sm mb-6 overflow-hidden">

        <div class="p-4 md:p-6 border-b border-slate-100">

            <div class="mb-5">
                <div class="text-xs text-slate-500 mb-1">Açıklama</div>
                <div class="text-2xl font-bold text-slate-900">{{ $expense->description ?: 'Gider' }}</div>
            </div>

            <details class="group">
                <summary class="cursor-pointer list-none inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Detaylar
                </summary>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 text-sm">

                    @if ($expense->account)
                        <div class="flex items-center justify-between gap-2 md:col-span-2 md:justify-start">
                            <div class="text-xs text-slate-500 md:w-24 shrink-0">Hesap / Tedarikçi</div>
                            <div class="font-semibold text-slate-900 text-right md:text-left">
                                <a href="{{ route('accounts.show', $expense->account) }}" class="hover:text-emerald-600 hover:underline">{{ $expense->account->name }}</a>
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-between gap-2 md:justify-start">
                        <div class="text-xs text-slate-500 md:w-24 shrink-0">Gider Tarihi</div>
                        <div class="font-semibold text-slate-900 text-right md:text-left">{{ $expense->expense_date->format('d.m.Y') }}</div>
                    </div>

                    <div class="flex items-center justify-between gap-2 md:justify-start">
                        <div class="text-xs text-slate-500 md:w-24 shrink-0">Son Ödeme</div>
                        <div class="font-semibold text-slate-900 text-right md:text-left">{{ $expense->due_date?->format('d.m.Y') ?? '-' }}</div>
                    </div>

                    <div class="flex items-center justify-between gap-2 md:justify-start">
                        <div class="text-xs text-slate-500 md:w-24 shrink-0">Durum</div>
                        <div class="font-semibold text-slate-900 text-right md:text-left">
                            @if ($expense->is_paid)
                                <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold bg-emerald-50 text-emerald-600 border border-emerald-200">Ödendi</span>
                            @elseif (($expense->paid_amount ?? 0) > 0)
                                <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold bg-amber-50 text-amber-600 border border-amber-200">Kısmen Ödendi</span>
                            @else
                                <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold bg-slate-50 text-slate-600 border border-slate-200">Bekliyor</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 md:justify-start">
                        <div class="text-xs text-slate-500 md:w-24 shrink-0">Kategori</div>
                        <div class="font-semibold text-slate-900 text-right md:text-left">{{ $expense->categoryRelation?->name ?? $expense->category ?? '—' }}</div>
                    </div>

                    <div class="flex items-center justify-between gap-2 md:justify-start">
                        <div class="text-xs text-slate-500 md:w-24 shrink-0">Dönem</div>
                        <div class="font-semibold text-slate-900 text-right md:text-left">{{ $periodText ?? '-' }}</div>
                    </div>

                    @if ($expense->reference_number)
                        <div class="flex items-center justify-between gap-2 md:justify-start">
                            <div class="text-xs text-slate-500 md:w-24 shrink-0">Referans</div>
                            <div class="font-semibold text-slate-900 text-right md:text-left">{{ $expense->reference_number }}</div>
                        </div>
                    @endif

                </div>
            </details>

        </div>

        @php $remaining = $expense->remaining_amount ?? $expense->amount; @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-slate-100">
            <div class="py-3 px-4 md:p-5 flex items-center justify-between gap-1">
                <div class="text-[10px] md:text-xs font-semibold uppercase tracking-wide text-slate-400">Tutar</div>
                <div class="text-lg md:text-xl font-bold text-slate-900 tabular-nums">{{ number_format($expense->amount, 2, ',', '.') }} TL</div>
            </div>
            <div class="py-3 px-4 md:p-5 flex items-center justify-between gap-1">
                <div class="text-[10px] md:text-xs font-semibold uppercase tracking-wide text-slate-400">Ödenen</div>
                <div class="text-lg md:text-xl font-bold text-emerald-600 tabular-nums">{{ number_format($expense->paid_amount ?? 0, 2, ',', '.') }} TL</div>
            </div>
            <div class="py-3 px-4 md:p-5 flex items-center justify-between gap-1">
                <div class="text-[10px] md:text-xs font-semibold uppercase tracking-wide text-slate-400">Kalan</div>
                <div class="text-lg md:text-xl font-bold {{ $remaining > 0 ? 'text-amber-600' : 'text-slate-400' }} tabular-nums">{{ number_format($remaining, 2, ',', '.') }} TL</div>
            </div>
        </div>

    </div>



    {{-- Documents Card --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-950">Dokümanlar</h2>
            <a href="{{ route('expenses.edit', $expense) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">+ Doküman Ekle</a>
        </div>

        @if ($expense->documents->isEmpty())
            <div class="py-6 text-sm text-slate-500">Bu giderde henüz doküman yok.</div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($expense->documents as $document)
                    <div class="rounded-xl border border-slate-200 p-4 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-900">{{ $document->original_name }}</div>
                            <div class="text-xs text-slate-500 truncate">Doküman</div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            @if ($document->isImage())
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->file_path) }}" target="_blank" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Görüntüle</a>
                            @endif
                            <a href="{{ route('expenses.documents.download', [$expense, $document]) }}" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">İndir</a>
                            <form method="POST" action="{{ route('expenses.documents.destroy', [$expense, $document]) }}" onsubmit="return confirm('Doküman silinsin mi?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Sil</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Payment Allocations Card --}}

    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">

        <h2 class="text-lg font-semibold text-slate-950 mb-4">Gideri Kapatan Ödeme / Ödemeler</h2>

        @if ($expense->paymentAllocations->isEmpty())

            <div class="py-6 text-sm text-slate-500">Bu gidere henüz herhangi bir ödeme tahsis edilmedi.</div>

        @else

            <div class="overflow-hidden rounded-xl border border-slate-200">

                <table class="hidden md:table min-w-full divide-y divide-slate-200 text-sm">

                    <thead class="bg-slate-50 text-left text-slate-500">

                        <tr>

                            <th class="px-5 py-3">Ref No</th>

                            <th class="px-5 py-3">Açıklama</th>

                            <th class="px-5 py-3 text-right">Ödeme Tutarı</th>

                            <th class="px-5 py-3 text-right">Bağlanan</th>

                            <th class="px-5 py-3">Ödeme Tarihi</th>

                            <th class="px-5 py-3"></th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        @foreach ($expense->paymentAllocations as $allocation)

                            <tr>

                                <td class="px-5 py-4">

                                    <a href="{{ route('payments.show', $allocation->payment) }}" class="font-medium text-slate-900 hover:text-emerald-600">{{ $allocation->payment->reference_number ?? '#'.$allocation->payment->id }}</a>

                                </td>

                                <td class="px-5 py-4 text-slate-700">{{ $allocation->payment->description ?: 'Ödeme' }}</td>

                                <td class="px-5 py-4 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($allocation->payment->amount, 2, ',', '.') }} TL</td>

                                <td class="px-5 py-4 text-right font-semibold text-emerald-600 tabular-nums">{{ number_format($allocation->amount, 2, ',', '.') }} TL</td>

                                <td class="px-5 py-4 text-slate-700">{{ $allocation->payment->payment_date?->format('d.m.Y') ?? '-' }}</td>

                                <td class="px-5 py-4 text-right">

                                    <button type="button" onclick="document.getElementById('revert-allocation-modal-{{ $allocation->id }}').classList.remove('hidden')"
                                            class="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Geri Al</button>

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

                {{-- Mobil: Kapatan Ödemeler Kartları --}}
                <div class="md:hidden divide-y divide-slate-100">
                    @foreach ($expense->paymentAllocations as $allocation)
                        <div class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('payments.show', $allocation->payment) }}" class="block">
                                    <div class="text-sm text-slate-700 truncate">
                                        {{ $allocation->payment->description ?: 'Ödeme' }}
                                    </div>
                                    <div class="mt-0.5 flex items-center justify-between gap-2">
                                        <span class="text-xs text-slate-500">{{ $allocation->payment->payment_date?->format('d.m.Y') ?? '-' }}</span>
                                        <span class="text-sm font-semibold text-emerald-600">{{ number_format($allocation->amount, 2, ',', '.') }} TL</span>
                                    </div>
                                </a>
                            </div>
                            <button type="button" onclick="document.getElementById('revert-allocation-modal-{{ $allocation->id }}').classList.remove('hidden')"
                                    class="shrink-0 rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Geri Al</button>
                        </div>
                    @endforeach
                </div>

            </div>

            @foreach ($expense->paymentAllocations as $allocation)
                @php
                    $canDeletePayment = $allocation->payment->allocations_count === 1
                        && round((float) $allocation->payment->amount, 2) === round((float) $allocation->amount, 2);
                @endphp

                <div id="revert-allocation-modal-{{ $allocation->id }}" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
                    <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4 shadow-xl">
                        <h3 class="text-lg font-semibold text-slate-900 mb-1">Gider Kapamayı Geri Al</h3>
                        <p class="text-sm text-slate-500 mb-4">
                            {{ $allocation->payment->reference_number ?? '#'.$allocation->payment->id }} —
                            {{ number_format($allocation->payment->amount, 2, ',', '.') }} TL
                        </p>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 mb-4">
                            <div class="text-xs text-slate-500 mb-1">BU GİDERE TAHŞİS EDİLEN</div>
                            <div class="text-sm font-semibold text-slate-900">{{ number_format($allocation->amount, 2, ',', '.') }} TL</div>
                        </div>

                        <div class="flex flex-col gap-3">
                            @unless ($canDeletePayment)
                                <form method="POST" action="{{ route('payments.allocations.destroy', [$allocation->payment, $allocation]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="redirect_to" value="{{ route('expenses.show', $expense) }}">
                                    <button type="submit" class="w-full rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">
                                        Geri Al (Ödeme Hesapta Kalır)
                                    </button>
                                </form>
                            @endunless

                            <form method="POST" action="{{ route('payments.destroy', $allocation->payment) }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="redirect_to" value="{{ route('expenses.show', $expense) }}">
                                <button type="submit" @disabled(! $canDeletePayment)
                                        class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold {{ $canDeletePayment ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-slate-100 text-slate-400 cursor-not-allowed' }}">
                                    Ödemeyi Sil
                                </button>
                            </form>

                            @unless ($canDeletePayment)
                                <p class="text-xs text-amber-600">Bu ödeme bu gideri birebir kapatmadığından silinemez; yalnızca gider kapamayı geri alabilirsiniz.</p>
                            @endunless

                            <button type="button" onclick="document.getElementById('revert-allocation-modal-{{ $allocation->id }}').classList.add('hidden')"
                                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Vazgeç
                            </button>
                        </div>
                    </div>
                </div>

                <script>
                    document.getElementById('revert-allocation-modal-{{ $allocation->id }}')?.addEventListener('click', function(e) {
                        if (e.target === this) this.classList.add('hidden');
                    });
                </script>
            @endforeach

        @endif

    </div>

    @if ($expense->paymentAllocations->isNotEmpty())
        <div id="delete-expense-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900 mb-1">Gideri Sil</h3>
                <p class="text-sm text-slate-500 mb-4">Bu gideri kapatan ödemeler:</p>

                <div class="overflow-hidden rounded-xl border border-slate-200 mb-4">
                    <div class="divide-y divide-slate-100">
                        @foreach ($expense->paymentAllocations as $allocation)
                            <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                                <div class="min-w-0">
                                    <div class="font-medium text-slate-900">{{ $allocation->payment->reference_number ?? '#'.$allocation->payment_id }}</div>
                                    <div class="truncate text-slate-500">{{ $allocation->payment->description ?: 'Ödeme' }}</div>
                                </div>
                                <div class="shrink-0 font-semibold text-slate-900 tabular-nums">{{ number_format($allocation->amount, 2, ',', '.') }} TL</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 mb-4">
                    Bu gideri kapatan ödemeler olduğu için gider silinemez.
                </div>

                <button type="button" onclick="document.getElementById('delete-expense-modal').classList.add('hidden')" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
            </div>
        </div>

        <script>
            document.getElementById('delete-expense-modal')?.addEventListener('click', function(e) {
                if (e.target === this) this.classList.add('hidden');
            });
        </script>
    @endif

@endsection

