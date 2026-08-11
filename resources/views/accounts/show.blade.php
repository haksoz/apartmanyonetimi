@extends('layouts.app')



@section('content')

    <div class="mb-6 flex flex-row items-center justify-between gap-2 md:gap-4 min-w-0">
        <div class="flex items-center gap-2 min-w-0 overflow-x-auto">
            <a href="{{ route('accounts.index') }}" class="shrink-0 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 bg-slate-50 hover:bg-slate-100">
                Hesaplar
            </a>
            <span class="text-slate-400">/</span>
            <a href="{{ route('accounts.show', $account) }}" class="shrink-0 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50">
                @if ($account->unit)
                    Daire {{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}
                @else
                    {{ $account->type_label }}
                @endif
            </a>

            @if (!$account->is_active)
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-1 text-sm font-semibold text-red-600">Pasif</span>
            @endif
        </div>

        {{-- Hesap Sekmeleri --}}
        <div class="flex items-center justify-end gap-2 shrink-0">
            @php
                $showTransfer = in_array($account->type, [App\Models\Account::TYPE_OWNER, App\Models\Account::TYPE_TENANT]) && ($account->dues->isNotEmpty() || $importedDues->isNotEmpty()) && $transferableAccounts->isNotEmpty();
            @endphp
            @include('accounts._tabs', ['account' => $account, 'active' => 'overview', 'withOverview' => false, 'showTransfer' => $showTransfer])
        </div>
    </div>



    {{-- Özet + Bilgiler Birleşik Kart --}}
    <div class="rounded-2xl bg-white shadow-sm mb-6 overflow-hidden">
        {{-- Üst: Bilgiler --}}
        <div class="p-4 md:p-6 border-b border-slate-100">
            {{-- Hesap adı --}}
            <div class="mb-5">
                <div class="text-2xl font-bold text-slate-900">
                    @if ($account->unit)
                        Daire: {{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }} - {{ $account->name }}
                    @else
                        {{ $account->name }}
                    @endif
                </div>
            </div>

            <details class="group">
                <summary class="cursor-pointer list-none inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Detaylar
                </summary>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 text-sm">

                {{-- Daire bilgileri --}}
                @if ($account->unit)
                    @if ($account->unit->floor)
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-xs text-slate-500">Kat</div>
                        <div class="font-semibold text-slate-900">{{ $account->unit->floor }}</div>
                    </div>
                    @endif
                    @if ($account->unit->block)
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-xs text-slate-500">Blok</div>
                        <div class="font-semibold text-slate-900">{{ $account->unit->block }}</div>
                    </div>
                    @endif
                    @if ($account->unit->square_meters)
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-xs text-slate-500">Alan</div>
                        <div class="font-semibold text-slate-900">{{ number_format($account->unit->square_meters, 0, ',', '.') }} m²</div>
                    </div>
                    @endif
                    @if ($account->unit->share_coefficient)
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-xs text-slate-500">Hisse Katsayısı</div>
                        <div class="font-semibold text-slate-900">{{ $account->unit->share_coefficient }}</div>
                    </div>
                    @endif
                @endif

                {{-- Telefon --}}
                @if ($account->phone)
                <div class="flex items-center justify-between gap-2">
                    <div class="text-xs text-slate-500">Telefon</div>
                    <div class="font-semibold text-slate-900">{{ $account->phone }}</div>
                </div>
                @endif

                {{-- E-posta --}}
                @if ($account->email)
                <div class="flex items-center justify-between gap-2">
                    <div class="text-xs text-slate-500">E-posta</div>
                    <div class="font-semibold text-slate-900">{{ $account->email }}</div>
                </div>
                @endif

                {{-- Portal erişimi --}}
                <div class="flex items-center justify-between gap-2">
                    <div class="text-xs text-slate-500">Portal Erişimi</div>
                    <div class="font-semibold text-slate-900">{{ $account->user ? 'Var' : 'Yok' }}</div>
                </div>

                {{-- Açılış tarihi --}}
                <div class="flex items-center justify-between gap-2">
                    <div class="text-xs text-slate-500">
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
                <div class="flex items-center justify-between gap-2">
                    <div class="text-xs text-slate-500">
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
                <div class="flex items-center justify-between gap-2">
                    <div class="text-xs text-slate-500">E-posta</div>
                    <div class="font-semibold text-slate-900">{{ $account->user->email }}</div>
                </div>
                @if ($account->user->phone)
                <div class="flex items-center justify-between gap-2">
                    <div class="text-xs text-slate-500">Telefon</div>
                    <div class="font-semibold text-slate-900">{{ $account->user->phone }}</div>
                </div>
                @endif
                @endif

            </div>
        </details>
    </div>

    {{-- Alt: Borç / Alacak / Bakiye --}}
        <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-slate-100">
            <div class="py-3 px-4 md:p-5 flex items-center justify-between gap-1">
                <div class="text-[10px] md:text-xs font-semibold uppercase tracking-wide text-slate-400">Borç</div>
                <div class="text-lg md:text-xl font-bold text-red-600 tabular-nums">{{ number_format($account->ledger_debit, 2, ',', '.') }} TL</div>
            </div>
            <div class="py-3 px-4 md:p-5 flex items-center justify-between gap-1">
                <div class="text-[10px] md:text-xs font-semibold uppercase tracking-wide text-slate-400">Alacak</div>
                <div class="text-lg md:text-xl font-bold text-emerald-600 tabular-nums">{{ number_format($account->ledger_credit, 2, ',', '.') }} TL</div>
            </div>
            <div class="py-3 px-4 md:p-5 flex items-center justify-between gap-1">
                <div class="text-[10px] md:text-xs font-semibold uppercase tracking-wide text-slate-400">Bakiye</div>
                @php $balance = $account->ledger_balance; @endphp
                <div class="text-lg md:text-xl font-bold tabular-nums {{ $balance < 0 ? 'text-red-600' : ($balance > 0 ? 'text-emerald-600' : 'text-slate-400') }}">
                    {{ number_format(abs($balance), 2, ',', '.') }} TL
                    <span class="text-xs font-normal">{{ $balance < 0 ? '(B)' : ($balance > 0 ? '(A)' : '') }}</span>
                </div>
            </div>
        </div>
    </div>



    @if (!in_array($account->type, [App\Models\Account::TYPE_SUPPLIER]))

        <div class="rounded-2xl bg-white p-4 md:p-6 shadow-sm mb-6">

            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">

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

                        class="rounded-xl px-4 py-2 text-sm font-semibold transition-colors bg-slate-200 text-slate-400 cursor-not-allowed w-full md:w-auto" disabled>

                        Seçilenleri Tahsil Et &mdash; <span id="selected-count">0</span> aidat / <span id="selected-total">0,00</span> TL

                    </button>

                @endif

            </div>

            @if ($account->dues->isEmpty() && $importedDues->isEmpty())

                <div class="py-6 text-sm text-slate-500">Bu hesap için ödenmemiş aidat yok.</div>

            @else

                <div class="overflow-hidden rounded-2xl border border-slate-200">

                    <table class="hidden md:table min-w-full divide-y divide-slate-200 text-sm">

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

                                <tr class="cursor-pointer hover:bg-slate-50" data-href="{{ route('dues.show', $due) }}">

                                    <td class="px-5 py-4">

                                        <input type="checkbox" class="due-checkbox due-checkbox-desktop rounded"

                                               data-due-id="{{ $due->id }}"

                                               data-amount="{{ $due->remaining_amount }}"

                                               data-description="{{ $due->description ?: 'Aidat' }}">

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

                                        <button type="button"
                                                onclick="openDuePaymentModal({{ $due->id }}, {{ $due->remaining_amount }}, '{{ addslashes($due->description ?: 'Aidat') }}', '{{ addslashes($due->account?->name ?: '-') }}', '{{ $due->unit?->unit_no ? str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-' }}')"
                                                class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                            Tahsil Et
                                        </button>

                                    </td>

                                </tr>

                            @endforeach

                            {{-- Devir Öncesi Aidatlar (toggle ile gösterilir) --}}

                            @foreach ($importedDues as $due)

                                <tr class="imported-due-row hidden bg-blue-50/40 cursor-pointer hover:bg-blue-100/40" data-href="{{ route('dues.show', $due) }}">

                                    <td class="px-5 py-4">

                                        <input type="checkbox" class="due-checkbox due-checkbox-desktop rounded"

                                               data-due-id="{{ $due->id }}"

                                               data-amount="{{ $due->remaining_amount }}"

                                               data-description="{{ $due->description ?: 'Aidat' }}">

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

                                        <button type="button"
                                                onclick="openDuePaymentModal({{ $due->id }}, {{ $due->remaining_amount }}, '{{ addslashes($due->description ?: 'Aidat') }}', '{{ addslashes($due->account?->name ?: '-') }}', '{{ $due->unit?->unit_no ? str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-' }}')"
                                                class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                            Tahsil Et
                                        </button>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                    {{-- Mobil: Açık Aidatlar Kartları --}}
                    <div class="md:hidden divide-y divide-slate-100 rounded-2xl border border-slate-200">
                        @foreach ($account->dues as $due)
                            @php
                                $statusLabel = match($due->status) {
                                    'paid'    => 'Ödendi',
                                    'partial' => 'Kısmi Ödendi',
                                    'overdue' => 'Gecikmiş',
                                    default   => 'Bekliyor',
                                };
                            @endphp
                            <div class="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer hover:bg-slate-50"
                                 onclick="window.location.href='{{ route('dues.show', $due) }}'">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <input type="checkbox" class="due-checkbox due-checkbox-mobile rounded shrink-0"
                                           data-due-id="{{ $due->id }}"
                                           data-amount="{{ $due->remaining_amount }}"
                                           data-description="{{ $due->description ?: 'Aidat' }}"
                                           onclick="event.stopPropagation()">
                                    <div class="min-w-0">
                                        <div class="text-xs text-slate-500">{{ $due->due_date->format('d.m.Y') }}</div>
                                        <div class="text-sm text-slate-700 truncate">
                                            {{ \Illuminate\Support\Str::limit($due->description ?: 'Aidat', 30) }}
                                            @if ($due->status === 'partial')
                                                <span class="text-xs text-slate-400 block">Toplam: {{ number_format($due->amount, 2, ',', '.') }} TL</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right text-sm">
                                    <div class="font-semibold text-slate-900">{{ number_format($due->remaining_amount, 2, ',', '.') }} TL</div>
                                    <div class="text-xs {{ $due->status === 'partial' ? 'text-amber-500' : 'text-amber-600' }}">{{ $statusLabel }}</div>
                                </div>
                            </div>
                        @endforeach

                        @foreach ($importedDues as $due)
                            @php
                                $statusLabel = match($due->status) {
                                    'paid'    => 'Ödendi',
                                    'partial' => 'Kısmi Ödendi',
                                    'overdue' => 'Gecikmiş',
                                    default   => 'Bekliyor',
                                };
                            @endphp
                            <div class="imported-due-row-mobile hidden flex items-center justify-between gap-3 px-4 py-3 cursor-pointer hover:bg-blue-100/40 bg-blue-50/40"
                                 onclick="window.location.href='{{ route('dues.show', $due) }}'">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <input type="checkbox" class="due-checkbox due-checkbox-mobile rounded shrink-0"
                                           data-due-id="{{ $due->id }}"
                                           data-amount="{{ $due->remaining_amount }}"
                                           data-description="{{ $due->description ?: 'Aidat' }}"
                                           onclick="event.stopPropagation()">
                                    <div class="min-w-0">
                                        <div class="text-xs text-slate-500">{{ $due->due_date->format('d.m.Y') }}</div>
                                        <div class="text-sm text-slate-700 truncate">
                                            {{ \Illuminate\Support\Str::limit($due->description ?: 'Aidat', 30) }}
                                            <span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right text-sm">
                                    <div class="font-semibold text-slate-900">{{ number_format($due->remaining_amount, 2, ',', '.') }} TL</div>
                                    <div class="text-xs text-amber-600">{{ $statusLabel }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

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

                            <input type="date" id="bulk-payment-date" name="payment_date" required value="{{ now()->toDateString() }}"

                                   class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">

                        </div>

                        <div>

                            <label class="block text-xs font-medium text-slate-600 mb-1.5">Açıklama <span class="text-slate-400">(opsiyonel)</span></label>

                            <input type="text" name="description" id="bulk-description" placeholder="Çoklu Aidat Tahsilatı"

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

        @include('dues._payment_modal', ['cashBoxes' => $cashBoxes])

    @endif



    @if (!in_array($account->type, [App\Models\Account::TYPE_SUPPLIER]) && ($account->payments->isNotEmpty() || $importedPayments->isNotEmpty()))

        <div class="rounded-2xl bg-white p-4 md:p-6 shadow-sm mb-6">

            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">

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
                        class="rounded-xl px-4 py-2 text-sm font-semibold transition-colors bg-slate-200 text-slate-400 cursor-not-allowed w-full md:w-auto" disabled>

                        Seçilenleri Aidata Bağla &mdash; <span id="selected-payment-count">0</span> tahsilat / <span id="selected-payment-total">0,00</span> TL

                    </button>

                </form>

            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200">

                <table class="hidden md:table min-w-full divide-y divide-slate-200 text-sm">

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

                                    <input type="checkbox" class="payment-checkbox payment-checkbox-desktop rounded"

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

                                    <input type="checkbox" class="payment-checkbox payment-checkbox-desktop rounded"

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

                {{-- Mobil: Açık Tahsilatlar Kartları --}}
                <div class="md:hidden divide-y divide-slate-100 rounded-2xl border border-slate-200">
                    @foreach ($account->payments as $payment)
                        <div class="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer hover:bg-slate-50"
                             onclick="window.location.href='{{ route('payments.show', $payment) }}'">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <input type="checkbox" class="payment-checkbox payment-checkbox-mobile rounded shrink-0"
                                       data-payment-id="{{ $payment->id }}"
                                       data-amount="{{ $payment->unallocated_amount }}"
                                       onclick="event.stopPropagation()">
                                <div class="min-w-0">
                                    <div class="text-xs text-slate-500">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</div>
                                    <div class="text-sm text-slate-700 truncate">
                                        {{ \Illuminate\Support\Str::limit($payment->description ?: 'Ödeme', 30) }}
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0 text-right text-sm">
                                <div class="font-semibold text-slate-900">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</div>
                            </div>
                        </div>
                    @endforeach

                    @foreach ($importedPayments as $payment)
                        <div class="imported-payment-row-mobile hidden flex items-center justify-between gap-3 px-4 py-3 cursor-pointer hover:bg-blue-100/40 bg-blue-50/40"
                             onclick="window.location.href='{{ route('payments.show', $payment) }}'">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <input type="checkbox" class="payment-checkbox payment-checkbox-mobile rounded shrink-0"
                                       data-payment-id="{{ $payment->id }}"
                                       data-amount="{{ $payment->unallocated_amount }}"
                                       onclick="event.stopPropagation()">
                                <div class="min-w-0">
                                    <div class="text-xs text-slate-500">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</div>
                                    <div class="text-sm text-slate-700 truncate">
                                        {{ \Illuminate\Support\Str::limit($payment->description ?: 'Ödeme', 30) }}
                                        <span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0 text-right text-sm">
                                <div class="font-semibold text-slate-900">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</div>
                            </div>
                        </div>
                    @endforeach
                </div>

            </div>

        </div>

    @endif





    @if ($account->type === App\Models\Account::TYPE_SUPPLIER && $account->expenses->isNotEmpty())

        <div class="rounded-2xl bg-white p-4 md:p-6 shadow-sm mb-6">

            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">

                <h2 class="text-lg font-semibold text-slate-950">Açık Giderler</h2>

                <div class="flex items-center gap-3 flex-wrap">

                    <input type="hidden" id="multi-expense-pay-ids">
                    <button type="button" id="multi-expense-pay-btn"
                        onclick="openMultiExpensePayFromSelection({{ $account->id }})"
                        class="rounded-xl px-4 py-2 text-sm font-semibold transition-colors bg-slate-200 text-slate-400 cursor-not-allowed" disabled>
                        Seçilileri Öde &mdash; <span id="selected-expense-count">0</span> gider / <span id="selected-expense-total">0,00</span> TL
                    </button>

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

                            <tr class="cursor-pointer hover:bg-slate-50" data-href="{{ route('expenses.show', $expense) }}">

                                <td class="px-4 py-4">
                                    <input type="checkbox" class="expense-checkbox rounded"
                                        data-expense-id="{{ $expense->id }}"
                                        data-amount="{{ $expense->amount }}">
                                </td>

                                <td class="px-5 py-4 text-slate-700">{{ $expense->expense_date?->format('d.m.Y') ?? ($expense->period_month?->format('d.m.Y') ?? '-') }}</td>

                                <td class="px-5 py-4 text-slate-700">{{ $expense->description ?: $expense->category }}</td>

                                <td class="px-5 py-4 text-right text-slate-900 font-semibold">{{ number_format($expense->amount, 2, ',', '.') }} TL</td>

                                <td class="px-5 py-4 text-right">

                                    <button type="button"
                                            onclick="openExpensePaymentModal({{ $expense->id }}, {{ $expense->amount }}, '{{ addslashes($expense->description ?: $expense->category) }}', '{{ addslashes($expense->category ?: '-') }}', '{{ addslashes($expense->account?->name ?: '-') }}')"
                                            class="rounded-xl bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                        Ödeme Yap
                                    </button>

                                </td>

                            </tr>

                        @endforeach


                    </tbody>

                </table>

            </div>

        </div>

        @include('expenses._payment_modal', ['cashBoxes' => $cashBoxes])

    @endif



    @if ($account->type === App\Models\Account::TYPE_SUPPLIER && ($account->payments->isNotEmpty() || $importedPayments->isNotEmpty()))

        <div class="rounded-2xl bg-white p-4 md:p-6 shadow-sm mb-6">

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



            const isDesktop = () => window.innerWidth >= 768;

            const dueCheckboxSelector = () => isDesktop() ? '.due-checkbox-desktop' : '.due-checkbox-mobile';

            function visibleDueChecked() {
                return document.querySelectorAll(dueCheckboxSelector() + ':checked');
            }

            function syncDueCheckboxes(source) {
                const dueId = source.dataset.dueId;
                document.querySelectorAll('.due-checkbox[data-due-id="' + dueId + '"]').forEach(cb => {
                    if (cb !== source) cb.checked = source.checked;
                });
            }

            const updateBtn = () => {

                const checked = visibleDueChecked();

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

                    syncDueCheckboxes(this);

                    const allDesktop = document.querySelectorAll('.due-checkbox-desktop');

                    selectAll.checked = Array.from(allDesktop).every(c => c.checked);

                    selectAll.indeterminate = !selectAll.checked && Array.from(allDesktop).some(c => c.checked);

                    updateBtn();

                });

            });

            window.addEventListener('resize', updateBtn);
        })();



        const bulkMonthNames = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

        const formatBulkDescription = (dateString) => {
            if (!dateString) return '';
            const [year, month] = dateString.split('-');
            if (!year || !month) return '';
            return `${year} ${bulkMonthNames[parseInt(month, 10) - 1]} Toplu Borç Tahsilatı`;
        };

        const isAutoBulkDescription = (current, dateString) => {
            if (!current) return true;
            return current === formatBulkDescription(dateString);
        };

        document.getElementById('bulk-payment-date')?.addEventListener('change', function() {
            const descEl = document.getElementById('bulk-description');
            if (descEl && isAutoBulkDescription(descEl.value.trim(), this.value)) {
                descEl.value = formatBulkDescription(this.value);
            }
        });



        function openBulkPayModal() {

            const checked = document.querySelectorAll(window.innerWidth >= 768 ? '.due-checkbox-desktop:checked' : '.due-checkbox-mobile:checked');

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



            const descEl = document.getElementById('bulk-description');
            const paymentDateEl = document.getElementById('bulk-payment-date');
            if (descEl && paymentDateEl && isAutoBulkDescription(descEl.value.trim(), paymentDateEl.value)) {
                descEl.value = formatBulkDescription(paymentDateEl.value);
            }

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

            document.querySelectorAll('tr[data-href]').forEach(function(row) {

                row.addEventListener('click', function(e) {

                    if (e.target.closest('td:first-child') || e.target.closest('td:last-child')) return;

                    window.location.href = row.dataset.href;

                });

            });



            document.getElementById('show-imported-dues')?.addEventListener('change', function() {

                document.querySelectorAll('.imported-due-row, .imported-due-row-mobile').forEach(r => r.classList.toggle('hidden', !this.checked));

            });



            document.getElementById('show-imported-payments')?.addEventListener('change', function() {

                document.querySelectorAll('.imported-payment-row, .imported-payment-row-mobile').forEach(r => r.classList.toggle('hidden', !this.checked));

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



            const isDesktopPaymentView = () => window.innerWidth >= 768;

            const paymentCheckboxSelector = () => isDesktopPaymentView() ? '.payment-checkbox-desktop' : '.payment-checkbox-mobile';

            const visiblePaymentCheckboxes = () => document.querySelectorAll(paymentCheckboxSelector());

            const visibleCheckedPayments = () => document.querySelectorAll(paymentCheckboxSelector() + ':checked');

            function syncPaymentCheckboxes(source) {

                const paymentId = source.dataset.paymentId;

                document.querySelectorAll('.payment-checkbox[data-payment-id="' + paymentId + '"]').forEach(cb => {

                    if (cb !== source) cb.checked = source.checked;

                });

            }

            const updatePayBtn = () => {

                const checked = visibleCheckedPayments();

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

                    syncPaymentCheckboxes(this);

                    const all = visiblePaymentCheckboxes();

                    selectAllPay.checked = all.length > 0 && Array.from(all).every(c => c.checked);

                    selectAllPay.indeterminate = !selectAllPay.checked && Array.from(all).some(c => c.checked);

                    updatePayBtn();

                });

            });

            window.addEventListener('resize', function() {

                const all = visiblePaymentCheckboxes();

                selectAllPay.checked = all.length > 0 && Array.from(all).every(c => c.checked);

                selectAllPay.indeterminate = !selectAllPay.checked && Array.from(all).some(c => c.checked);

                updatePayBtn();

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

        // Tekli aidat tahsilatı popup
        function openDuePaymentModal(dueId, amount, description, accountName, unitNo) {
            const modal = document.getElementById('due-payment-modal');
            const form = document.getElementById('due-payment-form');
            if (!modal || !form) return;

            form.action = (form.dataset.baseUrl || '').replace('__DUE_ID__', dueId);
            document.getElementById('due-payment-due-id').value = dueId;
            document.getElementById('due-payment-amount-input').value = amount;
            document.getElementById('due-payment-description').textContent = description || 'Aidat';
            document.getElementById('due-payment-amount').textContent = amount.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' TL';
            document.getElementById('due-payment-account').textContent = 'No:' + (unitNo || '-') + ' ' + (accountName || '-');

            const descInput = document.getElementById('due-payment-description-input');
            if (descInput) {
                descInput.value = (description ? description + ' Tahsilatı' : 'Aidat Tahsilatı');
            }

            modal.classList.remove('hidden');
        }

        function closeDuePaymentModal() {
            document.getElementById('due-payment-modal')?.classList.add('hidden');
        }

        document.getElementById('due-payment-modal')?.addEventListener('click', function(e) {
            if (e.target === this) closeDuePaymentModal();
        });

        // Gider ödemesi popup
        const expenseMonthNames = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

        function formatMultiExpenseDescription(dateString) {
            if (!dateString) return '';
            const [year, month] = dateString.split('-');
            if (!year || !month) return '';
            return `${year} ${expenseMonthNames[parseInt(month, 10) - 1]} Toplu Ödeme`;
        }

        function isAutoExpenseDescription(current, description) {
            if (!current) return true;
            return current === (description ? description + ' ödemesi' : 'Gider ödemesi');
        }

        function isAutoMultiExpenseDescription(current, dateString) {
            if (!current) return true;
            return current === formatMultiExpenseDescription(dateString);
        }

        function openExpensePaymentModal(expenseId, amount, description, category, supplier) {
            const modal = document.getElementById('expense-payment-modal');
            const form = document.getElementById('expense-payment-form');
            if (!modal || !form) return;

            form.action = (form.dataset.baseUrlSingle || '').replace('__EXPENSE_ID__', expenseId);
            document.getElementById('expense-payment-expense-id').value = expenseId;
            document.getElementById('expense-payment-amount-input').value = amount;
            document.getElementById('expense-payment-expense-ids').value = '';

            document.getElementById('expense-payment-modal-title').textContent = 'Gider Ödemesi';
            document.getElementById('expense-payment-modal-subtitle').textContent = 'Gider ödemesi yapın.';
            document.getElementById('expense-payment-info-single').classList.remove('hidden');
            document.getElementById('expense-payment-info-multi').classList.add('hidden');

            document.getElementById('expense-payment-description').textContent = description || 'Gider';
            document.getElementById('expense-payment-category').textContent = category || '-';
            document.getElementById('expense-payment-amount').textContent = amount.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' TL';
            document.getElementById('expense-payment-supplier').textContent = supplier || '-';

            const descInput = document.getElementById('expense-payment-description-input');
            if (descInput && isAutoExpenseDescription(descInput.value.trim(), description)) {
                descInput.value = description ? description + ' ödemesi' : 'Gider ödemesi';
            }

            modal.classList.remove('hidden');
        }

        function openMultiExpensePayFromSelection(accountId) {
            const checked = document.querySelectorAll('.expense-checkbox:checked');
            if (!checked.length) return;

            const expenses = [];
            checked.forEach(cb => {
                const row = cb.closest('tr');
                const descCell = row ? row.querySelector('td:nth-child(3)') : null;
                expenses.push({
                    id: cb.dataset.expenseId,
                    amount: parseFloat(cb.dataset.amount),
                    description: descCell ? descCell.textContent.trim() : 'Gider'
                });
            });

            openMultiExpensePaymentModal(accountId, expenses);
        }

        function openMultiExpensePaymentModal(accountId, expenses) {
            const modal = document.getElementById('expense-payment-modal');
            const form = document.getElementById('expense-payment-form');
            if (!modal || !form) return;

            form.action = (form.dataset.baseUrlMulti || '').replace('__ACCOUNT_ID__', accountId);
            document.getElementById('expense-payment-expense-id').value = '';
            document.getElementById('expense-payment-amount-input').value = '';
            document.getElementById('expense-payment-expense-ids').value = expenses.map(e => e.id).join(',');

            document.getElementById('expense-payment-modal-title').textContent = 'Seçili Giderleri Öde';
            document.getElementById('expense-payment-modal-subtitle').textContent = expenses.length + ' gider seçildi';
            document.getElementById('expense-payment-info-single').classList.add('hidden');
            document.getElementById('expense-payment-info-multi').classList.remove('hidden');

            const list = document.getElementById('expense-payment-multi-list');
            list.innerHTML = '';
            let total = 0;
            expenses.forEach(e => {
                total += e.amount;
                const div = document.createElement('div');
                div.className = 'py-2 flex justify-between text-sm';
                div.innerHTML = `<span class="text-slate-700 truncate pr-2">${e.description || 'Gider'}</span><span class="font-semibold text-slate-900 whitespace-nowrap">${e.amount.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} TL</span>`;
                list.appendChild(div);
            });
            document.getElementById('expense-payment-multi-total').textContent = total.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' TL';

            const descInput = document.getElementById('expense-payment-description-input');
            const dateInput = document.getElementById('expense-payment-date');
            if (descInput && isAutoMultiExpenseDescription(descInput.value.trim(), dateInput.value)) {
                descInput.value = formatMultiExpenseDescription(dateInput.value);
            }

            modal.classList.remove('hidden');
        }

        function closeExpensePaymentModal() {
            document.getElementById('expense-payment-modal')?.classList.add('hidden');
        }

        document.getElementById('expense-payment-date')?.addEventListener('change', function() {
            const descInput = document.getElementById('expense-payment-description-input');
            if (!descInput) return;
            const mode = document.getElementById('expense-payment-expense-ids').value ? 'multi' : 'single';
            if (mode === 'multi' && isAutoMultiExpenseDescription(descInput.value.trim(), this.value)) {
                descInput.value = formatMultiExpenseDescription(this.value);
            }
        });

        document.getElementById('expense-payment-modal')?.addEventListener('click', function(e) {
            if (e.target === this) closeExpensePaymentModal();
        });

    </script>






    <div class="rounded-2xl bg-white p-4 md:p-6 shadow-sm">

        <h2 class="mb-4 text-lg font-semibold text-slate-950">Son Hareketler</h2>

        @if ($transactions->isEmpty())

            <div class="py-6 text-sm text-slate-500">Henüz hareket yok.</div>

        @else

            <div class="overflow-hidden rounded-2xl border border-slate-200">

                <table class="hidden md:table min-w-full divide-y divide-slate-200 text-sm">

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

                {{-- Mobil: Son Hareketler Kartları --}}
                <div class="md:hidden divide-y divide-slate-100">
                    @foreach ($transactions as $t)
                        @php
                            $debit = $t->type === 'debit' ? $t->amount : 0;
                            $credit = $t->type === 'credit' ? $t->amount : 0;
                            $detailUrl = null;
                            if(($t->transactionable_type ?? '') === \App\Models\Payment::class && $t->transactionable_id)
                                $detailUrl = route('payments.show', $t->transactionable_id);
                            elseif(($t->transactionable_type ?? '') === \App\Models\Expense::class && $t->transactionable_id)
                                $detailUrl = route('expenses.show', $t->transactionable_id);
                            elseif(($t->transactionable_type ?? '') === \App\Models\Due::class && $t->transactionable_id)
                                $detailUrl = route('dues.show', $t->transactionable_id);
                        @endphp

                        <div class="flex items-center justify-between gap-3 px-4 py-3 {{ $detailUrl ? 'cursor-pointer hover:bg-slate-50' : '' }}"
                             @if($detailUrl) onclick="window.location.href='{{ $detailUrl }}'" @endif>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs text-slate-500 mb-0.5">{{ $t->transaction_date->format('d.m.Y') }}</div>
                                <div class="text-sm text-slate-700 truncate">
                                    {{ \Illuminate\Support\Str::limit($t->description ?: ucfirst($t->type), 30) }}
                                </div>
                            </div>
                            <div class="shrink-0 text-sm">
                                @if ($debit)
                                    <span class="font-semibold text-red-600">-{{ number_format($debit,2,',','.') }} TL</span>
                                @elseif ($credit)
                                    <span class="font-semibold text-emerald-600">+{{ number_format($credit,2,',','.') }} TL</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

            </div>

        @endif

    </div>



    {{-- Borç Devri Modal --}}

    @if (in_array($account->type, [App\Models\Account::TYPE_OWNER, App\Models\Account::TYPE_TENANT]) && ($account->dues->isNotEmpty() || $importedDues->isNotEmpty()) && $transferableAccounts->isNotEmpty())

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
                            @php
                                $isPartial = $due->remaining_amount < $due->amount && $due->remaining_amount > 0;
                            @endphp

                            <label class="flex items-center gap-3 px-4 py-3 {{ $isPartial ? 'cursor-not-allowed bg-slate-50 text-slate-400' : 'cursor-pointer hover:bg-slate-50' }}">

                                <input type="radio" name="due_id" value="{{ $due->id }}"

                                       data-action="{{ $isPartial ? '' : route('dues.transfer', $due) }}"

                                       class="due-radio" {{ $isPartial ? 'disabled' : 'required' }}>

                                <div class="flex-1 text-sm">

                                    <span class="font-medium {{ $isPartial ? 'text-slate-500' : 'text-slate-900' }}">{{ number_format($due->amount, 2, ',', '.') }} TL</span>

                                    <span class="ml-2 text-slate-500">{{ $due->due_date?->format('d.m.Y') }}</span>

                                    @if($due->description)

                                        <div class="text-xs text-slate-400 mt-0.5">{{ $due->description }}</div>

                                    @endif

                                </div>

                                @if($isPartial)

                                    <span class="text-xs text-amber-600">Kısmen ödenmiş aidat devredilemez</span>

                                @elseif($due->remaining_amount < $due->amount)

                                    <span class="text-xs text-amber-600">Kalan: {{ number_format($due->remaining_amount, 2, ',', '.') }} TL</span>

                                @endif

                            </label>

                        @endforeach

                        @foreach ($importedDues as $due)
                            @php
                                $isPartial = $due->remaining_amount < $due->amount && $due->remaining_amount > 0;
                            @endphp

                            <label class="flex items-center gap-3 px-4 py-3 {{ $isPartial ? 'cursor-not-allowed bg-slate-50 text-slate-400' : 'cursor-pointer hover:bg-slate-50 bg-blue-50/40' }}">

                                <input type="radio" name="due_id" value="{{ $due->id }}"

                                       data-action="{{ $isPartial ? '' : route('dues.transfer', $due) }}"

                                       class="due-radio" {{ $isPartial ? 'disabled' : 'required' }}>

                                <div class="flex-1 text-sm">

                                    <span class="font-medium {{ $isPartial ? 'text-slate-500' : 'text-slate-900' }}">{{ number_format($due->amount, 2, ',', '.') }} TL</span>

                                    <span class="ml-2 text-slate-500">{{ $due->due_date?->format('d.m.Y') }}</span>

                                    @if($due->description)

                                        <div class="text-xs text-slate-400 mt-0.5">{{ $due->description }}</div>

                                    @endif

                                    <div class="text-xs {{ $isPartial ? 'text-blue-500' : 'text-blue-700' }}">Devir Öncesi</div>

                                </div>

                                @if($isPartial)

                                    <span class="text-xs text-amber-600">Kısmen ödenmiş aidat devredilemez</span>

                                @elseif($due->remaining_amount < $due->amount)

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

                            class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>

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

