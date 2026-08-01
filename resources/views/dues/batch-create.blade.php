@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Toplu Borçlandır</h1>
            <p class="mt-1 text-sm text-slate-500">Dönem giderlerinden veya manuel toplam tutar ile tüm dairelere borçlandırma oluşturun.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('dues.index') }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Aidatlara Dön</a>
        </div>
    </div>

    <form method="POST" action="{{ route('dues.store') }}" class="space-y-4">
        @csrf

        {{-- Source Selection - Side by Side with Icons --}}
        <div class="flex gap-3">
            <label class="cursor-pointer flex-1">
                <input type="radio" name="source_type" value="fixed" class="peer sr-only" @checked(old('source_type', 'fixed') === 'fixed')>
                <div class="rounded-xl border-2 border-slate-200 p-4 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="font-semibold text-slate-800 peer-checked:text-emerald-700">Sabit Tutar</div>
                            <div class="text-xs text-slate-500">Daire başına sabit tutar</div>
                        </div>
                    </div>
                </div>
            </label>
            <label class="cursor-pointer flex-1">
                <input type="radio" name="source_type" value="manual" class="peer sr-only" @checked(old('source_type') === 'manual')>
                <div class="rounded-xl border-2 border-slate-200 p-4 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="font-semibold text-slate-800 peer-checked:text-emerald-700">Tutardan Hesapla</div>
                            <div class="text-xs text-slate-500">Belirli tutarı dağıt</div>
                        </div>
                    </div>
                </div>
            </label>
            <label class="cursor-pointer flex-1">
                <input type="radio" name="source_type" value="expenses" class="peer sr-only" @checked(old('source_type') === 'expenses')>
                <div class="rounded-xl border-2 border-slate-200 p-4 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center peer-checked:bg-emerald-100">
                            <svg class="w-5 h-5 text-slate-600 peer-checked:text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="font-semibold text-slate-800 peer-checked:text-emerald-700">Giderden Hesapla</div>
                            <div class="text-xs text-slate-500">Dönem giderleri dağıt</div>
                        </div>
                    </div>
                </div>
            </label>
        </div>
        @error('source_type')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror

        {{-- Dynamic Source Fields --}}
        <div id="expenses-fields" class="rounded-2xl bg-white p-5 shadow-sm @if(old('source_type', 'manual') === 'manual') hidden @endif">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Gider Dönemi Seçimi</h3>

            {{-- Period Selection --}}
            <div class="mb-4">
                <label class="mb-2 block text-sm font-medium text-slate-600">Gider Dönemi</label>
                <div class="flex items-center gap-3">
                    <input id="source_period" name="source_period" type="month" value="{{ old('source_period', now()->format('Y-m')) }}" class="w-40 md:w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    <button type="button" id="calc-btn" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 whitespace-nowrap">
                        Giderleri Getir
                    </button>
                    <div id="expense-total-display" class="hidden flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2">
                        <span class="text-xs text-emerald-600">Toplam:</span>
                        <span id="expense-total-amount" class="text-sm font-bold text-emerald-700">0,00 TL</span>
                    </div>
                </div>
                @error('source_period')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            {{-- Expense List for Selected Period --}}
            <div>
                <label class="mb-3 block text-sm font-medium text-slate-600">Dönem Giderleri (Seçilenler Borçlandırılacak)</label>
                <div id="expense-list-container" class="border border-slate-200 rounded-xl overflow-hidden">
                    <div id="expense-list-empty" class="p-4 text-sm text-slate-500 text-center">
                        Dönem seçip "Giderleri Getir" butonuna tıklayın
                    </div>
                    <div id="expense-list" class="hidden divide-y divide-slate-200">
                        {{-- Expenses will be loaded here dynamically --}}
                    </div>
                    <div id="expense-list-summary" class="hidden bg-slate-50 p-3 border-t border-slate-200">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-slate-600">Seçilen Giderler:</span>
                            <span id="selected-expense-count" class="font-medium text-slate-900">0</span>
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500">Listeden borçlandırılacak giderleri seçin. Seçtikçe toplam otomatik hesaplanır.</p>
            </div>

            <input type="hidden" name="selected_expense_ids" id="selected_expense_ids" value="">
        </div>

        <div id="fixed-fields" class="rounded-2xl bg-white p-5 shadow-sm @if(old('source_type', 'fixed') !== 'fixed') hidden @endif">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Sabit Tutar Girişi</h3>
            <div class="md:w-1/2">
                <label for="fixed_amount" class="mb-2 block text-sm font-medium text-slate-600">Daire Başına Tutar</label>
                <input id="fixed_amount" name="fixed_amount" type="number" min="0.01" step="0.01" value="{{ old('fixed_amount') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none text-lg font-semibold" placeholder="0,00">
                <p class="mt-1.5 text-xs text-slate-400">Her daire bu tutarla borçlandırılır. Toplam = tutar &times; daire sayısı.</p>
                @error('fixed_amount')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <div id="manual-fields" class="rounded-2xl bg-white p-5 shadow-sm @if(old('source_type') !== 'manual') hidden @endif">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Tutar Girişi</h3>
            <div class="md:w-1/2">
                <label for="source_amount" class="mb-2 block text-sm font-medium text-slate-600">Dağıtılacak Toplam Tutar</label>
                <input id="source_amount" name="source_amount" type="number" min="0.01" step="0.01" value="{{ old('source_amount') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none text-lg font-semibold">
                @error('source_amount')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Common Fields --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-700">Borç Bilgileri</h3>
                <div id="calc-summary" class="text-sm font-medium text-red-600 hidden"></div>
            </div>

            {{-- Distribution Type Selection --}}
            <div class="mb-4">
                <label class="mb-2 block text-sm font-medium text-slate-600">Dağıtım Yöntemi</label>
                <select name="distribution_type" id="distribution_type" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                    <option value="equal" @selected(old('distribution_type', 'equal') === 'equal')>Eşit dağıtım</option>
                    <option value="square_meters" @selected(old('distribution_type') === 'square_meters')>Metrekareye göre</option>
                    <option value="share_coefficient" @selected(old('distribution_type') === 'share_coefficient')>Pay çarpanına göre</option>
                </select>
                @error('distribution_type')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            {{-- Distribution Preview --}}
            <div id="distribution-preview" class="hidden mb-4">
                <div class="rounded-xl border border-slate-200 overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dağıtım Önizlemesi</span>
                        <span id="preview-total-label" class="text-xs text-slate-500"></span>
                    </div>
                    <div id="preview-warning" class="hidden px-4 py-2 bg-amber-50 border-b border-amber-200 text-xs text-amber-700"></div>
                    <div id="preview-groups" class="divide-y divide-slate-100"></div>
                </div>
            </div>

            {{-- Target Audience Selection --}}
            <div class="mb-4">
                <label class="mb-2 block text-sm font-medium text-slate-600">Borçlanacak Kişiler</label>
                <div class="flex gap-3">
                    <label class="cursor-pointer flex-1">
                        <input type="radio" name="target_audience" value="tenant_priority" class="peer sr-only" @checked(old('target_audience', 'tenant_priority') === 'tenant_priority')>
                        <div class="rounded-xl border-2 border-slate-200 p-3 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50">
                            <div class="font-semibold text-slate-800 peer-checked:text-emerald-700 text-sm">Kiracı Öncelikli</div>
                            <div class="text-xs text-slate-500 mt-1">Varsa Kiracıya, yoksa sahibine</div>
                        </div>
                    </label>
                    <label class="cursor-pointer flex-1">
                        <input type="radio" name="target_audience" value="owner_only" class="peer sr-only" @checked(old('target_audience') === 'owner_only')>
                        <div class="rounded-xl border-2 border-slate-200 p-3 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50">
                            <div class="font-semibold text-slate-800 peer-checked:text-emerald-700 text-sm">Sadece Sahipler</div>
                            <div class="text-xs text-slate-500 mt-1">Tüm borçlar kat maliklerine</div>
                        </div>
                    </label>
                </div>
                @error('target_audience')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="due_type" class="mb-2 block text-sm font-medium text-slate-600">Borç Türü <span class="text-red-500">*</span></label>
                    <select id="due_type" name="due_type" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                        <option value="">Tür seçin</option>
                        @foreach ($dueTypes as $t)
                            <option value="{{ $t['value'] }}" @selected(old('due_type', 'aidat') === $t['value'])>{{ $t['label'] }}</option>
                        @endforeach
                    </select>
                    @error('due_type')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="category_id" class="mb-2 block text-sm font-medium text-slate-600">Kategori <span class="text-xs text-slate-400">(isteğe bağlı)</span></label>
                    <select id="category_id" name="category_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                        <option value="">Kategori seçin</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) old('category_id') === (string) $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="created_at_manual" class="mb-2 block text-sm font-medium text-slate-600">Oluşturulma Tarihi</label>
                    <input id="created_at_manual" name="created_at_manual" type="date" value="{{ old('created_at_manual', now()->toDateString()) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                    @error('created_at_manual')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <input type="hidden" id="period" name="period" value="{{ old('period', now()->format('Y-m')) }}">

                <div>
                    <label for="due_date" class="mb-2 block text-sm font-medium text-slate-600">Son Ödeme Tarihi</label>
                    <input id="due_date" name="due_date" type="date" value="{{ old('due_date') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                    @error('due_date')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="mb-2 block text-sm font-medium text-slate-600">Açıklama</label>
                    <input id="description" name="description" value="{{ old('description') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none" placeholder="Örn. Nisan aidatı, demirbaş giderleri vb.">
                    @error('description')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="w-full md:w-auto rounded-xl bg-emerald-600 px-8 py-3.5 text-sm font-semibold text-white hover:bg-emerald-700 shadow-sm">Toplu Borçlandırmayı Oluştur</button>
        </div>
    </form>

    <script>
        (() => {
            const units = {{ $units }};
            const unitsData = {!! json_encode($unitsData->values()) !!};

            const sourceRadios = document.querySelectorAll('input[name="source_type"]');
            const expensesFields = document.getElementById('expenses-fields');
            const manualFields = document.getElementById('manual-fields');
            const fixedFields = document.getElementById('fixed-fields');
            const fixedAmount = document.getElementById('fixed_amount');
            const sourcePeriod = document.getElementById('source_period');
            const sourceAmount = document.getElementById('source_amount');
            const expenseTotalDisplay = document.getElementById('expense-total-display');
            const expenseTotalAmount = document.getElementById('expense-total-amount');
            const dueDateInput = document.getElementById('due_date');
            let isDueDateManuallySet = false;

            const calcSummary = document.getElementById('calc-summary');
            const calcBtn = document.getElementById('calc-btn');
            const expenseList = document.getElementById('expense-list');
            const expenseListEmpty = document.getElementById('expense-list-empty');
            const expenseListSummary = document.getElementById('expense-list-summary');
            const selectedExpenseCount = document.getElementById('selected-expense-count');
            const selectedExpenseIdsInput = document.getElementById('selected_expense_ids');

            let currentExpenses = [];
            let selectedExpenseIds = new Set();

            const formatMoney = (amount) => {
                return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + ' TL';
            };

            const calculateSelectedTotal = () => {
                let total = 0;
                currentExpenses.forEach(expense => {
                    if (selectedExpenseIds.has(expense.id.toString())) {
                        total += parseFloat(expense.amount);
                    }
                });
                return total;
            };

            const updateCalculation = () => {
                const selectedSource = document.querySelector('input[name="source_type"]:checked').value;
                let total = 0;

                if (selectedSource === 'expenses') {
                    total = calculateSelectedTotal();
                    expenseTotalDisplay.classList.remove('hidden');
                    expenseTotalAmount.textContent = total > 0 ? formatMoney(total) : '0,00 TL';
                } else if (selectedSource === 'fixed') {
                    const perUnit = parseFloat(fixedAmount?.value) || 0;
                    total = perUnit * units;
                    expenseTotalDisplay.classList.add('hidden');
                } else {
                    total = parseFloat(sourceAmount.value) || 0;
                    expenseTotalDisplay.classList.add('hidden');
                }

                if (selectedSource === 'fixed') {
                    const perUnit = parseFloat(fixedAmount?.value) || 0;
                    if (units > 0 && perUnit > 0) {
                        calcSummary.textContent = `Daire Başına: ${formatMoney(perUnit)} / Toplam: ${formatMoney(perUnit * units)} (${units} daire)`;
                        calcSummary.classList.remove('hidden');
                    } else {
                        calcSummary.classList.add('hidden');
                    }
                } else if (units > 0 && total > 0) {
                    const perUnit = total / units;
                    calcSummary.textContent = `Toplam: ${formatMoney(total)} / Daire: ${formatMoney(perUnit)}`;
                    calcSummary.classList.remove('hidden');
                } else {
                    calcSummary.classList.add('hidden');
                }

                if (selectedSource === 'fixed') {
                    updateFixedPreview(parseFloat(fixedAmount?.value) || 0);
                } else {
                    updateDistributionPreview(total);
                }
            };

            const updateFixedPreview = (perUnit) => {
                const previewEl = document.getElementById('distribution-preview');
                const groupsEl = document.getElementById('preview-groups');
                const warningEl = document.getElementById('preview-warning');
                const totalLbl = document.getElementById('preview-total-label');

                if (!perUnit || perUnit <= 0 || !units) {
                    previewEl.classList.add('hidden');
                    return;
                }

                warningEl.classList.add('hidden');
                const total = perUnit * units;

                groupsEl.innerHTML = `<div class="flex items-center justify-between px-4 py-3 text-sm">
                    <div class="text-slate-700"><span class="font-medium">${formatMoney(perUnit)} / daire</span> &times; <span class="text-slate-500">${units} daire</span></div>
                    <div class="font-bold text-slate-900 tabular-nums">Toplam ${formatMoney(total)}</div>
                </div>`;
                totalLbl.textContent = formatMoney(total) + ' · ' + units + ' daire';
                previewEl.classList.remove('hidden');
            };

            const updateDistributionPreview = (total) => {
                const distType = document.getElementById('distribution_type')?.value;
                const previewEl = document.getElementById('distribution-preview');
                const groupsEl = document.getElementById('preview-groups');
                const warningEl = document.getElementById('preview-warning');
                const totalLbl = document.getElementById('preview-total-label');

                if (!total || total <= 0 || !unitsData.length) {
                    previewEl.classList.add('hidden');
                    return;
                }

                const activeUnits = unitsData.filter(u => {
                    if (distType === 'square_meters') return u.sqm > 0;
                    if (distType === 'share_coefficient') return u.coef > 0;
                    return true;
                });

                const zeroCount = unitsData.length - activeUnits.length;
                if (distType !== 'equal' && zeroCount > 0) {
                    warningEl.textContent = zeroCount + ' dairenin ' + (distType === 'square_meters' ? 'metrekare' : 'pay çarpanı') + ' bilgisi 0 veya boş — bu daireler dağıtımdan hariç tutulur.';
                    warningEl.classList.remove('hidden');
                } else {
                    warningEl.classList.add('hidden');
                }

                if (activeUnits.length === 0) {
                    previewEl.classList.add('hidden');
                    return;
                }

                const totalWeight = activeUnits.reduce((s, u) => {
                    if (distType === 'square_meters') return s + u.sqm;
                    if (distType === 'share_coefficient') return s + u.coef;
                    return s + 1;
                }, 0);

                let shares = [];
                let allocated = 0;
                activeUnits.forEach((u, idx) => {
                    const w = distType === 'equal' ? 1 : (distType === 'square_meters' ? u.sqm : u.coef);
                    const share = idx === activeUnits.length - 1
                        ? Math.round((total - allocated) * 100) / 100
                        : Math.round(total * w / totalWeight * 100) / 100;
                    allocated += share;
                    shares.push({ unit: u, w, share });
                });

                const groups = {};
                shares.forEach(s => {
                    const key = distType === 'equal' ? 'equal' : s.w.toString();
                    if (!groups[key]) groups[key] = { w: s.w, share: s.share, count: 0 };
                    groups[key].count++;
                });

                const sortedGroups = Object.values(groups).sort((a, b) => a.w - b.w);

                let html = '';
                sortedGroups.forEach(g => {
                    let weightLabel = distType === 'equal' ? 'Eşit dağıtım'
                        : distType === 'square_meters' ? g.w.toLocaleString('tr-TR') + ' m²'
                        : g.w.toLocaleString('tr-TR') + ' çarpan';
                    html += `<div class="flex items-center justify-between px-4 py-3 text-sm">
                        <div class="text-slate-700"><span class="font-medium">${weightLabel}</span> &mdash; <span class="text-slate-500">${g.count} daire</span></div>
                        <div class="font-bold text-slate-900 tabular-nums">${formatMoney(g.share)} / daire</div>
                    </div>`;
                });

                groupsEl.innerHTML = html;
                totalLbl.textContent = formatMoney(total) + ' · ' + activeUnits.length + ' daire';
                previewEl.classList.remove('hidden');
            };

            const renderExpenseList = () => {
                if (currentExpenses.length === 0) {
                    expenseList.innerHTML = '';
                    expenseList.classList.add('hidden');
                    expenseListEmpty.classList.remove('hidden');
                    expenseListSummary.classList.add('hidden');
                    return;
                }

                expenseListEmpty.classList.add('hidden');
                expenseList.classList.remove('hidden');
                expenseListSummary.classList.remove('hidden');

                expenseList.innerHTML = currentExpenses.map(expense => {
                    const isSelected = selectedExpenseIds.has(expense.id.toString());
                    return `
                        <label class="flex items-center gap-3 p-3 hover:bg-slate-50 cursor-pointer transition-colors">
                            <input type="checkbox" class="expense-checkbox w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                value="${expense.id}" ${isSelected ? 'checked' : ''}
                                data-amount="${expense.amount}">
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start gap-2">
                                    <div class="text-sm font-medium text-slate-900 truncate">${expense.description || 'Gider #' + expense.reference_number}</div>
                                    <div class="text-sm font-semibold text-slate-900 whitespace-nowrap">${formatMoney(expense.amount)}</div>
                                </div>
                                <div class="flex justify-between items-center gap-2 mt-1">
                                    <div class="text-xs text-slate-500">${expense.category_name} • ${expense.expense_date}</div>
                                    <div class="text-xs text-slate-400">${expense.reference_number}</div>
                                </div>
                            </div>
                        </label>
                    `;
                }).join('');

                // Attach event listeners to checkboxes
                document.querySelectorAll('.expense-checkbox').forEach(cb => {
                    cb.addEventListener('change', (e) => {
                        if (e.target.checked) {
                            selectedExpenseIds.add(e.target.value);
                        } else {
                            selectedExpenseIds.delete(e.target.value);
                        }
                        selectedExpenseCount.textContent = selectedExpenseIds.size;
                        selectedExpenseIdsInput.value = Array.from(selectedExpenseIds).join(',');
                        updateCalculation();
                    });
                });

                selectedExpenseCount.textContent = selectedExpenseIds.size;
            };

            // Load expenses button click handler
            calcBtn.addEventListener('click', async () => {
                const period = sourcePeriod.value;
                if (!period) {
                    alert('Lütfen bir dönem seçin.');
                    return;
                }

                calcBtn.disabled = true;
                calcBtn.textContent = 'Yükleniyor...';

                try {
                    const response = await fetch(`/dues/expenses-by-period?period=${period}`);
                    
                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error('Server error:', response.status, errorText);
                        throw new Error(`Server error: ${response.status}`);
                    }
                    
                    currentExpenses = await response.json();
                    
                    if (currentExpenses.error) {
                        throw new Error(currentExpenses.error);
                    }
                    
                    selectedExpenseIds.clear();
                    selectedExpenseIdsInput.value = '';
                    
                    renderExpenseList();
                    updateCalculation();
                } catch (error) {
                    console.error('Error fetching expenses:', error);
                    alert('Giderler yüklenirken bir hata oluştu: ' + error.message);
                } finally {
                    calcBtn.disabled = false;
                    calcBtn.textContent = 'Giderleri Getir';
                }
            });

            const distTypeWrapper = document.getElementById('distribution_type')?.closest('.mb-4');

            const toggleFields = () => {
                const selected = document.querySelector('input[name="source_type"]:checked').value;
                expensesFields.classList.toggle('hidden', selected !== 'expenses');
                manualFields.classList.toggle('hidden', selected !== 'manual');
                fixedFields.classList.toggle('hidden', selected !== 'fixed');
                if (distTypeWrapper) distTypeWrapper.classList.toggle('hidden', selected === 'fixed');
                updateCalculation();
            };

            sourceRadios.forEach(radio => radio.addEventListener('change', toggleFields));
            sourceAmount.addEventListener('input', updateCalculation);
            fixedAmount?.addEventListener('input', updateCalculation);
            document.getElementById('distribution_type')?.addEventListener('change', updateCalculation);

            // Initialize
            toggleFields();

            // Auto-populate description based on period and category
            const typeSelect = document.getElementById('due_type');
            const topicSelect = document.getElementById('category_id');
            const periodInput = document.getElementById('period');
            const createdAtInput = document.getElementById('created_at_manual');
            const descriptionInput = document.getElementById('description');

            const months = {
                '01': 'Ocak', '02': 'Şubat', '03': 'Mart', '04': 'Nisan',
                '05': 'Mayıs', '06': 'Haziran', '07': 'Temmuz', '08': 'Ağustos',
                '09': 'Eylül', '10': 'Ekim', '11': 'Kasım', '12': 'Aralık'
            };

            const syncPeriodFromDate = () => {
                const dateVal = createdAtInput?.value;
                if (dateVal) {
                    periodInput.value = dateVal.substring(0, 7);
                    updateDescription();
                }
            };

            const syncDueDateFromCreatedAt = () => {
                const dateVal = createdAtInput?.value;
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
                const period = periodInput.value;
                const typeOption = typeSelect.options[typeSelect.selectedIndex];
                const typeName = typeOption?.value ? typeOption.text : '';
                const topicOption = topicSelect?.options[topicSelect.selectedIndex];
                const topicName = topicOption?.value ? topicOption.text : '';

                if (period && typeName) {
                    const [year, month] = period.split('-');
                    const monthName = months[month] || month;
                    descriptionInput.value = `${year} ${monthName} - ${typeName}`;
                }
            };

            createdAtInput?.addEventListener('change', () => {
                syncPeriodFromDate();
                syncDueDateFromCreatedAt();
            });
            dueDateInput?.addEventListener('input', () => {
                isDueDateManuallySet = dueDateInput.value !== '';
            });
            typeSelect?.addEventListener('change', updateDescription);
            periodInput?.addEventListener('change', updateDescription);

            // Init
            syncPeriodFromDate();
            if (!dueDateInput.value) {
                syncDueDateFromCreatedAt();
            }
        })();
    </script>
@endsection
