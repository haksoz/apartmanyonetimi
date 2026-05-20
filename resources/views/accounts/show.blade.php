@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">{{ $account->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $account->unit ? $account->unit->unit_no.' no.lu daire' : 'Daire bağlantısı yok' }}</p>
        </div>
        <div class="flex gap-2">
            @if (in_array($account->type, [App\Models\Account::TYPE_OWNER, App\Models\Account::TYPE_TENANT]))
                <a href="{{ route('dues.create', ['account_id' => $account->id]) }}" class="rounded-xl bg-slate-600 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">+ Borçlandır</a>
            @endif
            @if ($account->type === App\Models\Account::TYPE_SUPPLIER)
                <span title="Toplu gider ödeme özelliği henüz geliştirme aşamasındadır" class="cursor-not-allowed rounded-xl bg-slate-300 px-4 py-2 text-sm font-semibold text-slate-500 select-none">+ Tahsilat Al</span>
                <span title="Giderler menüsünden ekleyin" class="cursor-not-allowed rounded-xl bg-slate-300 px-4 py-2 text-sm font-semibold text-slate-500 select-none">+ Gider Ekle</span>
            @else
                <a href="{{ route('payments.create', ['account_id' => $account->id]) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Tahsilat Ekle</a>
            @endif
            <a href="{{ route('accounts.edit', $account) }}" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">Düzenle</a>
        </div>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Borç</div><div class="mt-2 text-2xl font-bold">{{ number_format($account->ledger_debit, 2, ',', '.') }} TL</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Alacak</div><div class="mt-2 text-2xl font-bold">{{ number_format($account->ledger_credit, 2, ',', '.') }} TL</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Bakiye</div><div class="mt-2 text-2xl font-bold">{{ number_format($account->ledger_balance, 2, ',', '.') }} TL</div></div>
    </div>

    @if (!in_array($account->type, [App\Models\Account::TYPE_SUPPLIER]))
        <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
            <div class="flex items-center justify-between">
                <h2 class="mb-4 text-lg font-semibold text-slate-950">Açık Aidatlar</h2>
                <a href="{{ route('dues.index') }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Aidatlar</a>
            </div>
            @if ($account->dues->isEmpty())
                <div class="py-6 text-sm text-slate-500">Bu hesap için ödenmemiş aidat yok.</div>
            @else
                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Tarih</th>
                                <th class="px-5 py-3">Açıklama</th>
                                <th class="px-5 py-3 text-right">Tutar</th>
                                <th class="px-5 py-3 text-right">Durum</th>
                                <th class="px-5 py-3 text-right">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($account->dues as $due)
                                <tr>
                                    <td class="px-5 py-4 text-slate-700">{{ $due->due_date->format('d.m.Y') }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $due->description ?: 'Aidat' }}</td>
                                    <td class="px-5 py-4 text-right text-slate-900 font-semibold">
                                        {{ number_format($due->amount, 2, ',', '.') }} TL
                                        @if ($due->status === 'partial')
                                            <div class="text-xs text-amber-600 font-normal">Kalan: {{ number_format($due->remaining_amount, 2, ',', '.') }} TL</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right {{ $due->status === 'partial' ? 'text-amber-500' : 'text-amber-600' }}">
                                        @php
                                            $statusLabel = match($due->status) {
                                                'paid'    => 'Ödendi',
                                                'partial' => 'Kısmi Ödendi',
                                                'overdue' => 'Gecikmiş',
                                                default   => 'Bekliyor',
                                            };
                                        @endphp
                                        {{ $statusLabel }}
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="{{ route('dues.payment.create', $due) }}" class="rounded-xl bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Tahsil Et</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    @if ($account->type === App\Models\Account::TYPE_SUPPLIER && $account->expenses->isNotEmpty())
        <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
            <div class="flex items-center justify-between">
                <h2 class="mb-4 text-lg font-semibold text-slate-950">Açık Giderler</h2>
                <a href="{{ route('expenses.index') }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Giderler</a>
            </div>
            <div class="overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Tarih</th>
                            <th class="px-5 py-3">Açıklama</th>
                            <th class="px-5 py-3 text-right">Tutar</th>
                            <th class="px-5 py-3 text-right">Durum</th>
                            <th class="px-5 py-3 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($account->expenses as $expense)
                            <tr>
                                <td class="px-5 py-4 text-slate-700">{{ $expense->expense_date->format('d.m.Y') }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $expense->description ?: $expense->category }}</td>
                                <td class="px-5 py-4 text-right text-slate-900 font-semibold">{{ number_format($expense->amount, 2, ',', '.') }} TL</td>
                                <td class="px-5 py-4 text-right text-amber-600">Bekliyor</td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('expenses.payment.create', $expense) }}" class="rounded-xl bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ödeme Yap</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <script>
        (function(){
            function toggleAlloc(e){
                const target = e.currentTarget.getAttribute('data-toggle-alloc');
                if(!target) return;
                const rows = document.querySelectorAll('[data-parent="'+target+'"]');
                rows.forEach(r => r.classList.toggle('hidden'));
                // toggle button text
                const open = Array.from(rows).some(r => !r.classList.contains('hidden'));
                e.currentTarget.textContent = open ? 'Tahsisleri Gizle' : 'Tahsisleri Göster';
            }

            document.addEventListener('click', function(e){
                const btn = e.target.closest('[data-toggle-alloc]');
                if(btn) toggleAlloc({currentTarget: btn});
            });
        })();
    </script>

    @if ($account->payments->isNotEmpty())
        <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
            <div class="flex items-center justify-between">
                <h2 class="mb-4 text-lg font-semibold text-slate-950">Dağıtılmamış Ödemeler</h2>
                <a href="{{ route('dues.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Aidatlara Dön</a>
            </div>
            <div class="overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3">#</th>
                            <th class="px-5 py-3">Tarih</th>
                            <th class="px-5 py-3">Açıklama</th>
                            <th class="px-5 py-3 text-right">Kalan</th>
                            <th class="px-5 py-3 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($account->payments as $payment)
                            <tr>
                                <td class="px-5 py-4 text-slate-700">{{ $payment->id }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $payment->description ?: 'Ödeme' }}</td>
                                <td class="px-5 py-4 text-right text-slate-900 font-semibold">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</td>
                                <td class="px-5 py-4 text-right space-x-2">
                                    <a href="{{ route('payments.show', $payment) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detay</a>
                                    <a href="{{ route('payments.allocations.create', $payment) }}" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Tahsis Et</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-950">Son Hareketler</h2>
            <a href="{{ route('accounts.statement', $account) }}"
               class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Tüm Hareketler &rarr;
            </a>
        </div>
        @if ($transactions->isEmpty())
            <div class="py-6 text-sm text-slate-500">Henüz hareket yok.</div>
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Tarih</th>
                            <th class="px-5 py-3">Açıklama</th>
                            <th class="px-5 py-3 text-right">Borç</th>
                            <th class="px-5 py-3 text-right">Alacak</th>
                            <th class="px-5 py-3 text-right">Bakiye</th>
                            <th class="px-5 py-3 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($transactions as $t)
                            @php
                                $debit = $t->type === 'debit' ? $t->amount : 0;
                                $credit = $t->type === 'credit' ? $t->amount : 0;
                            @endphp
                            <tr>
                                <td class="px-5 py-4 text-slate-700">{{ $t->transaction_date->format('d.m.Y') }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $t->description ?: ucfirst($t->type) }}</td>
                                <td class="px-5 py-4 text-right text-red-600 font-semibold">{{ $debit ? number_format($debit, 2, ',', '.') . ' TL' : '-' }}</td>
                                <td class="px-5 py-4 text-right text-emerald-600 font-semibold">{{ $credit ? number_format($credit, 2, ',', '.') . ' TL' : '-' }}</td>
                                <td class="px-5 py-4 text-right font-semibold">{{ number_format($t->running_balance, 2, ',', '.') }} TL</td>
                                <td class="px-5 py-4 text-right space-x-2">
                                    @if(($t->transactionable_type ?? '') === \App\Models\Payment::class && $t->transactionable_id)
                                        @if($t->allocations->isNotEmpty())
                                            <button type="button" data-toggle-alloc="alloc-{{ $t->id }}" class="toggle-alloc rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Tahsisleri Göster</button>
                                        @endif
                                        <a href="{{ route('payments.show', $t->transactionable_id) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detay</a>
                                    @elseif(($t->transactionable_type ?? '') === \App\Models\Expense::class && $t->transactionable_id)
                                        <a href="{{ route('expenses.show', $t->transactionable_id) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detay</a>
                                    @elseif(($t->transactionable_type ?? '') === \App\Models\Due::class && $t->transactionable_id)
                                        <a href="{{ route('dues.show', $t->transactionable_id) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detay</a>
                                    @endif
                                </td>
                            </tr>

                            @if($t->allocations->isNotEmpty())
                                @foreach($t->allocations as $a)
                                    <tr class="bg-slate-50 text-sm alloc-row alloc-{{ $t->id }} hidden" data-parent="alloc-{{ $t->id }}">
                                        <td class="px-5 py-2"></td>
                                        <td class="px-5 py-2">Tahsis — Aidat <a href="{{ route('dues.show', $a->due) }}" class="font-medium text-slate-900 hover:text-emerald-600">#{{ $a->due->id }}</a> — {{ $a->due->due_date->format('d.m.Y') }}
                                            @php $desc = $a->due->description ?: 'Aidat'; @endphp
                                            <div class="text-slate-500 text-xs mt-1" title="{{ $desc }}">{{ \Illuminate\Support\Str::limit($desc, 80) }}</div>
                                        </td>
                                        <td class="px-5 py-2 text-right text-emerald-600 font-medium tabular-nums">{{ number_format($a->amount,2,',','.') }} TL</td>
                                        <td class="px-5 py-2"></td>
                                        <td class="px-5 py-2"></td>
                                        <td class="px-5 py-2 text-right">
                                            <a href="{{ route('dues.show', $a->due) }}" class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Aidat Detay</a>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
