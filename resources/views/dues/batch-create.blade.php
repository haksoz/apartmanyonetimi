@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Toplu Borçlandır</h1>
            <p class="mt-1 text-sm text-slate-500">Dönem giderlerinden veya manuel toplam tutar ile tüm dairelere borçlandırma oluşturun.</p>
        </div>
        <a href="{{ route('dues.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Aidatlara Dön</a>
    </div>

    <form method="POST" action="{{ route('dues.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="distribution_type" value="equal">

        {{-- Source Selection - Side by Side with Icons --}}
        <div class="flex gap-3">
            <label class="cursor-pointer flex-1">
                <input type="radio" name="source_type" value="expenses" class="peer sr-only" @checked(old('source_type', 'expenses') === 'expenses')>
                <div class="rounded-xl border-2 border-slate-200 p-4 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center peer-checked:bg-emerald-100">
                            <svg class="w-5 h-5 text-slate-600 peer-checked:text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="font-semibold text-slate-800 peer-checked:text-emerald-700">Giderlerden</div>
                            <div class="text-xs text-slate-500">Dönem giderleri dağıt</div>
                        </div>
                    </div>
                </div>
            </label>
            <label class="cursor-pointer flex-1">
                <input type="radio" name="source_type" value="manual" class="peer sr-only" @checked(old('source_type') === 'manual')>
                <div class="rounded-xl border-2 border-slate-200 p-4 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center peer-checked:bg-emerald-100">
                            <svg class="w-5 h-5 text-slate-600 peer-checked:text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="font-semibold text-slate-800 peer-checked:text-emerald-700">Manuel</div>
                            <div class="text-xs text-slate-500">Kendi tutarını gir</div>
                        </div>
                    </div>
                </div>
            </label>
        </div>
        @error('source_type')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror

        {{-- Dynamic Source Fields --}}
        <div id="expenses-fields" class="rounded-2xl bg-white p-5 shadow-sm @if(old('source_type') === 'manual') hidden @endif">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Gider Dönemi Seçimi</h3>

            {{-- Period Selection - Desktop Friendly --}}
            <div class="mb-4">
                <label class="mb-2 block text-sm font-medium text-slate-600">Gider Dönemi</label>
                <div class="flex gap-3">
                    <input id="source_period" name="source_period" type="month" value="{{ old('source_period', now()->format('Y-m')) }}" class="flex-1 rounded-xl border border-slate-300 px-5 py-4 text-base focus:border-emerald-500 focus:outline-none md:text-lg">
                    <button type="button" id="calc-btn" class="rounded-xl bg-emerald-600 px-6 py-4 text-base font-semibold text-white hover:bg-emerald-700 whitespace-nowrap shadow-md">
                        Hesapla
                    </button>
                </div>
                @error('source_period')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            {{-- Calculation Result --}}
            <div id="expense-total-display" class="rounded-xl bg-emerald-50 p-4 text-center hidden mb-4">
                <div class="text-sm text-emerald-700 mb-1">Seçili dönem toplam gideri</div>
                <div id="expense-total-amount" class="text-2xl font-bold text-emerald-800">0,00 TL</div>
            </div>

            {{-- Tag Style Category Filters --}}
            <div>
                <label class="mb-3 block text-sm font-medium text-slate-600">Kategori Filtresi (İsteğe Bağlı)</label>
                <div class="flex flex-wrap gap-2" id="category-filters">
                    @foreach ($expenseCategories as $category)
                        <label class="cursor-pointer category-filter" data-category-id="{{ $category->id }}">
                            <input type="checkbox" name="category_filter_ids[]" value="{{ $category->id }}" class="peer sr-only category-checkbox">
                            <span class="inline-flex rounded-full border border-slate-300 px-4 py-2 text-sm text-slate-600 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-500 peer-checked:text-white hover:bg-slate-50">
                                {{ $category->name }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-slate-500">Seçili kategorilerin toplamı hesaplanır. Boş bırakırsanız tüm giderler.</p>
            </div>
        </div>

        <div id="manual-fields" class="rounded-2xl bg-white p-5 shadow-sm @if(old('source_type', 'expenses') !== 'manual') hidden @endif">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Manuel Tutar Girişi</h3>
            <div>
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
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="category_id" class="mb-2 block text-sm font-medium text-slate-600">Borç Kategorisi</label>
                    <select id="category_id" name="category_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                        <option value="">Kategori seçin</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="period" class="mb-2 block text-sm font-medium text-slate-600">Borç Dönemi</label>
                    <input id="period" name="period" type="month" value="{{ old('period', now()->format('Y-m')) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                    @error('period')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="created_at_manual" class="mb-2 block text-sm font-medium text-slate-600">Oluşturulma Tarihi</label>
                    <input id="created_at_manual" name="created_at_manual" type="date" value="{{ old('created_at_manual', now()->toDateString()) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                    @error('created_at_manual')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div class="md:col-span-2">
                    <label for="due_date" class="mb-2 block text-sm font-medium text-slate-600">Son Ödeme Tarihi</label>
                    <input id="due_date" name="due_date" type="date" value="{{ old('due_date', now()->endOfMonth()->toDateString()) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                    @error('due_date')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="mb-2 block text-sm font-medium text-slate-600">Açıklama</label>
                    <input id="description" name="description" value="{{ old('description') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none" placeholder="Örn. Nisan aidatı, demirbaş giderleri vb.">
                    @error('description')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <button type="submit" class="w-full rounded-xl bg-emerald-600 px-5 py-4 text-base font-semibold text-white hover:bg-emerald-700 shadow-lg">Toplu Borçlandırmayı Oluştur</button>
    </form>

    <script>
        (() => {
            const units = {{ $units }};
            const expensesByPeriod = @json($expensesByPeriod);
            const expensesByCategory = @json($expensesByCategory);

            const sourceRadios = document.querySelectorAll('input[name="source_type"]');
            const expensesFields = document.getElementById('expenses-fields');
            const manualFields = document.getElementById('manual-fields');
            const sourcePeriod = document.getElementById('source_period');
            const sourceAmount = document.getElementById('source_amount');
            const expenseTotalDisplay = document.getElementById('expense-total-display');
            const expenseTotalAmount = document.getElementById('expense-total-amount');
            const categoryCheckboxes = document.querySelectorAll('.category-checkbox');

            const calcSummary = document.getElementById('calc-summary');
            const calcBtn = document.getElementById('calc-btn');

            const formatMoney = (amount) => {
                return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + ' TL';
            };

            const getSelectedCategories = () => {
                return Array.from(categoryCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);
            };

            const calculateExpenseTotal = () => {
                const period = sourcePeriod.value;
                const selectedCategories = getSelectedCategories();

                let total = 0;

                if (selectedCategories.length === 0) {
                    // No categories selected, use all expenses for the period
                    total = expensesByPeriod[period] || 0;
                } else {
                    // Sum up expenses for selected categories only
                    selectedCategories.forEach(catId => {
                        const catExpenses = expensesByCategory[catId] || {};
                        total += catExpenses[period] || 0;
                    });
                }

                return total;
            };

            const updateCalculation = () => {
                const selectedSource = document.querySelector('input[name="source_type"]:checked').value;
                let total = 0;

                if (selectedSource === 'expenses') {
                    total = calculateExpenseTotal();

                    if (total > 0) {
                        expenseTotalDisplay.classList.remove('hidden');
                        expenseTotalAmount.textContent = formatMoney(total);
                    } else {
                        expenseTotalDisplay.classList.remove('hidden');
                        expenseTotalAmount.textContent = '0,00 TL';
                    }
                } else {
                    total = parseFloat(sourceAmount.value) || 0;
                    expenseTotalDisplay.classList.add('hidden');
                }

                // Update red summary text in Debt Info section
                if (units > 0 && total > 0) {
                    const perUnit = total / units;
                    calcSummary.textContent = `Toplam: ${formatMoney(total)} / Daire: ${formatMoney(perUnit)}`;
                    calcSummary.classList.remove('hidden');
                } else {
                    calcSummary.classList.add('hidden');
                }
            };

            // Calculate button click handler
            calcBtn.addEventListener('click', () => {
                updateCalculation();
            });

            // Category checkbox change handler
            categoryCheckboxes.forEach(cb => {
                cb.addEventListener('change', () => {
                    // When category changes, recalculate if already calculated
                    if (!expenseTotalDisplay.classList.contains('hidden')) {
                        updateCalculation();
                    }
                });
            });

            const toggleFields = () => {
                const selected = document.querySelector('input[name="source_type"]:checked').value;
                if (selected === 'expenses') {
                    expensesFields.classList.remove('hidden');
                    manualFields.classList.add('hidden');
                    // Don't auto-calculate for expenses mode, wait for button click
                } else {
                    expensesFields.classList.add('hidden');
                    manualFields.classList.remove('hidden');
                    updateCalculation(); // Auto-calculate for manual mode
                }
            };

            sourceRadios.forEach(radio => radio.addEventListener('change', toggleFields));
            sourceAmount.addEventListener('input', updateCalculation);

            // Initialize
            toggleFields();

            // Auto-populate description based on period and category
            const categorySelect = document.getElementById('category_id');
            const periodInput = document.getElementById('period');
            const descriptionInput = document.getElementById('description');

            const months = {
                '01': 'Ocak', '02': 'Şubat', '03': 'Mart', '04': 'Nisan',
                '05': 'Mayıs', '06': 'Haziran', '07': 'Temmuz', '08': 'Ağustos',
                '09': 'Eylül', '10': 'Ekim', '11': 'Kasım', '12': 'Aralık'
            };

            const updateDescription = () => {
                const period = periodInput.value;
                const categoryOption = categorySelect.options[categorySelect.selectedIndex];
                const categoryName = categoryOption ? categoryOption.text : '';

                if (period && categoryName) {
                    const [year, month] = period.split('-');
                    const monthName = months[month] || month;
                    descriptionInput.value = `${monthName} ${year} - ${categoryName} - `;
                }
            };

            categorySelect?.addEventListener('change', updateDescription);
            periodInput?.addEventListener('change', updateDescription);
        })();
    </script>
@endsection
