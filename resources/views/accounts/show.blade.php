@extends('layouts.app')



@section('content')

    <div class="mb-6 flex items-center justify-between">

        <div>

            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('accounts.index') }}" class="hover:text-slate-600">Hesaplar</a>
                <span>/</span>
                <span class="text-slate-500">{{ $account->type_label }}</span>
            </div>

            <h1 class="text-2xl font-bold text-slate-950">
                {{ $account->name }}
                @if ($account->unit)
                    <span class="text-slate-400 font-normal"> — Daire No: {{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}</span>
                @endif
            </h1>

            @if (!$account->is_active)
                <div class="mt-1">
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-1 text-sm font-semibold text-red-600">Pasif</span>
                </div>
            @endif

        </div>

        <div class="flex gap-2">

            <a href="{{ route('accounts.statement', $account) }}"

               class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">

                Tüm Hareketler

            </a>

            @if (in_array($account->type, [App\Models\Account::TYPE_OWNER, App\Models\Account::TYPE_TENANT]))

                <a href="{{ route('dues.create', ['account_id' => $account->id]) }}" class="rounded-xl bg-slate-600 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">+ Borçlandır</a>

            @endif

            @if ($account->type === App\Models\Account::TYPE_SUPPLIER)

                <a href="{{ route('expenses.create', ['account_id' => $account->id]) }}" class="rounded-xl bg-slate-600 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">+ Gider Ekle</a>

                <a href="{{ route('payments.create', ['account_id' => $account->id]) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Ödeme Ekle</a>

            @else

                <a href="{{ route('payments.create', ['account_id' => $account->id]) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Tahsilat Ekle</a>

            @endif

            @if (in_array($account->type, [App\Models\Account::TYPE_OWNER, App\Models\Account::TYPE_TENANT]) && $account->dues->isNotEmpty() && $transferableAccounts->isNotEmpty())

                <button type="button" onclick="document.getElementById('transfer-dues-modal').classList.remove('hidden')"

                        class="rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600">

                    Borç Devret

                </button>

            @endif

            <a href="{{ route('accounts.edit', $account) }}" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">Düzenle</a>

        </div>

    </div>



    {{-- Özet + Bilgiler Birleşik Kart --}}
    <div class="rounded-2xl bg-white shadow-sm mb-6 overflow-hidden">
        {{-- Üst: Borç / Alacak / Bakiye --}}
        <div class="grid grid-cols-3 divide-x divide-slate-100 border-b border-slate-100">
            <div class="p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Borç</div>
                <div class="mt-1.5 text-xl font-bold text-red-600 tabular-nums">{{ number_format($account->ledger_debit, 2, ',', '.') }} TL</div>
            </div>
            <div class="p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alacak</div>
                <div class="mt-1.5 text-xl font-bold text-emerald-600 tabular-nums">{{ number_format($account->ledger_credit, 2, ',', '.') }} TL</div>
            </div>
            <div class="p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bakiye</div>
                @php $balance = $account->ledger_balance; @endphp
                <div class="mt-1.5 text-xl font-bold tabular-nums {{ $balance < 0 ? 'text-red-600' : ($balance > 0 ? 'text-emerald-600' : 'text-slate-400') }}">
                    {{ number_format(abs($balance), 2, ',', '.') }} TL
                    <span class="text-sm font-normal">{{ $balance < 0 ? '(B)' : ($balance > 0 ? '(A)' : '') }}</span>
                </div>
            </div>
        </div>

        {{-- Alt: Bilgiler --}}
        <div class="p-6">
            <div class="grid gap-x-8 gap-y-4 text-sm" style="grid-template-columns: repeat(auto-fill, minmax(160px, 1fr))">

                {{-- Daire bilgileri --}}
                @if ($account->unit)
                    @if ($account->unit->floor)
                    <div>
                        <div class="text-xs text-slate-500 mb-0.5">Kat</div>
                        <div class="font-semibold text-slate-900">{{ $account->unit->floor }}</div>
                    </div>
                    @endif
                    @if ($account->unit->block)
                    <div>
                        <div class="text-xs text-slate-500 mb-0.5">Blok</div>
                        <div class="font-semibold text-slate-900">{{ $account->unit->block }}</div>
                    </div>
                    @endif
                    @if ($account->unit->square_meters)
                    <div>
                        <div class="text-xs text-slate-500 mb-0.5">Alan</div>
                        <div class="font-semibold text-slate-900">{{ number_format($account->unit->square_meters, 0, ',', '.') }} m²</div>
                    </div>
                    @endif
                    @if ($account->unit->share_coefficient)
                    <div>
                        <div class="text-xs text-slate-500 mb-0.5">Hisse Katsayısı</div>
                        <div class="font-semibold text-slate-900">{{ $account->unit->share_coefficient }}</div>
                    </div>
                    @endif
                @endif

                {{-- Portal erişimi --}}
                <div>
                    <div class="text-xs text-slate-500 mb-0.5">Portal Erişimi</div>
                    <div class="font-semibold text-slate-900">{{ $account->user ? 'Var' : 'Yok' }}</div>
                </div>

                {{-- Açılış tarihi --}}
                <div>
                    <div class="text-xs text-slate-500 mb-0.5">
                        @if ($account->type === App\Models\Account::TYPE_TENANT) Kiracı Girişi
                        @else Hesap Açılışı
                        @endif
                    </div>
                    <div class="font-semibold text-slate-900">
                        @if ($account->type === App\Models\Account::TYPE_TENANT && $account->active_tenant_assignment)
                            {{ $account->active_tenant_assignment->move_in_date->format('d.m.Y') }}
                        @else
                            {{ $account->account_opening_date ? $account->account_opening_date->format('d.m.Y') : '—' }}
                        @endif
                    </div>
                </div>

                {{-- Kapanış tarihi --}}
                @if ($account->account_end_date)
                <div>
                    <div class="text-xs text-slate-500 mb-0.5">
                        @if ($account->type === App\Models\Account::TYPE_TENANT) Kiracı Çıkışı
                        @elseif ($account->type === App\Models\Account::TYPE_OWNER) Maliklik Bitişi
                        @else Hesap Kapanışı
                        @endif
                    </div>
                    <div class="font-semibold text-red-600">{{ $account->account_end_date->format('d.m.Y') }}</div>
                </div>
                @endif

                {{-- Kullanıcı bilgileri --}}
                @if ($account->user)
                <div>
                    <div class="text-xs text-slate-500 mb-0.5">E-posta</div>
                    <div class="font-semibold text-slate-900">{{ $account->user->email }}</div>
                </div>
                @if ($account->user->phone)
                <div>
                    <div class="text-xs text-slate-500 mb-0.5">Telefon</div>
                    <div class="font-semibold text-slate-900">{{ $account->user->phone }}</div>
                </div>
                @endif
                @endif

            </div>
        </div>
    </div>



    @if (!in_array($account->type, [App\Models\Account::TYPE_SUPPLIER]))

        <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">

            <div class="flex items-center justify-between mb-4">

                <div class="flex items-center gap-3">

                    <h2 class="text-lg font-semibold text-slate-950">Açık Aidatlar</h2>

                    @if ($importedDues->isNotEmpty())

                        <label class="flex items-center gap-1.5 cursor-pointer text-xs text-slate-500 select-none">

                            <input type="checkbox" id="show-imported-dues" class="rounded">

                            Devir Öncesini Göster ({{ $importedDues->count() }})

                        </label>

                    @endif

                </div>

                @if ($account->dues->isNotEmpty() || $importedDues->isNotEmpty())

                    <button type="button" id="bulk-pay-btn" onclick="openBulkPayModal()"

                        class="rounded-xl px-4 py-2 text-sm font-semibold transition-colors bg-slate-200 text-slate-400 cursor-not-allowed" disabled>

                        Seçilenleri Tahsil Et &mdash; <span id="selected-count">0</span> aidat / <span id="selected-total">0,00</span> TL

                    </button>

                @endif

            </div>

            @if ($account->dues->isEmpty() && $importedDues->isEmpty())

                <div class="py-6 text-sm text-slate-500">Bu hesap için ödenmemiş aidat yok.</div>

            @else

                <div class="overflow-hidden rounded-2xl border border-slate-200">

                    <table class="min-w-full divide-y divide-slate-200 text-sm">

                        <thead class="bg-slate-50 text-left text-slate-500">

                            <tr>

                                <th class="px-5 py-3"><input type="checkbox" id="select-all-dues" class="rounded"></th>

                                <th class="px-5 py-3">Tarih</th>

                                <th class="px-5 py-3">Açıklama</th>

                                <th class="px-5 py-3 text-right">Kalan Tutar</th>

                                <th class="px-5 py-3 text-right">Durum</th>

                                <th class="px-5 py-3 text-right">İşlem</th>

                            </tr>

                        </thead>

                        <tbody class="divide-y divide-slate-100">

                            @foreach ($account->dues as $due)

                                <tr>

                                    <td class="px-5 py-4">

                                        <input type="checkbox" class="due-checkbox rounded"

                                               data-due-id="{{ $due->id }}"

                                               data-amount="{{ $due->remaining_amount }}">

                                    </td>

                                    <td class="px-5 py-4 text-slate-700">{{ $due->due_date->format('d.m.Y') }}</td>

                                    <td class="px-5 py-4 text-slate-700">{{ $due->description ?: 'Aidat' }}</td>

                                    <td class="px-5 py-4 text-right text-slate-900 font-semibold">

                                        {{ number_format($due->remaining_amount, 2, ',', '.') }} TL

                                        @if ($due->status === 'partial')

                                            <div class="text-xs text-slate-400 font-normal">Toplam: {{ number_format($due->amount, 2, ',', '.') }} TL</div>

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

                                        <a href="{{ route('dues.payment.create', $due) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Tahsil Et</a>

                                    </td>

                                </tr>

                            @endforeach

                            {{-- Devir Öncesi Aidatlar (toggle ile gösterilir) --}}

                            @foreach ($importedDues as $due)

                                <tr class="imported-due-row hidden bg-blue-50/40">

                                    <td class="px-5 py-4">

                                        <input type="checkbox" class="due-checkbox rounded"

                                               data-due-id="{{ $due->id }}"

                                               data-amount="{{ $due->remaining_amount }}">

                                    </td>

                                    <td class="px-5 py-4 text-slate-700">{{ $due->due_date->format('d.m.Y') }}</td>

                                    <td class="px-5 py-4 text-slate-700">

                                        {{ $due->description ?: 'Aidat' }}

                                        <span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>

                                    </td>

                                    <td class="px-5 py-4 text-right text-slate-900 font-semibold">

                                        {{ number_format($due->remaining_amount, 2, ',', '.') }} TL

                                    </td>

                                    <td class="px-5 py-4 text-right text-amber-600">

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

                                        <a href="{{ route('dues.payment.create', $due) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Tahsil Et</a>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            @endif

        </div>



        {{-- Bulk Pay Modal --}}

        <div id="bulk-pay-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">

            <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 shadow-xl">

                <h3 class="text-lg font-semibold text-slate-900 mb-1">Seçili Aidatları Tahsil Et</h3>

                <p class="text-sm text-slate-500 mb-4">Toplam: <span id="modal-total" class="font-bold text-slate-900">0,00 TL</span></p>



                <form id="bulk-pay-form" method="POST" action="{{ route('accounts.dues.bulk-pay', $account) }}">

                    @csrf

                    <div id="bulk-due-ids"></div>



                    <div class="space-y-4">

                        <div>

                            <label class="block text-xs font-medium text-slate-600 mb-1.5">Kasa</label>

                            <select name="cash_box_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">

                                <option value="">Kasa seçin</option>

                                @foreach ($cashBoxes as $cashBox)

                                    <option value="{{ $cashBox->id }}">{{ $cashBox->name }}</option>

                                @endforeach

                            </select>

                        </div>

                        <div>

                            <label class="block text-xs font-medium text-slate-600 mb-1.5">Ödeme Tarihi</label>

                            <input type="date" name="payment_date" required value="{{ now()->toDateString() }}"

                                   class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">

                        </div>

                        <div>

                            <label class="block text-xs font-medium text-slate-600 mb-1.5">Açıklama <span class="text-slate-400">(opsiyonel)</span></label>

                            <input type="text" name="description" placeholder="Çoklu Aidat Tahsilatı"

                                   class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">

                        </div>

                    </div>



                    <div class="flex gap-3 mt-5">

                        <button type="submit" class="flex-1 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">

                            Tahsil Et

                        </button>

                        <button type="button" onclick="closeBulkPayModal()"

                                class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">

                            İptal

                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif



    @if (!in_array($account->type, [App\Models\Account::TYPE_SUPPLIER]) && ($account->payments->isNotEmpty() || $importedPayments->isNotEmpty()))

        <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">

            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">

                <div class="flex items-center gap-3">

                    <h2 class="text-lg font-semibold text-slate-950">Açık Tahsilatlar</h2>

                    @if ($importedPayments->isNotEmpty())

                        <label class="flex items-center gap-1.5 cursor-pointer text-xs text-slate-500 select-none">

                            <input type="checkbox" id="show-imported-payments" class="rounded">

                            Devir Öncesini Göster ({{ $importedPayments->count() }})

                        </label>

                    @endif

                </div>

                <form id="multi-allocate-form" method="POST" action="{{ route('accounts.payments.multi-allocate', $account) }}">

                    @csrf

                    <input type="hidden" name="payment_ids" id="multi-allocate-payment-ids">

                    <button type="submit" id="multi-allocate-btn"
                        class="rounded-xl px-4 py-2 text-sm font-semibold transition-colors bg-slate-200 text-slate-400 cursor-not-allowed" disabled>

                        Seçilenleri Aidata Bağla &mdash; <span id="selected-payment-count">0</span> tahsilat / <span id="selected-payment-total">0,00</span> TL

                    </button>

                </form>

            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200">

                <table class="min-w-full divide-y divide-slate-200 text-sm">

                    <thead class="bg-slate-50 text-left text-slate-500">

                        <tr>

                            <th class="px-4 py-3"><input type="checkbox" id="select-all-payments" class="rounded"></th>

                            <th class="px-5 py-3">Tarih</th>

                            <th class="px-5 py-3">Açıklama</th>

                            <th class="px-5 py-3 text-right">Dağıtılabilir Bakiye</th>

                            <th class="px-5 py-3 text-right">İşlem</th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        @foreach ($account->payments as $payment)

                            <tr class="payment-row" data-imported="0">

                                <td class="px-4 py-4">

                                    <input type="checkbox" class="payment-checkbox rounded"

                                        data-payment-id="{{ $payment->id }}"

                                        data-amount="{{ $payment->unallocated_amount }}">

                                </td>

                                <td class="px-5 py-4 text-slate-700">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</td>

                                <td class="px-5 py-4 text-slate-700">{{ $payment->description ?: 'Ödeme' }}</td>

                                <td class="px-5 py-4 text-right font-semibold text-slate-900">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</td>

                                <td class="px-5 py-4 text-right space-x-2">

                                    <a href="{{ route('payments.show', $payment) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detay</a>

                                    <a href="{{ route('payments.allocations.create', $payment) }}" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Aidata Bağla</a>

                                </td>

                            </tr>

                        @endforeach

                        @foreach ($importedPayments as $payment)

                            <tr class="payment-row imported-payment-row hidden bg-blue-50/40" data-imported="1">

                                <td class="px-4 py-4">

                                    <input type="checkbox" class="payment-checkbox rounded"

                                        data-payment-id="{{ $payment->id }}"

                                        data-amount="{{ $payment->unallocated_amount }}">

                                </td>

                                <td class="px-5 py-4 text-slate-700">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</td>

                                <td class="px-5 py-4 text-slate-700">

                                    {{ $payment->description ?: 'Ödeme' }}

                                    <span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>

                                </td>

                                <td class="px-5 py-4 text-right font-semibold text-slate-900">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</td>

                                <td class="px-5 py-4 text-right space-x-2">

                                    <a href="{{ route('payments.show', $payment) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detay</a>

                                    <a href="{{ route('payments.allocations.create', $payment) }}" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Aidata Bağla</a>

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        </div>

    @endif





    @if ($account->type === App\Models\Account::TYPE_SUPPLIER && $account->expenses->isNotEmpty())

        <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">

            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">

                <h2 class="text-lg font-semibold text-slate-950">Açık Giderler</h2>

                <div class="flex items-center gap-3 flex-wrap">

                    <form id="multi-expense-pay-form" method="POST" action="{{ route('accounts.expenses.multi-pay', $account) }}">
                        @csrf
                        <input type="hidden" name="expense_ids" id="multi-expense-pay-ids">
                        <button type="submit" id="multi-expense-pay-btn"
                            class="rounded-xl px-4 py-2 text-sm font-semibold transition-colors bg-slate-200 text-slate-400 cursor-not-allowed" disabled>
                            Seçilileri Öde &mdash; <span id="selected-expense-count">0</span> gider / <span id="selected-expense-total">0,00</span> TL
                        </button>
                    </form>

                </div>

            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200">

                <table class="min-w-full divide-y divide-slate-200 text-sm">

                    <thead class="bg-slate-50 text-left text-slate-500">

                        <tr>

                            <th class="px-4 py-3"><input type="checkbox" id="select-all-expenses" class="rounded"></th>

                            <th class="px-5 py-3">Tarih</th>

                            <th class="px-5 py-3">Açıklama</th>

                            <th class="px-5 py-3 text-right">Tutar</th>

                            <th class="px-5 py-3 text-right">İşlem</th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        @foreach ($account->expenses as $expense)

                            <tr>

                                <td class="px-4 py-4">
                                    <input type="checkbox" class="expense-checkbox rounded"
                                        data-expense-id="{{ $expense->id }}"
                                        data-amount="{{ $expense->amount }}">
                                </td>

                                <td class="px-5 py-4 text-slate-700">{{ $expense->expense_date?->format('d.m.Y') ?? ($expense->period_month?->format('d.m.Y') ?? '-') }}</td>

                                <td class="px-5 py-4 text-slate-700">{{ $expense->description ?: $expense->category }}</td>

                                <td class="px-5 py-4 text-right text-slate-900 font-semibold">{{ number_format($expense->amount, 2, ',', '.') }} TL</td>

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



    @if ($account->type === App\Models\Account::TYPE_SUPPLIER && ($account->payments->isNotEmpty() || $importedPayments->isNotEmpty()))

        <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">

            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">

                <div class="flex items-center gap-3">

                    <h2 class="text-lg font-semibold text-slate-950">Açık Ödemeler</h2>

                    @if ($importedPayments->isNotEmpty())

                        <label class="flex items-center gap-1.5 cursor-pointer text-xs text-slate-500 select-none">

                            <input type="checkbox" id="show-imported-supplier-payments" class="rounded">

                            Devir Öncesini Göster ({{ $importedPayments->count() }})

                        </label>

                    @endif

                </div>

                <form id="multi-supplier-allocate-form" method="POST" action="{{ route('accounts.payments.multi-supplier-allocate', $account) }}">

                    @csrf

                    <input type="hidden" name="payment_ids" id="multi-supplier-allocate-payment-ids">

                    <button type="submit" id="multi-supplier-allocate-btn"
                        class="rounded-xl px-4 py-2 text-sm font-semibold transition-colors bg-slate-200 text-slate-400 cursor-not-allowed" disabled>

                        Seçilileri Kapat &mdash; <span id="selected-supplier-payment-count">0</span> ödeme / <span id="selected-supplier-payment-total">0,00</span> TL

                    </button>

                </form>

            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200">

                <table class="min-w-full divide-y divide-slate-200 text-sm">

                    <thead class="bg-slate-50 text-left text-slate-500">

                        <tr>

                            <th class="px-4 py-3"><input type="checkbox" id="select-all-supplier-payments" class="rounded"></th>

                            <th class="px-5 py-3">Tarih</th>

                            <th class="px-5 py-3">Açıklama</th>

                            <th class="px-5 py-3 text-right">Dağıtılabilir Bakiye</th>

                            <th class="px-5 py-3 text-right">İşlem</th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        @foreach ($account->payments as $payment)

                            <tr class="supplier-payment-row" data-imported="0">

                                <td class="px-4 py-4">

                                    <input type="checkbox" class="supplier-payment-checkbox rounded"

                                        data-payment-id="{{ $payment->id }}"

                                        data-amount="{{ $payment->unallocated_amount }}">

                                </td>

                                <td class="px-5 py-4 text-slate-700">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</td>

                                <td class="px-5 py-4 text-slate-700">{{ $payment->description ?: 'Ödeme' }}</td>

                                <td class="px-5 py-4 text-right font-semibold text-slate-900">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</td>

                                <td class="px-5 py-4 text-right space-x-2">

                                    <a href="{{ route('payments.show', $payment) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detay</a>

                                    <a href="{{ route('payments.supplier-allocations.create', $payment) }}" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Kapat</a>

                                </td>

                            </tr>

                        @endforeach

                        @foreach ($importedPayments as $payment)

                            <tr class="supplier-payment-row imported-supplier-payment-row hidden bg-blue-50/40" data-imported="1">

                                <td class="px-4 py-4">

                                    <input type="checkbox" class="supplier-payment-checkbox rounded"

                                        data-payment-id="{{ $payment->id }}"

                                        data-amount="{{ $payment->unallocated_amount }}">

                                </td>

                                <td class="px-5 py-4 text-slate-700">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</td>

                                <td class="px-5 py-4 text-slate-700">

                                    {{ $payment->description ?: 'Ödeme' }}

                                    <span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>

                                </td>

                                <td class="px-5 py-4 text-right font-semibold text-slate-900">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</td>

                                <td class="px-5 py-4 text-right space-x-2">

                                    <a href="{{ route('payments.show', $payment) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detay</a>

                                    <a href="{{ route('payments.supplier-allocations.create', $payment) }}" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Kapat</a>

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        </div>

    @endif



    <script>

        // Checkbox & bulk pay

        (function(){

            const selectAll = document.getElementById('select-all-dues');

            const bulkBtn   = document.getElementById('bulk-pay-btn');

            const countEl   = document.getElementById('selected-count');



            if (!selectAll) return;



            const totalEl = document.getElementById('selected-total');



            const updateBtn = () => {

                const checked = document.querySelectorAll('.due-checkbox:checked');

                let total = 0;

                checked.forEach(cb => total += parseFloat(cb.dataset.amount));

                countEl.textContent = checked.length;

                totalEl.textContent = total.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                if (checked.length > 0) {

                    bulkBtn.disabled = false;

                    bulkBtn.classList.remove('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');

                    bulkBtn.classList.add('bg-emerald-600', 'text-white', 'hover:bg-emerald-700', 'cursor-pointer');

                } else {

                    bulkBtn.disabled = true;

                    bulkBtn.classList.remove('bg-emerald-600', 'text-white', 'hover:bg-emerald-700', 'cursor-pointer');

                    bulkBtn.classList.add('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');

                }

            };



            selectAll.addEventListener('change', function() {

                document.querySelectorAll('.due-checkbox').forEach(cb => cb.checked = this.checked);

                updateBtn();

            });



            document.querySelectorAll('.due-checkbox').forEach(cb => {

                cb.addEventListener('change', function() {

                    const all = document.querySelectorAll('.due-checkbox');

                    selectAll.checked = Array.from(all).every(c => c.checked);

                    selectAll.indeterminate = !selectAll.checked && Array.from(all).some(c => c.checked);

                    updateBtn();

                });

            });

        })();



        function openBulkPayModal() {

            const checked = document.querySelectorAll('.due-checkbox:checked');

            if (!checked.length) return;



            let total = 0;

            const container = document.getElementById('bulk-due-ids');

            container.innerHTML = '';

            checked.forEach(cb => {

                total += parseFloat(cb.dataset.amount);

                const inp = document.createElement('input');

                inp.type = 'hidden';

                inp.name = 'due_ids[]';

                inp.value = cb.dataset.dueId;

                container.appendChild(inp);

            });



            document.getElementById('modal-total').textContent =

                total.toLocaleString('tr-TR', {minimumFractionDigits: 2}) + ' TL';

            document.getElementById('bulk-pay-modal').classList.remove('hidden');

        }



        function closeBulkPayModal() {

            document.getElementById('bulk-pay-modal').classList.add('hidden');

        }



        document.getElementById('bulk-pay-modal')?.addEventListener('click', function(e) {

            if (e.target === this) closeBulkPayModal();

        });



        // Devir Öncesi toggle — DOMContentLoaded ile bağla (ödemeler bölümü bu script'ten sonra render edilir)

        document.addEventListener('DOMContentLoaded', function() {

            document.getElementById('show-imported-dues')?.addEventListener('change', function() {

                document.querySelectorAll('.imported-due-row').forEach(r => r.classList.toggle('hidden', !this.checked));

            });



            document.getElementById('show-imported-payments')?.addEventListener('change', function() {

                document.querySelectorAll('.imported-payment-row').forEach(r => r.classList.toggle('hidden', !this.checked));

            });



            document.getElementById('show-imported-supplier-payments')?.addEventListener('change', function() {

                document.querySelectorAll('.imported-supplier-payment-row').forEach(r => r.classList.toggle('hidden', !this.checked));

            });


        });



        // Tedarikçi ödeme checkbox & toplu tahsis

        (function(){

            const selectAllPay = document.getElementById('select-all-supplier-payments');

            const multiBtn     = document.getElementById('multi-supplier-allocate-btn');

            const countEl      = document.getElementById('selected-supplier-payment-count');

            const totalEl      = document.getElementById('selected-supplier-payment-total');

            const idsInput     = document.getElementById('multi-supplier-allocate-payment-ids');



            if (!selectAllPay) return;



            const updateBtn = () => {

                const checked = document.querySelectorAll('.supplier-payment-checkbox:checked');

                let total = 0, ids = [];

                checked.forEach(cb => { total += parseFloat(cb.dataset.amount); ids.push(cb.dataset.paymentId); });

                countEl.textContent = checked.length;

                totalEl.textContent = total.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                idsInput.value = ids.join(',');

                if (checked.length > 0) {

                    multiBtn.disabled = false;

                    multiBtn.classList.remove('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');

                    multiBtn.classList.add('bg-slate-950', 'text-white', 'hover:bg-slate-800', 'cursor-pointer');

                } else {

                    multiBtn.disabled = true;

                    multiBtn.classList.remove('bg-slate-950', 'text-white', 'hover:bg-slate-800', 'cursor-pointer');

                    multiBtn.classList.add('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');

                    idsInput.value = '';

                }

            };



            selectAllPay.addEventListener('change', function() {

                document.querySelectorAll('.supplier-payment-checkbox').forEach(cb => cb.checked = this.checked);

                updateBtn();

            });



            document.querySelectorAll('.supplier-payment-checkbox').forEach(cb => {

                cb.addEventListener('change', function() {

                    const all = document.querySelectorAll('.supplier-payment-checkbox');

                    selectAllPay.checked = Array.from(all).every(c => c.checked);

                    selectAllPay.indeterminate = !selectAllPay.checked && Array.from(all).some(c => c.checked);

                    updateBtn();

                });

            });

        })();



        // Kiracı/Kat maliki ödeme checkbox & toplu tahsis

        (function(){

            const selectAllPay = document.getElementById('select-all-payments');

            const multiBtn     = document.getElementById('multi-allocate-btn');

            const countEl      = document.getElementById('selected-payment-count');

            const totalEl      = document.getElementById('selected-payment-total');

            const idsInput     = document.getElementById('multi-allocate-payment-ids');



            if (!selectAllPay) return;



            const updatePayBtn = () => {

                const checked = document.querySelectorAll('.payment-checkbox:checked');

                let total = 0, ids = [];

                checked.forEach(cb => { total += parseFloat(cb.dataset.amount); ids.push(cb.dataset.paymentId); });

                countEl.textContent = checked.length;

                totalEl.textContent = total.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                idsInput.value = ids.join(',');

                if (checked.length > 0) {

                    multiBtn.disabled = false;

                    multiBtn.classList.remove('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');

                    multiBtn.classList.add('bg-slate-950', 'text-white', 'hover:bg-slate-800', 'cursor-pointer');

                } else {

                    multiBtn.disabled = true;

                    multiBtn.classList.remove('bg-slate-950', 'text-white', 'hover:bg-slate-800', 'cursor-pointer');

                    multiBtn.classList.add('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');

                    idsInput.value = '';

                }

            };



            selectAllPay.addEventListener('change', function() {

                document.querySelectorAll('.payment-checkbox').forEach(cb => cb.checked = this.checked);

                updatePayBtn();

            });



            document.querySelectorAll('.payment-checkbox').forEach(cb => {

                cb.addEventListener('change', function() {

                    const all = document.querySelectorAll('.payment-checkbox');

                    selectAllPay.checked = Array.from(all).every(c => c.checked);

                    selectAllPay.indeterminate = !selectAllPay.checked && Array.from(all).some(c => c.checked);

                    updatePayBtn();

                });

            });

        })();



        // Tedarikçi gider checkbox & toplu ödeme

        (function(){

            const selectAll = document.getElementById('select-all-expenses');
            const multiBtn  = document.getElementById('multi-expense-pay-btn');
            const countEl   = document.getElementById('selected-expense-count');
            const totalEl   = document.getElementById('selected-expense-total');
            const idsInput  = document.getElementById('multi-expense-pay-ids');

            if (!selectAll) return;

            const updateBtn = () => {
                const checked = document.querySelectorAll('.expense-checkbox:checked');
                let total = 0, ids = [];
                checked.forEach(cb => { total += parseFloat(cb.dataset.amount); ids.push(cb.dataset.expenseId); });
                countEl.textContent = checked.length;
                totalEl.textContent = total.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                idsInput.value = ids.join(',');
                if (checked.length > 0) {
                    multiBtn.disabled = false;
                    multiBtn.classList.remove('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');
                    multiBtn.classList.add('bg-slate-950', 'text-white', 'hover:bg-slate-800', 'cursor-pointer');
                } else {
                    multiBtn.disabled = true;
                    multiBtn.classList.remove('bg-slate-950', 'text-white', 'hover:bg-slate-800', 'cursor-pointer');
                    multiBtn.classList.add('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');
                    idsInput.value = '';
                }
            };

            selectAll.addEventListener('change', function() {
                document.querySelectorAll('.expense-checkbox').forEach(cb => cb.checked = this.checked);
                updateBtn();
            });

            document.querySelectorAll('.expense-checkbox').forEach(cb => {
                cb.addEventListener('change', function() {
                    const all = document.querySelectorAll('.expense-checkbox');
                    selectAll.checked = Array.from(all).every(c => c.checked);
                    selectAll.indeterminate = !selectAll.checked && Array.from(all).some(c => c.checked);
                    updateBtn();
                });
            });

        })();



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






    <div class="rounded-2xl bg-white p-6 shadow-sm">

        <h2 class="mb-4 text-lg font-semibold text-slate-950">Son Hareketler</h2>

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

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        @foreach ($transactions as $t)

                            @php

                                $debit = $t->type === 'debit' ? $t->amount : 0;

                                $credit = $t->type === 'credit' ? $t->amount : 0;

                            @endphp

                            @php
                                $detailUrl = null;
                                if(($t->transactionable_type ?? '') === \App\Models\Payment::class && $t->transactionable_id)
                                    $detailUrl = route('payments.show', $t->transactionable_id);
                                elseif(($t->transactionable_type ?? '') === \App\Models\Expense::class && $t->transactionable_id)
                                    $detailUrl = route('expenses.show', $t->transactionable_id);
                                elseif(($t->transactionable_type ?? '') === \App\Models\Due::class && $t->transactionable_id)
                                    $detailUrl = route('dues.show', $t->transactionable_id);
                            @endphp

                            <tr class="hover:bg-slate-50 transition-colors {{ $detailUrl ? 'cursor-pointer' : '' }}"
                                @if($detailUrl) onclick="window.location.href='{{ $detailUrl }}'" @endif>

                                <td class="px-5 py-4 text-slate-700">{{ $t->transaction_date->format('d.m.Y') }}</td>

                                <td class="px-5 py-4 text-slate-700">{{ $t->description ?: ucfirst($t->type) }}</td>

                                <td class="px-5 py-4 text-right text-red-600 font-semibold">{{ $debit ? number_format($debit, 2, ',', '.') . ' TL' : '-' }}</td>

                                <td class="px-5 py-4 text-right text-emerald-600 font-semibold">{{ $credit ? number_format($credit, 2, ',', '.') . ' TL' : '-' }}</td>

                                <td class="px-5 py-4 text-right font-semibold">{{ number_format($t->running_balance, 2, ',', '.') }} TL</td>


                            </tr>



                            @if($t->allocations->isNotEmpty())

                                @foreach($t->allocations as $a)

                                    <tr class="bg-slate-50 text-sm alloc-row alloc-{{ $t->id }} hidden" data-parent="alloc-{{ $t->id }}">

                                        <td class="px-5 py-2"></td>

                                        @if ($a->due_id && $a->due)

                                            <td class="px-5 py-2">Tahsis — Aidat <a href="{{ route('dues.show', $a->due) }}" class="font-medium text-slate-900 hover:text-emerald-600">#{{ $a->due->id }}</a> — {{ $a->due->due_date->format('d.m.Y') }}

                                                @php $desc = $a->due->description ?: 'Aidat'; @endphp

                                                <div class="text-slate-500 text-xs mt-1" title="{{ $desc }}">{{ \Illuminate\Support\Str::limit($desc, 80) }}</div>

                                            </td>

                                            <td class="px-5 py-2 text-right">—</td>

                                            <td class="px-5 py-2 text-right text-emerald-600 font-medium tabular-nums">{{ number_format($a->amount,2,',','.') }} TL</td>

                                            <td class="px-5 py-2 text-right">—</td>

                                            <td class="px-5 py-2 text-right">

                                                <a href="{{ route('dues.show', $a->due) }}" class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Aidat Detay</a>

                                            </td>

                                        @elseif ($a->expense_id && $a->expense)

                                            <td class="px-5 py-2">Tahsis — Gider <a href="{{ route('expenses.show', $a->expense) }}" class="font-medium text-slate-900 hover:text-emerald-600">#{{ $a->expense->id }}</a>

                                                @if ($a->expense->expense_date)
                                                    — {{ $a->expense->expense_date->format('d.m.Y') }}
                                                @endif

                                                @php $desc = $a->expense->description ?: ($a->expense->category ?? 'Gider'); @endphp

                                                <div class="text-slate-500 text-xs mt-1" title="{{ $desc }}">{{ \Illuminate\Support\Str::limit($desc, 80) }}</div>

                                            </td>

                                            <td class="px-5 py-2 text-right">—</td>

                                            <td class="px-5 py-2 text-right text-emerald-600 font-medium tabular-nums">{{ number_format($a->amount,2,',','.') }} TL</td>

                                            <td class="px-5 py-2 text-right">—</td>

                                            <td class="px-5 py-2 text-right">

                                                <a href="{{ route('expenses.show', $a->expense) }}" class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Gider Detay</a>

                                            </td>

                                        @endif

                                    </tr>

                                @endforeach

                            @endif

                        @endforeach

                    </tbody>

                </table>

            </div>

        @endif

    </div>



    {{-- Borç Devri Modal --}}

    @if (in_array($account->type, [App\Models\Account::TYPE_OWNER, App\Models\Account::TYPE_TENANT]) && $account->dues->isNotEmpty() && $transferableAccounts->isNotEmpty())

    <div id="transfer-dues-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">

        <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4 shadow-xl">

            <h3 class="text-lg font-semibold text-slate-900 mb-1">Borç Devri</h3>

            <p class="text-sm text-slate-500 mb-4">Seçilen açık aidat(lar) başka bir hesaba devredilir.</p>



            <form method="POST" id="transfer-form" action="" class="space-y-4">

                @csrf



                <div>

                    <label class="block text-xs font-semibold text-slate-600 mb-2">Devredilecek Aidat</label>

                    <div class="rounded-xl border border-slate-200 divide-y divide-slate-100 max-h-48 overflow-y-auto">

                        @foreach ($account->dues as $due)

                            <label class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-slate-50">

                                <input type="radio" name="due_id" value="{{ $due->id }}"

                                       data-action="{{ route('dues.transfer', $due) }}"

                                       class="due-radio" required>

                                <div class="flex-1 text-sm">

                                    <span class="font-medium text-slate-900">{{ number_format($due->amount, 2, ',', '.') }} TL</span>

                                    <span class="ml-2 text-slate-500">{{ $due->due_date?->format('d.m.Y') }}</span>

                                    @if($due->description)

                                        <div class="text-xs text-slate-400 mt-0.5">{{ $due->description }}</div>

                                    @endif

                                </div>

                                @if($due->remaining_amount < $due->amount)

                                    <span class="text-xs text-amber-600">Kalan: {{ number_format($due->remaining_amount, 2, ',', '.') }} TL</span>

                                @endif

                            </label>

                        @endforeach

                    </div>

                </div>



                <div>

                    <label class="block text-xs font-semibold text-slate-600 mb-2">Hedef Hesap</label>

                    <select name="target_account_id" required

                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">

                        <option value="">— Hesap seçin —</option>

                        @foreach ($transferableAccounts as $ta)

                            @php

                                $taType = match($ta->type) {

                                    App\Models\Account::TYPE_OWNER  => 'Kat Maliki',

                                    App\Models\Account::TYPE_TENANT => 'Kiracı',

                                    default => ''

                                };

                            @endphp

                            <option value="{{ $ta->id }}">{{ $ta->name }} @if($taType)( {{ $taType }})@endif</option>

                        @endforeach

                    </select>

                </div>



                <div class="flex gap-3 pt-1">

                    <button type="submit" class="flex-1 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-600">Devret</button>

                    <button type="button" onclick="document.getElementById('transfer-dues-modal').classList.add('hidden')"

                            class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>

                </div>

            </form>

        </div>

    </div>



    <script>

        document.querySelectorAll('.due-radio').forEach(function(radio) {

            radio.addEventListener('change', function() {

                document.getElementById('transfer-form').action = this.dataset.action;

            });

        });

        document.getElementById('transfer-dues-modal')?.addEventListener('click', function(e) {

            if (e.target === this) this.classList.add('hidden');

        });

    </script>

    @endif



@endsection

