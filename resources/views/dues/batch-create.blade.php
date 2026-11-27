@extends('layouts.app')

@section('content')
    @php
        $sourceLabels = ['fixed' => 'Sabit Tutar', 'manual' => 'Tutardan Hesapla', 'expenses' => 'Giderden Hesapla'];
        $requestedSource = request()->query('source_type');
        $initialSource = is_string($requestedSource) && isset($sourceLabels[$requestedSource]) ? $requestedSource : 'fixed';
        $selectedSource = old('source_type', $initialSource);
        $compactSource = is_string($requestedSource) && isset($sourceLabels[$requestedSource]);
    @endphp
    <div class="mb-6 flex flex-row items-center justify-between gap-3 min-w-0">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold text-slate-950">
                <a href="{{ route('dues.index') }}" class="hover:text-slate-600">Aidatlar</a>
                <span class="font-normal text-slate-400"> / </span>
                <span class="text-lg font-semibold text-slate-800">Toplu Borçlandır</span>
            </h1>
            <p class="mt-1 text-sm text-slate-500">Dönem giderlerinden veya manuel toplam tutar ile tüm dairelere borçlandırma oluşturun.</p>
        </div>
        <div class="flex items-center justify-end gap-2 shrink-0">
            <button type="button" onclick="history.back()" class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-xl border border-slate-300 px-3 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:px-4 sm:text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h14" />
                </svg>
                Geri
            </button>
        </div>
    </div>

    <form method="POST" action="{{ route('dues.store') }}" class="space-y-4">
        @csrf

        {{-- Source Selection - Side by Side with Icons --}}
        @if ($compactSource)
            <div id="batch-method-summary" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 md:hidden">
                <span id="batch-selected-method" class="text-sm font-semibold text-slate-800">{{ $sourceLabels[$selectedSource] ?? $sourceLabels[$initialSource] }}</span>
                <button type="button" onclick="document.getElementById('batch-source-options').classList.remove('hidden'); document.getElementById('batch-method-summary').classList.add('hidden')" class="text-sm font-semibold text-emerald-700">Değiştir</button>
            </div>
        @endif
        <div id="batch-source-options" class="{{ $compactSource ? 'hidden md:flex' : 'flex' }} flex-col gap-3 md:flex-row">
            <label class="cursor-pointer flex-1">
                <input type="radio" name="source_type" value="fixed" class="peer sr-only" @checked(old('source_type', $initialSource) === 'fixed')>
                <div class="rounded-xl border-2 border-slate-200 p-4 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-disabled:opacity-50 hover:bg-slate-50">
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
                <input type="radio" name="source_type" value="manual" class="peer sr-only" @checked(old('source_type', $initialSource) === 'manual')>
                <div class="rounded-xl border-2 border-slate-200 p-4 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-disabled:opacity-50 hover:bg-slate-50">
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
                <input type="radio" name="source_type" value="expenses" class="peer sr-only" @checked(old('source_type', $initialSource) === 'expenses')>
                <div class="rounded-xl border-2 border-slate-200 p-4 transition-all peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-disabled:opacity-50 hover:bg-slate-50">
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
        <div id="expenses-details-fields" class="rounded-2xl bg-white p-5 shadow-sm space-y-4 @if(old('source_type', $initialSource) !== 'expenses') hidden @endif">
            <div id="expenses-type-slot"></div>
            <div id="expenses-dates-slot"></div>
        </div>

        <div id="expenses-fields" class="rounded-2xl bg-white p-5 shadow-sm @if(old('source_type', $initialSource) !== 'expenses') hidden @endif">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Gider Dönemi Seçimi</h3>

            {{-- Period Selection --}}
            <div class="mb-4">
                <label class="mb-2 block text-sm font-medium text-slate-600">Gider Dönemi</label>
                <div class="flex flex-wrap items-center gap-3">
                    @php
                        $sourceStart = old('source_period', now()->format('Y-m'));
                        $sourceEnd = old('source_period_end', $sourceStart);
                        if (! is_string($sourceStart) || ! preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $sourceStart)) $sourceStart = now()->format('Y-m');
                        if (! is_string($sourceEnd) || ! preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $sourceEnd)) $sourceEnd = $sourceStart;
                        $sourceLabel = $sourceStart ? \Carbon\Carbon::parse($sourceStart.'-01')->locale('tr')->translatedFormat('F Y') : 'Dönem seçin';
                        if ($sourceEnd && $sourceEnd !== $sourceStart) $sourceLabel .= ' – '.\Carbon\Carbon::parse($sourceEnd.'-01')->locale('tr')->translatedFormat('F Y');
                    @endphp
                    <input id="expense-source-period" name="source_period" type="hidden" value="{{ $sourceStart }}">
                    <input id="expense-source-period-end" name="source_period_end" type="hidden" value="{{ $sourceEnd }}">
                    <button type="button" id="expense-source-period-open" aria-haspopup="dialog" aria-label="Gider dönem aralığını seç" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-sm sm:w-auto"><span id="expense-source-period-text">{{ $sourceLabel }}</span></button>
                    <button type="button" id="calc-btn" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 whitespace-nowrap">
                        Giderleri Getir
                    </button>
                    <div id="expense-total-display" class="hidden w-full md:w-auto flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2">
                        <span class="text-xs text-emerald-600">Toplam:</span>
                        <span id="expense-total-amount" class="text-sm font-bold text-emerald-700">0,00 TL</span>
                    </div>
                </div>
                @error('source_period')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                @error('source_period_end')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                @error('selected_expense_ids')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
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

        <div id="fixed-fields" class="rounded-2xl bg-white p-5 shadow-sm space-y-4 @if(old('source_type', $initialSource) !== 'fixed') hidden @endif">
            <div id="fixed-type-slot">
                <div id="batch-type-fields" class="grid gap-4">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <input type="hidden" name="due_type" value="{{ $selectedDueType }}">
                        <p class="text-sm font-semibold text-emerald-800">{{ \App\Enums\DueType::from($selectedDueType)->label() }}</p>
                        <p class="mt-1 text-xs text-emerald-700">{{ $selectedDueType === 'aidat' ? 'Apartmanın ortak kullanım ve düzenli giderleri için borçlandırma.' : 'Demirbaş, yenileme ve benzeri işler için kat maliklerine borçlandırma.' }}</p>
                        @error('due_type')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                    </div>

                    <div id="category-field">
                        <label for="category_id" class="mb-2 block text-sm font-medium text-slate-600">Kategori <span class="text-red-500">*</span></label>
                        <select id="category_id" name="category_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                            <option value="">Kategori seçin</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" data-usages="{{ collect(\App\Models\Category::USAGE_PERMISSIONS)->keys()->filter(fn ($key) => $cat->{$key})->map(fn ($key) => str_replace('allow_', '', $key))->implode(',') }}" @selected((string) old('category_id') === (string) $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div id="fixed-dates-slot">
                <div id="batch-date-fields" class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="created_at_manual" class="mb-2 block text-sm font-medium text-slate-600">Oluşturulma Tarihi</label>
                        <input id="created_at_manual" name="created_at_manual" type="date" required value="{{ old('created_at_manual', now()->toDateString()) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                        @error('created_at_manual')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                    </div>


                    <div>
                        <label for="due_date" class="mb-2 block text-sm font-medium text-slate-600">Son Ödeme Tarihi</label>
                        <input id="due_date" name="due_date" type="date" value="{{ old('due_date') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                        @error('due_date')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div id="manual-fields" class="rounded-2xl bg-white p-5 shadow-sm space-y-4 @if(old('source_type', $initialSource) !== 'manual') hidden @endif">
            <div id="manual-type-slot"></div>

            <div id="manual-dates-slot"></div>
        </div>

        {{-- Common Fields --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-700">Borç Bilgileri</h3>
                <div id="calc-summary" class="text-sm font-medium text-red-600 hidden"></div>
            </div>

            <div id="fixed-amount-field" class="mb-4 md:w-1/2 @if(old('source_type', $initialSource) !== 'fixed') hidden @endif">
                <label for="fixed_amount" class="mb-2 block text-sm font-medium text-slate-600">Daire Başına Tutar</label>
                <input id="fixed_amount" name="fixed_amount" type="number" min="0.01" step="0.01" value="{{ old('fixed_amount') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none text-lg font-semibold" placeholder="0,00">
                <p class="mt-1.5 text-xs text-slate-400">Her daire bu tutarla borçlandırılır. Toplam = tutar &times; daire sayısı.</p>
                @error('fixed_amount')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div id="manual-amount-field" class="mb-4 md:w-1/2 @if(old('source_type', $initialSource) !== 'manual') hidden @endif">
                <label for="source_amount" class="mb-2 block text-sm font-medium text-slate-600">Dağıtılacak Toplam Tutar</label>
                <input id="source_amount" name="source_amount" type="number" min="0.01" step="0.01" value="{{ old('source_amount') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none text-lg font-semibold" placeholder="0,00">
                @error('source_amount')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            {{-- Distribution Type Selection --}}
            <div class="mb-4">
                <label class="mb-2 block text-sm font-medium text-slate-600">Dağıtım Yöntemi</label>
                <div class="flex flex-wrap md:flex-nowrap items-center gap-3">
                    <select name="distribution_type" id="distribution_type" class="w-full md:w-1/2 rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none">
                        <option value="equal" @selected(old('distribution_type', 'equal') === 'equal')>Eşit dağıtım</option>
                        <option value="square_meters" @selected(old('distribution_type') === 'square_meters')>Metrekareye göre</option>
                        <option value="share_coefficient" @selected(old('distribution_type') === 'share_coefficient')>Gider Dağıtım Katsayısına Göre</option>
                        <option value="consumption" @selected(old('distribution_type') === 'consumption')>Tüketime göre (Sayaç ve Pay Ölçer)</option>
                    </select>
                    <div class="shrink-0 flex items-center gap-2">
                        <input type="checkbox" id="round_to_integer" name="round_to_integer" value="1" @checked(old('round_to_integer')) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <label for="round_to_integer" class="text-sm text-slate-600 whitespace-nowrap">Tamsayıya yukarı yuvarla</label>
                    </div>
                </div>
                @error('distribution_type')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div id="consumption-fields" class="mb-4 hidden grid gap-3 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-600">Tüketim Türü<select name="consumption_type" id="consumption-type" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3">@foreach(\App\Models\ConsumptionSetting::TYPES as $key => $label)<option value="{{ $key }}" @selected(old('consumption_type', 'cold_water') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="text-sm font-medium text-slate-600">Okuma Dönemi<select name="consumption_period_id" id="consumption-period" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><option value="">Dönem seçin</option>@foreach($consumptionPeriods as $readingPeriod)<option value="{{ $readingPeriod->id }}" data-type="{{ $readingPeriod->consumption_type }}" @selected(old('consumption_period_id') == $readingPeriod->id)>{{ $readingPeriod->start_date->format('d.m.Y') }} – {{ $readingPeriod->end_date->format('d.m.Y') }}</option>@endforeach</select></label>
                <p class="text-xs text-slate-500 sm:col-span-2">Yalnızca borçlandırılmamış dönemler listelenir. Dairelerin tutarları sonraki adımda ayrı önizlemede gösterilir.</p>
                @error('consumption_type')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('consumption_period_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
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
                <p id="target-audience-summary" aria-live="polite" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ $selectedDueType === 'malik_payi' ? 'Sadece kat malikleri borçlandırılır.' : 'Kiracı öncelikli: varsa kiracı, yoksa kat maliki borçlandırılır.' }}</p>
                <p class="mt-2 text-xs text-slate-500">Borç türüne göre otomatik belirlenir.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="description" class="mb-2 block text-sm font-medium text-slate-600">Açıklama</label>
                    <input id="description" name="description" value="{{ old('description') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:outline-none" placeholder="Örn. Nisan aidatı, demirbaş giderleri vb.">
                    @error('description')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            @include('late-compensation._choice', [
                'sources' => [
                    'manual' => \App\Support\DueProductionSource::ManualBatch,
                    'fixed' => \App\Support\DueProductionSource::FixedBatch,
                    'expenses' => \App\Support\DueProductionSource::ExpenseBatch,
                    'consumption' => \App\Support\DueProductionSource::Consumption,
                ],
                'dueType' => $selectedDueType,
                'initialSource' => old('distribution_type') === 'consumption' && in_array($selectedSource, ['manual', 'expenses'], true)
                    ? 'consumption' : $selectedSource,
            ])
            <button id="batch-submit" type="submit" class="w-full md:w-auto rounded-xl bg-emerald-600 px-8 py-3.5 text-sm font-semibold text-white hover:bg-emerald-700 shadow-sm">Toplu Borçlandırmayı Oluştur</button>
        </div>
    </form>

    {{-- Missing Unit Data Modal --}}
    <div id="missing-data-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
            <h3 class="text-lg font-bold text-slate-950">Hesaplama Yapılamıyor</h3>
            <p id="missing-data-message" class="mt-2 text-sm text-slate-600"></p>
            <div class="mt-6 flex flex-col gap-2">
                <a href="{{ route('units.index') }}" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-emerald-700">Daire Ayarlarına Git</a>
                <button type="button" id="close-missing-data-modal" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Kapat</button>
            </div>
        </div>
    </div>

    @include('components.period-picker', ['periodPickerId' => 'expense-source', 'periodPickerRange' => true, 'periodPickerAllowClear' => false])

    <script>
        (() => {
            const units = {{ $units }};
            const unitsData = {!! json_encode($unitsData->values()) !!};

            const sourceRadios = document.querySelectorAll('input[name="source_type"]');
            const expensesFields = document.getElementById('expenses-fields');
            const manualFields = document.getElementById('manual-fields');
            const fixedFields = document.getElementById('fixed-fields');
            const batchTypeFields = document.getElementById('batch-type-fields');
            const batchDateFields = document.getElementById('batch-date-fields');
            const fixedTypeSlot = document.getElementById('fixed-type-slot');
            const fixedDatesSlot = document.getElementById('fixed-dates-slot');
            const manualTypeSlot = document.getElementById('manual-type-slot');
            const manualDatesSlot = document.getElementById('manual-dates-slot');
            const expensesDetailsFields = document.getElementById('expenses-details-fields');
            const expensesTypeSlot = document.getElementById('expenses-type-slot');
            const expensesDatesSlot = document.getElementById('expenses-dates-slot');
            const fixedAmountField = document.getElementById('fixed-amount-field');
            const manualAmountField = document.getElementById('manual-amount-field');
            const fixedAmount = document.getElementById('fixed_amount');
            const sourcePeriod = document.getElementById('expense-source-period');
            const sourcePeriodEnd = document.getElementById('expense-source-period-end');
            let expenseRequestVersion = 0;
            const sourceAmount = document.getElementById('source_amount');
            const expenseTotalDisplay = document.getElementById('expense-total-display');
            const expenseTotalAmount = document.getElementById('expense-total-amount');
            const dueDateInput = document.getElementById('due_date');
            let isDueDateManuallySet = false;

            const calcSummary = document.getElementById('calc-summary');
            const roundToInteger = document.getElementById('round_to_integer');
            const distSelect = document.getElementById('distribution_type');
            const missingDataModal = document.getElementById('missing-data-modal');
            const missingDataMessage = document.getElementById('missing-data-message');
            const closeMissingDataModal = document.getElementById('close-missing-data-modal');
            let lastDistributionType = distSelect?.value || 'equal';
            const calcBtn = document.getElementById('calc-btn');
            const expenseList = document.getElementById('expense-list');
            const expenseListEmpty = document.getElementById('expense-list-empty');
            const expenseListSummary = document.getElementById('expense-list-summary');
            const selectedExpenseCount = document.getElementById('selected-expense-count');
            const selectedExpenseIdsInput = document.getElementById('selected_expense_ids');

            let currentExpenses = [];
            let selectedExpenseIds = new Set();

            const formatMoney = (amount, fractionDigits = 2) => {
                return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: fractionDigits, maximumFractionDigits: fractionDigits }).format(amount) + ' TL';
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

            const getRoundedTotal = (total) => {
                const distType = document.getElementById('distribution_type')?.value;
                if (!total || total <= 0 || !unitsData.length) return 0;

                const activeUnits = unitsData.filter(u => {
                    if (distType === 'square_meters') return u.sqm > 0;
                    if (distType === 'share_coefficient') return u.coef > 0;
                    return true;
                });
                if (activeUnits.length === 0) return 0;

                const totalWeight = activeUnits.reduce((s, u) => {
                    if (distType === 'square_meters') return s + u.sqm;
                    if (distType === 'share_coefficient') return s + u.coef;
                    return s + 1;
                }, 0);

                const baseTotal = Math.ceil(total);
                let sum = 0;
                activeUnits.forEach(u => {
                    const w = distType === 'equal' ? 1 : (distType === 'square_meters' ? u.sqm : u.coef);
                    sum += Math.ceil(baseTotal * w / totalWeight);
                });
                return sum;
            };

            const updateCalculation = () => {
                const selectedSource = document.querySelector('input[name="source_type"]:checked').value;
                const consumptionMode = document.getElementById('distribution_type').value === 'consumption' && ['manual', 'expenses'].includes(selectedSource);
                document.getElementById('consumption-fields').classList.toggle('hidden', !consumptionMode);
                for (const id of ['consumption-type', 'consumption-period']) {
                    document.getElementById(id).disabled = !consumptionMode;
                    document.getElementById(id).required = consumptionMode;
                }
                document.getElementById('batch-submit').textContent = consumptionMode ? 'Önizlemeyi Gör' : 'Toplu Borçlandırmayı Oluştur';
                if (consumptionMode) {
                    calcSummary.classList.add('hidden');
                    document.getElementById('distribution-preview').classList.add('hidden');
                    return;
                }
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
                    const round = roundToInteger?.checked;
                    const displayTotal = round ? getRoundedTotal(total) : total;
                    const displayPerUnit = round ? Math.ceil(displayTotal / units) : (total / units);
                    calcSummary.textContent = `Toplam: ${formatMoney(displayTotal, round ? 0 : 2)} / Daire: ${formatMoney(displayPerUnit, round ? 0 : 2)}`;
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

                const round = roundToInteger?.checked;
                const baseTotal = round ? Math.ceil(total) : total;

                if (!baseTotal || baseTotal <= 0 || !unitsData.length) {
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
                    warningEl.textContent = zeroCount + ' dairenin ' + (distType === 'square_meters' ? 'metrekare' : 'gider dağıtım katsayısı') + ' bilgisi 0 veya boş — bu daireler dağıtımdan hariç tutulur.';
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
                activeUnits.forEach(u => {
                    const w = distType === 'equal' ? 1 : (distType === 'square_meters' ? u.sqm : u.coef);
                    const share = round
                        ? Math.ceil(baseTotal * w / totalWeight)
                        : (u === activeUnits[activeUnits.length - 1]
                            ? Math.round((baseTotal - allocated) * 100) / 100
                            : Math.round(baseTotal * w / totalWeight * 100) / 100);
                    allocated += share;
                    shares.push({ unit: u, w, share });
                });

                const totalToUse = round ? allocated : baseTotal;

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
                        <div class="font-bold text-slate-900 tabular-nums">${formatMoney(g.share, round ? 0 : 2)} / daire</div>
                    </div>`;
                });

                groupsEl.innerHTML = html;
                totalLbl.textContent = formatMoney(totalToUse, round ? 0 : 2) + ' · ' + activeUnits.length + ' daire';
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
                const periodEnd = sourcePeriodEnd.value || period;
                const requestVersion = ++expenseRequestVersion;
                if (!period) {
                    alert('Lütfen bir dönem seçin.');
                    return;
                }

                calcBtn.disabled = true;
                calcBtn.textContent = 'Yükleniyor...';

                try {
                    const parameters = new URLSearchParams({period, period_end: periodEnd});
                    const response = await fetch(@json(route('dues.expenses.by-period')) + '?' + parameters);
                    
                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error('Server error:', response.status, errorText);
                        throw new Error(`Server error: ${response.status}`);
                    }
                    
                    const loaded = await response.json();
                    if (requestVersion !== expenseRequestVersion) return;
                    currentExpenses = loaded;
                    
                    if (currentExpenses.error) {
                        throw new Error(currentExpenses.error);
                    }
                    
                    selectedExpenseIds.clear();
                    selectedExpenseIdsInput.value = '';
                    
                    expenseListEmpty.textContent = 'Seçili dönem aralığında gider bulunamadı.';
                    renderExpenseList();
                    updateCalculation();
                } catch (error) {
                    if (requestVersion !== expenseRequestVersion) return;
                    console.error('Error fetching expenses:', error);
                    alert('Giderler yüklenirken bir hata oluştu: ' + error.message);
                } finally {
                    if (requestVersion === expenseRequestVersion) {
                    calcBtn.disabled = false;
                    calcBtn.textContent = 'Giderleri Getir';
                    }
                }
            });
            sourcePeriod.addEventListener('change', () => {
                expenseRequestVersion++;
                currentExpenses = [];
                selectedExpenseIds.clear();
                selectedExpenseIdsInput.value = '';
                selectedExpenseCount.textContent = '0';
                calcBtn.disabled = false;
                calcBtn.textContent = 'Giderleri Getir';
                expenseListEmpty.textContent = 'Dönem değişti. Giderleri yeniden getirin.';
                renderExpenseList();
                updateCalculation();
            });

            const distTypeWrapper = document.getElementById('distribution_type')?.closest('.mb-4');

            const toggleFields = () => {
                const selected = document.querySelector('input[name="source_type"]:checked').value;
                expensesFields.classList.toggle('hidden', selected !== 'expenses');
                expensesDetailsFields.classList.toggle('hidden', selected !== 'expenses');
                manualFields.classList.toggle('hidden', selected !== 'manual');
                fixedFields.classList.toggle('hidden', selected !== 'fixed');
                fixedAmountField.classList.toggle('hidden', selected !== 'fixed');
                manualAmountField.classList.toggle('hidden', selected !== 'manual');
                const typeSlot = { fixed: fixedTypeSlot, manual: manualTypeSlot, expenses: expensesTypeSlot }[selected];
                const datesSlot = { fixed: fixedDatesSlot, manual: manualDatesSlot, expenses: expensesDatesSlot }[selected];
                typeSlot.appendChild(batchTypeFields);
                datesSlot.appendChild(batchDateFields);
                if (distTypeWrapper) distTypeWrapper.classList.toggle('hidden', selected === 'fixed');
                const consumptionOption = distSelect.querySelector('option[value="consumption"]');
                consumptionOption.disabled = !['manual', 'expenses'].includes(selected);
                if (consumptionOption.disabled && distSelect.value === 'consumption') { distSelect.value = 'equal'; lastDistributionType = 'equal'; }
                updateCalculation();
            };

            sourceRadios.forEach(radio => radio.addEventListener('change', toggleFields));
            const methodSummary = document.getElementById('batch-method-summary');
            const methodLabels = @json($sourceLabels);
            if (methodSummary) {
                sourceRadios.forEach(radio => radio.addEventListener('click', () => {
                    document.getElementById('batch-selected-method').textContent = methodLabels[radio.value];
                    document.getElementById('batch-source-options').classList.add('hidden');
                    methodSummary.classList.remove('hidden');
                    if (window.matchMedia('(max-width: 767px)').matches) {
                        methodSummary.querySelector('button').focus({ preventScroll: true });
                    }
                }));
            }
            sourceAmount.addEventListener('input', updateCalculation);
            fixedAmount?.addEventListener('input', updateCalculation);
            const handleDistChange = () => {
                const newType = distSelect?.value;
                const hasMissing = newType === 'square_meters'
                    ? unitsData.some(u => !u.sqm || u.sqm <= 0)
                    : (newType === 'share_coefficient' ? unitsData.some(u => !u.coef || u.coef <= 0) : false);
                if (hasMissing) {
                    const label = newType === 'square_meters' ? 'metrekare' : 'gider dağıtım katsayısı';
                    missingDataMessage.textContent = `${label.charAt(0).toUpperCase() + label.slice(1)} dağıtımı yapabilmek için tüm dairelerin ${label} bilgisi girilmiş olmalıdır.`;
                    missingDataModal?.classList.remove('hidden');
                    distSelect.value = lastDistributionType;
                    return;
                }
                lastDistributionType = newType;
                updateCalculation();
            };

            distSelect?.addEventListener('change', handleDistChange);
            roundToInteger?.addEventListener('change', updateCalculation);

            closeMissingDataModal?.addEventListener('click', () => {
                missingDataModal?.classList.add('hidden');
            });

            const consumptionType = document.getElementById('consumption-type');
            const consumptionPeriod = document.getElementById('consumption-period');
            const filterConsumptionPeriods = () => {
                for (const option of consumptionPeriod.options) {
                    if (!option.value) continue;
                    const visible = option.dataset.type === consumptionType.value;
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (!visible && option.selected) consumptionPeriod.value = '';
                }
            };
            consumptionType.addEventListener('change', filterConsumptionPeriods);
            filterConsumptionPeriods();

            // Initialize
            toggleFields();

            // Auto-populate description based on period and category
            const typeInputs = document.querySelectorAll('input[name="due_type"]');
            const selectedDueType = () => document.querySelector('input[type="hidden"][name="due_type"]')?.value || '';
            const targetAudienceSummary = document.getElementById('target-audience-summary');
            const syncTargetAudience = () => {
                const ownerOnly = selectedDueType() === 'malik_payi';
                targetAudienceSummary.textContent = ownerOnly
                    ? 'Sadece kat malikleri borçlandırılır.'
                    : 'Kiracı öncelikli: varsa kiracı, yoksa kat maliki borçlandırılır.';
            };
            const topicSelect = document.getElementById('category_id');
            const categoryField = document.getElementById('category-field');
            const createdAtInput = document.getElementById('created_at_manual');
            const descriptionInput = document.getElementById('description');

            const months = {
                '01': 'Ocak', '02': 'Şubat', '03': 'Mart', '04': 'Nisan',
                '05': 'Mayıs', '06': 'Haziran', '07': 'Temmuz', '08': 'Ağustos',
                '09': 'Eylül', '10': 'Ekim', '11': 'Kasım', '12': 'Aralık'
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

            let lastSuggestedDescription = '';
            let descriptionIsManual = descriptionInput.value.trim() !== '';
            descriptionInput.addEventListener('input', () => {
                descriptionIsManual = descriptionInput.value.trim() !== '' && descriptionInput.value !== lastSuggestedDescription;
            });
            const updateDescription = () => {
                const period = createdAtInput?.value?.substring(0, 7);
                const categoryName = topicSelect?.value ? topicSelect.selectedOptions[0]?.textContent.trim() : '';
                let suggestion = '';
                if (categoryName) {
                    const [year, month] = (period || '').split('-');
                    suggestion = period ? `${year} ${months[month] || month} - ${categoryName}` : categoryName;
                }
                if (!descriptionIsManual) descriptionInput.value = suggestion;
                lastSuggestedDescription = suggestion;
            };
            topicSelect?.addEventListener('change', updateDescription);

            createdAtInput?.addEventListener('change', () => {
                updateDescription();
                syncDueDateFromCreatedAt();
            });
            dueDateInput?.addEventListener('input', () => {
                isDueDateManuallySet = dueDateInput.value !== '';
            });
            const syncCategoryField = () => {
                if (!topicSelect) return;
                let selectedVisible = false;
                Array.from(topicSelect.options).forEach(option => {
                    if (!option.value) return;
                    const visible = (option.dataset.usages || '').split(',').includes(selectedDueType());
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (visible && option.selected) selectedVisible = true;
                });
                if (!selectedVisible) topicSelect.value = '';
            };

            typeInputs.forEach(input => input.addEventListener('change', () => {
                syncCategoryField();
                syncTargetAudience();
                updateDescription();
            }));

            // Init
            syncTargetAudience();
            syncCategoryField();
            updateDescription();
            if (!dueDateInput.value) {
                syncDueDateFromCreatedAt();
            }
        })();
    </script>
@endsection
