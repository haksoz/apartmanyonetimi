@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Aidatlar</h1>
            <p class="mt-1 text-sm text-slate-500">Tekil veya toplu aidat tahakkukları burada yönetilecek.</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('dues.export', array_merge(request()->query(), [])) }}"
               class="flex-1 md:flex-none rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 text-center">
                Excel'e Aktar
            </a>
            @if($isOwner)
                @if ($activePlans->isNotEmpty())
                    <button type="button" onclick="document.getElementById('modal-generate-plan').classList.remove('hidden')"
                            class="flex-1 md:flex-none rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 text-center">
                        Plandan Borçlandır
                    </button>
                @endif
                <a href="{{ route('dues.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 text-center">Borçlandır</a>
                <a href="{{ route('dues.batch.create') }}" class="flex-1 md:flex-none rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 text-center">Toplu Borçlandır</a>
            @endif
        </div>
    </div>

    {{-- Plandan Borçlandır Modal --}}
    @if ($activePlans->isNotEmpty())
        <div id="modal-generate-plan" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                    <h2 class="text-base font-semibold text-slate-950">Plandan Borçlandır</h2>
                    <button type="button" onclick="document.getElementById('modal-generate-plan').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form id="form-generate-plan" method="POST" action="" class="px-6 py-5 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Aidat Planı</label>
                        <select name="_plan_id" id="modal-plan-select"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none" required>
                            <option value="">— Plan seçin —</option>
                            @foreach ($activePlans as $plan)
                                <option value="{{ $plan->id }}"
                                        data-action="{{ route('due-plans.generate-month', $plan) }}"
                                        data-category="{{ $plan->category?->name ?? '' }}">
                                    {{ $plan->name }} ({{ $plan->year }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Dönem</label>
                        <input type="month" name="period" value="{{ now()->format('Y-m') }}"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Açıklama</label>
                        <input type="text" name="description" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button type="submit" class="flex-1 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">Oluştur</button>
                        <button type="button" onclick="document.getElementById('modal-generate-plan').classList.add('hidden')" class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            var trMonths = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

            function turkishAccusative(word) {
                if (!word) return '';
                var backVowels  = ['a', 'ı', 'o', 'u'];
                var frontVowels = ['e', 'i', 'ö', 'ü'];
                var vowels = ['a','e','ı','i','o','ö','u','ü'];
                var lower = word.toLowerCase()
                    .replace(/ı/g,'ı').replace(/İ/g,'i')
                    .replace(/ş/g,'ş').replace(/Ş/g,'ş')
                    .replace(/ğ/g,'ğ').replace(/Ğ/g,'ğ')
                    .replace(/ç/g,'ç').replace(/Ç/g,'ç')
                    .replace(/ö/g,'ö').replace(/Ö/g,'ö')
                    .replace(/ü/g,'ü').replace(/Ü/g,'ü');
                var lastVowel = '';
                for (var i = lower.length - 1; i >= 0; i--) {
                    if (vowels.indexOf(lower[i]) !== -1) { lastVowel = lower[i]; break; }
                }
                var suffix = backVowels.indexOf(lastVowel) !== -1
                    ? (lastVowel === 'a' || lastVowel === 'ı' ? 'ı' : 'u')
                    : (lastVowel === 'e' || lastVowel === 'i' ? 'i' : 'ü');
                var lastChar = lower[lower.length - 1];
                var needsBuffer = vowels.indexOf(lastChar) !== -1;
                return word + (needsBuffer ? 'y' : '') + suffix;
            }

            function updateModalDescription() {
                var planSelect  = document.getElementById('modal-plan-select');
                var periodInput = document.querySelector('#form-generate-plan input[name="period"]');
                var descInput   = document.querySelector('#form-generate-plan input[name="description"]');

                var selectedOpt  = planSelect.options[planSelect.selectedIndex];
                var categoryName = selectedOpt?.dataset?.category || '';
                var period       = periodInput?.value || '';

                var label = '';
                if (period) {
                    var parts = period.split('-');
                    var month = trMonths[parseInt(parts[1], 10) - 1] || '';
                    var year  = parts[0] || '';
                    var suffix = categoryName ? turkishAccusative(categoryName) : '';
                    label = month + ' ' + year + (suffix ? ' ' + suffix : '');
                }

                if (descInput && !descInput.dataset.userEdited) {
                    descInput.value = label;
                }
            }

            document.getElementById('modal-plan-select').addEventListener('change', function() {
                document.getElementById('form-generate-plan').action = this.options[this.selectedIndex].dataset.action || '';
                updateModalDescription();
            });

            document.querySelector('#form-generate-plan input[name="period"]').addEventListener('change', updateModalDescription);

            document.querySelector('#form-generate-plan input[name="description"]').addEventListener('input', function() {
                this.dataset.userEdited = '1';
            });

            document.getElementById('modal-generate-plan').addEventListener('click', function(e) {
                if (e.target === this) this.classList.add('hidden');
            });

            updateModalDescription();
        </script>
    @endif

    {{-- Arama + Filtre --}}
    <div class="mb-4 flex flex-col md:flex-row gap-2">

        {{-- Arama --}}
        <form method="GET" action="{{ route('dues.index') }}" class="flex gap-2 flex-1">
            @if ($filters['filterPeriod'])
                <input type="hidden" name="period" value="{{ $filters['filterPeriod'] }}">
            @endif
            @if ($filters['filterStatus'])
                <input type="hidden" name="status" value="{{ $filters['filterStatus'] }}">
            @endif
            @if ($filters['filterSource'])
                <input type="hidden" name="source" value="{{ $filters['filterSource'] }}">
            @endif
            @if ($filters['filterBatchId'])
                <input type="hidden" name="batch_id" value="{{ $filters['filterBatchId'] }}">
            @endif
            <input type="text" name="search" value="{{ $filters['filterSearch'] ?? '' }}"
                placeholder="Ad, daire no veya açıklama..."
                class="flex-1 rounded-xl border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ara</button>
            @if ($filters['filterSearch'] ?? '')
                <a href="{{ route('dues.index', array_filter(['period' => $filters['filterPeriod'], 'status' => $filters['filterStatus'], 'source' => $filters['filterSource'], 'batch_id' => $filters['filterBatchId']])) }}"
                   class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-50">✕</a>
            @endif
        </form>

        {{-- Filtreler --}}
        <form method="GET" action="{{ route('dues.index') }}" class="flex gap-2 items-center flex-wrap md:flex-nowrap">
            @if ($filters['filterSearch'] ?? '')
                <input type="hidden" name="search" value="{{ $filters['filterSearch'] }}">
            @endif
            @if ($filters['filterBatchId'])
                <input type="hidden" name="batch_id" value="{{ $filters['filterBatchId'] }}">
            @endif
            <input type="month" name="period" value="{{ $filters['filterPeriod'] }}"
                class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            <select name="status" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                <option value="">Tüm Durumlar</option>
                <option value="unpaid"  {{ $filters['filterStatus'] === 'unpaid'  ? 'selected' : '' }}>Bekliyor</option>
                <option value="partial" {{ $filters['filterStatus'] === 'partial' ? 'selected' : '' }}>Kısmen Ödendi</option>
                <option value="paid"    {{ $filters['filterStatus'] === 'paid'    ? 'selected' : '' }}>Ödendi</option>
                <option value="overdue" {{ $filters['filterStatus'] === 'overdue' ? 'selected' : '' }}>Gecikmiş</option>
            </select>
            <select name="unit_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                <option value="">Tüm Daireler</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}" {{ $filters['filterUnitId'] == $unit->id ? 'selected' : '' }}>Daire {{ str_pad($unit->unit_no, 2, '0', STR_PAD_LEFT) }}</option>
                @endforeach
            </select>
            <select name="account_type" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                <option value="">Tüm Hesaplar</option>
                <option value="owner" {{ $filters['filterAccountType'] === 'owner' ? 'selected' : '' }}>Kat Maliki</option>
                <option value="tenant" {{ $filters['filterAccountType'] === 'tenant' ? 'selected' : '' }}>Kiracı</option>
                <option value="supplier" {{ $filters['filterAccountType'] === 'supplier' ? 'selected' : '' }}>Tedarikçi</option>
            </select>
            <select name="source" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                <option value="">Tüm Kaynaklar</option>
                <option value="plan"   {{ $filters['filterSource'] === 'plan'   ? 'selected' : '' }}>Aidat Planı</option>
                <option value="batch"  {{ $filters['filterSource'] === 'batch'  ? 'selected' : '' }}>Toplu Borçlandırma</option>
                <option value="manual" {{ $filters['filterSource'] === 'manual' ? 'selected' : '' }}>Manuel</option>
            </select>
            @if ($hasImported)
                <label class="flex items-center gap-1.5 cursor-pointer text-xs text-slate-500 select-none whitespace-nowrap">
                    <input type="checkbox" name="show_imported" value="1" class="rounded" {{ $showImported ? 'checked' : '' }}>
                    Devir Öncesini Göster
                </label>
            @endif
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrele</button>
            @if ($filters['filterPeriod'] || $filters['filterStatus'] || $filters['filterSource'] || $filters['filterBatchId'] || $filters['filterUnitId'] || $filters['filterAccountType'] || $showImported)
                <a href="{{ route('dues.index', array_filter(['search' => $filters['filterSearch']])) }}"
                   class="text-xs text-slate-400 hover:text-slate-600 whitespace-nowrap">Temizle</a>
            @endif
        </form>
    </div>

    @if ($filters['filterBatchId'])
        <div class="mb-3 flex items-center gap-1.5 rounded-full bg-violet-50 border border-violet-200 px-3 py-1.5 text-xs font-semibold text-violet-700 w-fit">
            Toplu kayıt filtresi aktif
            <a href="{{ route('dues.index') }}" class="ml-1 text-violet-500 hover:text-violet-800">&times;</a>
        </div>
    @endif

    {{-- Toplu Aksiyon Barı --}}
    <form id="form-bulk-destroy" method="POST" action="{{ route('dues.bulk-destroy') }}" onsubmit="return confirmBulkDelete()">
        @csrf
        @method('DELETE')
        <input type="hidden" name="ids" id="bulk-ids">
        <div id="bulk-action-bar" class="hidden mb-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
            <span id="bulk-count" class="text-sm font-semibold text-red-700"></span>
            <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Seçilileri Sil</button>
            <button type="button" onclick="clearSelection()" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Seçimi Temizle</button>
        </div>
    </form>

    {{-- Desktop Table View --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-3.5 w-10">
                        <input type="checkbox" id="check-all" class="rounded border-slate-300 text-slate-700 cursor-pointer">
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Oluşturulma</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">
                        <a href="{{ route('dues.index', ['sort_by' => 'amount', 'sort_direction' => $sortBy === 'amount' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center justify-end gap-1 hover:text-slate-700">Tutar @if ($sortBy === 'amount')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">Kalan</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($dues as $due)
                    @php
                        $isOverdue = $due->status !== 'paid' && $due->due_date && $due->due_date->isPast();
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors cursor-pointer" data-id="{{ $due->id }}" onclick="window.location.href='{{ route('dues.show', $due) }}'">
                        <td class="px-4 py-4" onclick="event.stopPropagation()">
                            <input type="checkbox" class="row-check rounded border-slate-300 text-slate-700 cursor-pointer" value="{{ $due->id }}" data-status="{{ $due->status }}">
                        </td>
                        <td class="px-5 py-4">
                            <div class="text-slate-900 font-medium">
                                {{ $due->description ?: '-' }}
                                @if ($due->is_imported)
                                    <span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>
                                @endif
                            </div>
                            @if ($due->account)
                                @php
                                    $title = match($due->account->type) {
                                        App\Models\Account::TYPE_OWNER => 'Kat Maliki',
                                        App\Models\Account::TYPE_TENANT => 'Kiracı',
                                        App\Models\Account::TYPE_SUPPLIER => 'Tedarikçi',
                                        default => ''
                                    };
                                @endphp
                                <div class="text-xs text-slate-500 mt-1">
                                    @if ($title){{ $title }} @endif{{ $due->account->name }}
                                    @if ($due->unit) - Daire {{ str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) }}@endif
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="text-slate-700 tabular-nums">{{ $due->created_at_manual ? \Carbon\Carbon::parse($due->created_at_manual)->format('d.m.Y') : $due->created_at->format('d.m.Y') }}</div>
                            <div class="text-xs text-slate-500 mt-1">
                                @if ($due->batch?->plan)
                                    Aidat Planı ile
                                @elseif ($due->batch)
                                    Toplu Borçlandırma
                                @else
                                    Manuel
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="font-semibold text-slate-900 tabular-nums">{{ number_format($due->amount, 2, ',', '.') }} TL</div>
                        </td>
                        <td class="px-5 py-4 text-right tabular-nums">
                            @if ($due->remaining_amount == 0)
                                <span class="text-emerald-600 font-semibold">—</span>
                            @elseif ($due->remaining_amount != $due->amount)
                                <span class="text-amber-600 font-semibold">{{ number_format($due->remaining_amount, 2, ',', '.') }} TL</span>
                            @else
                                <span class="text-slate-700 font-semibold">{{ number_format($due->remaining_amount, 2, ',', '.') }} TL</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if ($due->computed_status === 'overdue')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                                    {{ $due->category?->name ?? '-' }} Gecikti
                                </span>
                            @elseif ($due->computed_status === 'paid')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                    {{ $due->category?->name ?? '-' }} Ödendi
                                </span>
                            @elseif ($due->computed_status === 'partial')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    {{ $due->category?->name ?? '-' }} Kısmi
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                    {{ $due->category?->name ?? '-' }} Bekliyor
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                            <div class="flex items-center justify-end gap-2">
                                @if ($due->computed_status !== 'paid')
                                    <a href="{{ route('dues.payment.create', $due) }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition-colors">Tahsil Et</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Henüz aidat kaydı yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Card View --}}
    <div class="md:hidden space-y-3">
        @forelse ($dues as $due)
            @php
                $isOverdue = $due->status !== 'paid' && $due->due_date && $due->due_date->isPast();
            @endphp
            <a href="{{ route('dues.show', $due) }}" class="flex items-start justify-between rounded-xl bg-white p-3 shadow-sm border border-slate-200 hover:bg-slate-50 transition-colors">
                <div class="flex-1">
                    @if ($due->description)
                        <div class="text-base font-bold text-slate-900">{{ $due->description }}</div>
                    @endif
                    <div class="text-xs text-slate-600 mt-1">
                        <span>{{ $due->unit ? 'No '.str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-' }}</span>
                        <span class="mx-1 text-slate-400">•</span>
                        <span>{{ $due->account?->name }}</span>
                        <span class="mx-1 text-slate-400">•</span>
                        <span>{{ $due->created_at_manual ? \Carbon\Carbon::parse($due->created_at_manual)->format('d.m.Y') : $due->created_at->format('d.m.Y') }}</span>
                    </div>
                    @if($due->remaining_amount > 0 && $due->remaining_amount != $due->amount)
                        <div class="text-xs text-amber-600 mt-1">Kalan: {{ number_format($due->remaining_amount, 2, ',', '.') }} TL</div>
                    @endif
                </div>
                <div class="ml-3 text-right">
                    <div class="font-bold text-slate-900">{{ number_format($due->amount, 2, ',', '.') }} TL</div>
                    @if ($due->computed_status === 'overdue')
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-red-50 px-2 py-1 text-xs font-semibold text-red-700 mt-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-600"></span>
                            Gecikmiş
                        </span>
                    @elseif ($due->computed_status === 'paid')
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 mt-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                            Ödendi
                        </span>
                    @elseif ($due->computed_status === 'partial')
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 mt-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                            Kısmi
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 mt-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                            Bekliyor
                        </span>
                    @endif
                </div>
            </a>
        @empty
            <div class="rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm">
                Henüz aidat kaydı yok.
            </div>
        @endforelse
    </div>

    {{-- Sayfalama --}}
    @if ($dues->hasPages())
        <div class="mt-6">
            {{ $dues->links() }}
        </div>
    @endif

    <script>
        var selectedIds = new Set();

        function updateBulkBar() {
            var bar   = document.getElementById('bulk-action-bar');
            var count = document.getElementById('bulk-count');
            var input = document.getElementById('bulk-ids');
            if (selectedIds.size > 0) {
                bar.classList.remove('hidden');
                count.textContent = selectedIds.size + ' kayıt seçildi';
                input.value = Array.from(selectedIds).join(',');
            } else {
                bar.classList.add('hidden');
                input.value = '';
            }
        }

        document.getElementById('check-all').addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(function (cb) {
                cb.checked = this.checked;
                if (this.checked) selectedIds.add(cb.value);
                else selectedIds.delete(cb.value);
            }, this);
            updateBulkBar();
        });

        document.querySelectorAll('.row-check').forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (this.checked) selectedIds.add(this.value);
                else selectedIds.delete(this.value);
                document.getElementById('check-all').indeterminate =
                    selectedIds.size > 0 &&
                    selectedIds.size < document.querySelectorAll('.row-check').length;
                document.getElementById('check-all').checked =
                    selectedIds.size === document.querySelectorAll('.row-check').length;
                updateBulkBar();
            });
        });

        function clearSelection() {
            selectedIds.clear();
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
            document.getElementById('check-all').checked = false;
            document.getElementById('check-all').indeterminate = false;
            updateBulkBar();
        }

        function confirmBulkDelete() {
            var blocked = [];
            document.querySelectorAll('.row-check:checked').forEach(function (cb) {
                if (cb.dataset.status === 'paid' || cb.dataset.status === 'partial') {
                    blocked.push(cb.value);
                }
            });
            if (blocked.length > 0) {
                alert(
                    'İşlem gerçekleştirilemedi.\n\n' +
                    'Seçtiğiniz ' + blocked.length + ' kayıt ödenmiş veya kısmen ödenmiş durumda olduğu için silinemez.\n\n' +
                    'Lütfen yalnızca bekleyen ya da gecikmiş kayıtları seçin.'
                );
                return false;
            }
            return confirm(selectedIds.size + ' aidat kaydı silinecek. Devam edilsin mi?');
        }
    </script>
@endsection
