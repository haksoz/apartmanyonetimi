@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Gider Düzenle</h1>
            <p class="mt-1 text-sm text-slate-500">Hesap, kategori, tutar ve tarih bilgilerini güncelleyin.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('expenses.show', $expense) }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Detaya Dön</a>
        </div>
    </div>

    @if ($expense->is_paid)
    <div class="mb-4 rounded-2xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3">
        <svg class="h-5 w-5 text-amber-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <div class="text-sm text-amber-800">
            <span class="font-semibold">Bu gider ödenmiştir.</span>
            Tutar, hesap ve ödeme durumu değiştirilemez. Yalnızca açıklama, dönem ve tarih bilgileri düzenlenebilir.
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('expenses.update', $expense) }}" class="space-y-4">
        @csrf
        @method('PUT')

        {{-- Hesap & Gider Bilgisi --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Hesap &amp; Gider Bilgisi</h3>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="account_id" class="mb-2 block text-sm font-medium text-slate-600">Hesap / Tedarikçi</label>
                    @if ($expense->is_paid)
                        <input type="hidden" name="account_id" value="{{ $expense->account_id }}">
                        <div class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-500 cursor-not-allowed">{{ $expense->account?->name ?? 'Hesap seçilmedi' }}</div>
                    @else
                        <select id="account_id" name="account_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                            @if (!$expense->account_id)
                                <option value="">Hesap seçmeden kaydet</option>
                            @endif
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('account_id', $expense->account_id) === (string) $account->id)>{{ $account->name }} ({{ $account->type_label }})</option>
                            @endforeach
                        </select>
                        @error('account_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                    @endif
                </div>

                <div>
                    <label for="category_id" class="mb-2 block text-sm font-medium text-slate-600">Kategori</label>
                    <select id="category_id" name="category_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        <option value="">Kategori seçin</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id', $expense->category_id) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="amount" class="mb-2 block text-sm font-medium text-slate-600">Tutar</label>
                    @if ($expense->is_paid)
                        <input type="hidden" name="amount" value="{{ $expense->amount }}">
                        <div class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-500 cursor-not-allowed">{{ number_format($expense->amount, 2, ',', '.') }} TL</div>
                    @else
                        <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $expense->amount) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        @error('amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                    @endif
                </div>

                <div>
                    <label for="period_month" class="mb-2 block text-sm font-medium text-slate-600">Dönem</label>
                    <input id="period_month" name="period_month" type="month" value="{{ old('period_month', $expense->period_month?->format('Y-m')) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('period_month')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="mb-2 block text-sm font-medium text-slate-600">Açıklama</label>
                    <input id="description" name="description" value="{{ old('description', $expense->description) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none" placeholder="Fatura no, dönem veya kısa açıklama">
                    @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Tarih Bilgileri --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Tarih Bilgileri</h3>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="expense_date" class="mb-2 block text-sm font-medium text-slate-600">Gider Tarihi</label>
                    <input id="expense_date" name="expense_date" type="date" value="{{ old('expense_date', $expense->expense_date?->format('Y-m-d')) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('expense_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="due_date" class="mb-2 block text-sm font-medium text-slate-600">Son Ödeme Tarihi <span class="font-normal text-slate-400">(opsiyonel)</span></label>
                    <input id="due_date" name="due_date" type="date" value="{{ old('due_date', $expense->due_date?->format('Y-m-d')) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('due_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Ödeme Bilgisi --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Ödeme Bilgisi</h3>
            @if ($expense->is_paid)
                <input type="hidden" name="is_paid" value="1">
                @php $paymentTx = $expense->transactions->firstWhere('type', 'debit'); @endphp
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm">
                    <div class="flex items-center gap-2 font-semibold text-emerald-700 mb-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Ödendi
                    </div>
                    @if ($paymentTx)
                        <div class="text-slate-600">Ödeme Tarihi: <span class="font-medium text-slate-900">{{ $paymentTx->transaction_date->format('d.m.Y') }}</span></div>
                    @endif
                </div>
            @else
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700 cursor-pointer">
                    <input type="checkbox" name="is_paid" value="1" @checked(old('is_paid', $expense->is_paid)) class="rounded border-slate-300">
                    Bu gider ödendi
                </label>
            @endif
        </div>

        <div class="flex justify-end">
            <button type="submit" class="w-full md:w-auto rounded-xl bg-slate-950 px-8 py-3 text-sm font-semibold text-white hover:bg-slate-800">Güncelle</button>
        </div>
    </form>

    <script>
        (() => {
            const expenseDateInput = document.getElementById('expense_date');
            const dueDateInput = document.getElementById('due_date');
            const periodInput = document.getElementById('period_month');
            const categorySelect = document.getElementById('category_id');
            const descriptionInput = document.getElementById('description');
            let isDueDateManuallySet = !!dueDateInput?.value;

            const months = {
                '01': 'Ocak', '02': 'Şubat', '03': 'Mart', '04': 'Nisan',
                '05': 'Mayıs', '06': 'Haziran', '07': 'Temmuz', '08': 'Ağustos',
                '09': 'Eylül', '10': 'Ekim', '11': 'Kasım', '12': 'Aralık'
            };

            const syncPeriodFromDate = () => {
                const dateVal = expenseDateInput?.value;
                if (dateVal) {
                    periodInput.value = dateVal.substring(0, 7);
                }
            };

            const syncPeriodFromDueDate = () => {
                const dateVal = dueDateInput?.value;
                if (dateVal && isDueDateManuallySet) {
                    periodInput.value = dateVal.substring(0, 7);
                }
            };

            const syncDueDate = () => {
                const dateVal = expenseDateInput?.value;
                if (dateVal && !isDueDateManuallySet) {
                    const dueDate = new Date(dateVal);
                    dueDate.setDate(dueDate.getDate() + 15);
                    const year = dueDate.getFullYear();
                    const month = String(dueDate.getMonth() + 1).padStart(2, '0');
                    const day = String(dueDate.getDate()).padStart(2, '0');
                    dueDateInput.value = `${year}-${month}-${day}`;
                }
            };

            const updateDescription = () => {
                if (descriptionInput.value && descriptionInput.dataset.userEdited) return;
                const period = periodInput.value;
                const categoryOption = categorySelect.options[categorySelect.selectedIndex];
                const categoryName = categoryOption ? categoryOption.text : '';

                if (period && categoryName) {
                    const [year, month] = period.split('-');
                    const monthName = months[month] || month;
                    descriptionInput.value = `${year} ${monthName} - ${categoryName}`;
                }
            };

            expenseDateInput?.addEventListener('change', () => {
                syncPeriodFromDate();
                syncDueDate();
                updateDescription();
            });

            dueDateInput?.addEventListener('input', () => {
                isDueDateManuallySet = dueDateInput.value !== '';
                syncPeriodFromDueDate();
                updateDescription();
            });

            categorySelect?.addEventListener('change', () => {
                descriptionInput.dataset.userEdited = '';
                updateDescription();
            });

            descriptionInput?.addEventListener('input', () => {
                descriptionInput.dataset.userEdited = '1';
            });

            // Init
            syncPeriodFromDate();
            syncDueDate();
        })();
    </script>
@endsection
