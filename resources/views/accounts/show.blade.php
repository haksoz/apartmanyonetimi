@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">{{ $account->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $account->unit ? $account->unit->unit_no.' no.lu daire' : 'Daire bağlantısı yok' }}</p>
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
                <span title="Toplu gider ödeme özelliği henüz geliştirme aşamasındadır" class="cursor-not-allowed rounded-xl bg-slate-300 px-4 py-2 text-sm font-semibold text-slate-500 select-none">+ Tahsilat Al</span>
                <span title="Giderler menüsünden ekleyin" class="cursor-not-allowed rounded-xl bg-slate-300 px-4 py-2 text-sm font-semibold text-slate-500 select-none">+ Gider Ekle</span>
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

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Borç</div><div class="mt-2 text-2xl font-bold">{{ number_format($account->ledger_debit, 2, ',', '.') }} TL</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Alacak</div><div class="mt-2 text-2xl font-bold">{{ number_format($account->ledger_credit, 2, ',', '.') }} TL</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Bakiye</div><div class="mt-2 text-2xl font-bold">{{ number_format($account->ledger_balance, 2, ',', '.') }} TL</div></div>
    </div>

    {{-- Kullanıcı ve Hesap Bilgileri --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
        <h2 class="mb-4 text-lg font-semibold text-slate-950">Bilgiler</h2>
        @if ($account->user)
            <div class="grid gap-4 md:grid-cols-3 text-sm mb-4">
                <div>
                    <div class="text-xs text-slate-500 mb-1">Ad Soyad</div>
                    <div class="font-semibold text-slate-900">{{ $account->user->name }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 mb-1">E-posta</div>
                    <div class="font-semibold text-slate-900">{{ $account->user->email }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 mb-1">Telefon</div>
                    <div class="font-semibold text-slate-900">{{ $account->user->phone ?: '—' }}</div>
                </div>
            </div>
        @endif
        <div class="grid gap-4 md:grid-cols-2 text-sm mb-4">
            <div>
                <div class="text-xs text-slate-500 mb-1">Portal Erişimi</div>
                <div class="font-semibold text-slate-900">
                    @if ($account->user)
                        Var ({{ $account->user->name }})
                    @else
                        Yok
                    @endif
                </div>
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-2 text-sm">
            <div>
                <div class="text-xs text-slate-500 mb-1">
                    @if ($account->type === App\Models\Account::TYPE_TENANT) Kiracı Giriş Tarihi
                    @else Hesap Açılış Tarihi
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
            @if ($account->account_end_date)
                <div>
                    <div class="text-xs text-slate-500 mb-1">
                        @if ($account->type === App\Models\Account::TYPE_TENANT) Kiracı Çıkış Tarihi
                        @elseif ($account->type === App\Models\Account::TYPE_OWNER) Maliklik Bitiş Tarihi
                        @else Hesap Kapanış Tarihi
                        @endif
                    </div>
                    <div class="font-semibold text-red-600">{{ $account->account_end_date->format('d.m.Y') }}</div>
                </div>
            @endif
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
                        class="hidden rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
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
        // Checkbox & bulk pay
        (function(){
            const selectAll = document.getElementById('select-all-dues');
            const bulkBtn   = document.getElementById('bulk-pay-btn');
            const countEl   = document.getElementById('selected-count');

            if (!selectAll) return;

            const totalEl = document.getElementById('selected-total');

            const updateBtn = () => {
                const checked = document.querySelectorAll('.due-checkbox:checked');
                if (checked.length > 0) {
                    let total = 0;
                    checked.forEach(cb => total += parseFloat(cb.dataset.amount));
                    bulkBtn.classList.remove('hidden');
                    countEl.textContent = checked.length;
                    totalEl.textContent = total.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                } else {
                    bulkBtn.classList.add('hidden');
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
        });

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

    @if ($account->payments->isNotEmpty() || $importedPayments->isNotEmpty())
        <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
            <div class="flex items-center gap-3 mb-4">
                <h2 class="text-lg font-semibold text-slate-950">Dağıtılmamış Ödemeler</h2>
                @if ($importedPayments->isNotEmpty())
                    <label class="flex items-center gap-1.5 cursor-pointer text-xs text-slate-500 select-none">
                        <input type="checkbox" id="show-imported-payments" class="rounded">
                        Devir Öncesini Göster ({{ $importedPayments->count() }})
                    </label>
                @endif
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
                        @foreach ($importedPayments as $payment)
                            <tr class="imported-payment-row hidden bg-blue-50/40">
                                <td class="px-5 py-4 text-slate-700">{{ $payment->id }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-700">
                                    {{ $payment->description ?: 'Ödeme' }}
                                    <span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>
                                </td>
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
                                        <td class="px-5 py-2 text-right">—</td>
                                        <td class="px-5 py-2 text-right text-emerald-600 font-medium tabular-nums">{{ number_format($a->amount,2,',','.') }} TL</td>
                                        <td class="px-5 py-2 text-right">—</td>
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
