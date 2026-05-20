@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Aidatlar</h1>
            <p class="mt-1 text-sm text-slate-500">Tekil veya toplu aidat tahakkukları burada yönetilecek.</p>
        </div>
        <div class="flex gap-2">
            @if ($activePlans->isNotEmpty())
                <button type="button" onclick="document.getElementById('modal-generate-plan').classList.remove('hidden')"
                        class="flex-1 md:flex-none rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 text-center">
                    Plandan Borçlandır
                </button>
            @endif
            <a href="{{ route('dues.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 text-center">Borçlandır</a>
            <a href="{{ route('dues.batch.create') }}" class="flex-1 md:flex-none rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 text-center">Toplu Borçlandır</a>
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

    {{-- Filtre Barı --}}
    <form method="GET" action="{{ route('dues.index') }}" class="mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Ara</label>
            <input type="text" name="search" value="{{ $filters['filterSearch'] ?? '' }}" placeholder="Ad, daire no veya açıklama..."
                   class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none w-52">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Dönem</label>
            <input type="month" name="period" value="{{ $filters['filterPeriod'] }}"
                   class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Durum</label>
            <select name="status" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                <option value="">Tüm Durumlar</option>
                <option value="unpaid"  {{ $filters['filterStatus'] === 'unpaid'  ? 'selected' : '' }}>Bekliyor</option>
                <option value="partial" {{ $filters['filterStatus'] === 'partial' ? 'selected' : '' }}>Kısmen Ödendi</option>
                <option value="paid"    {{ $filters['filterStatus'] === 'paid'    ? 'selected' : '' }}>Ödendi</option>
                <option value="overdue" {{ $filters['filterStatus'] === 'overdue' ? 'selected' : '' }}>Gecikmiş</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Kaynak</label>
            <select name="source" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                <option value="">Tüm Kaynaklar</option>
                <option value="plan"   {{ $filters['filterSource'] === 'plan'   ? 'selected' : '' }}>Aidat Planı</option>
                <option value="batch"  {{ $filters['filterSource'] === 'batch'  ? 'selected' : '' }}>Toplu Borçlandırma</option>
                <option value="manual" {{ $filters['filterSource'] === 'manual' ? 'selected' : '' }}>Manuel</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrele</button>
            @if (($filters['filterSearch'] ?? '') || $filters['filterPeriod'] || $filters['filterStatus'] || $filters['filterSource'] || $filters['filterBatchId'])
                <a href="{{ route('dues.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Temizle</a>
            @endif
        </div>
        @if ($filters['filterBatchId'])
            <input type="hidden" name="batch_id" value="{{ $filters['filterBatchId'] }}">
            <div class="flex items-center gap-1.5 rounded-full bg-violet-50 border border-violet-200 px-3 py-1.5 text-xs font-semibold text-violet-700">
                Toplu kayıt filtresi aktif
                <a href="{{ route('dues.index') }}" class="ml-1 text-violet-500 hover:text-violet-800">&times;</a>
            </div>
        @endif
    </form>

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
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Daire / Hesap</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Oluşturulma</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Kategori</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">
                        <a href="{{ route('dues.index', ['sort_by' => 'amount', 'sort_direction' => $sortBy === 'amount' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center justify-end gap-1 hover:text-slate-700">Tutar @if ($sortBy === 'amount')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <a href="{{ route('dues.index', ['sort_by' => 'status', 'sort_direction' => $sortBy === 'status' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 hover:text-slate-700">Durum @if ($sortBy === 'status')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($dues as $due)
                    @php
                        $isOverdue = $due->status !== 'paid' && $due->due_date && $due->due_date->isPast();
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors" data-id="{{ $due->id }}">
                        <td class="px-4 py-4">
                            <input type="checkbox" class="row-check rounded border-slate-300 text-slate-700 cursor-pointer" value="{{ $due->id }}" data-status="{{ $due->status }}">
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-semibold text-slate-900">{{ $due->unit ? str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-' }} No.lu Daire</div>
                            <div class="text-xs text-slate-500 mt-0.5">{{ $due->account?->name }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="text-slate-700 tabular-nums">{{ $due->created_at_manual ? \Carbon\Carbon::parse($due->created_at_manual)->format('d.m.Y') : $due->created_at->format('d.m.Y') }}</div>
                            <div class="mt-1">
                                @if ($due->batch?->plan)
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Aidat Planı ile</span>
                                @elseif ($due->batch)
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Toplu Borçlandırma</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Manuel</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-4 text-slate-700">{{ $due->category?->name ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-600 max-w-48 truncate">{{ $due->description ?: '-' }}</td>
                        <td class="px-5 py-4 text-right">
                            <div class="font-semibold text-slate-900 tabular-nums">{{ number_format($due->amount, 2, ',', '.') }} TL</div>
                            @if($due->remaining_amount > 0 && $due->remaining_amount != $due->amount)
                                <div class="text-xs text-amber-600 mt-0.5">Kalan: {{ number_format($due->remaining_amount, 2, ',', '.') }} TL</div>
                            @elseif($due->remaining_amount == 0)
                                <div class="text-xs text-emerald-600 mt-0.5">Ödendi</div>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if ($isOverdue)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>Gecikmiş
                                </span>
                            @elseif ($due->status === 'paid')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Ödendi
                                </span>
                            @elseif ($due->status === 'partial')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Kısmi
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Bekliyor
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-2">
                                @if ($due->status !== 'paid')
                                    <a href="{{ route('dues.payment.create', $due) }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition-colors">Tahsil Et</a>
                                @endif
                                <a href="{{ route('dues.show', $due) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Detay</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-slate-400">Henüz aidat kaydı yok.</td></tr>
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
            <div class="rounded-xl bg-white p-4 shadow-sm border border-slate-200">
                {{-- Header: Unit & Status --}}
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="text-lg font-bold text-slate-900">
                            {{ $due->unit ? str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-' }} No.lu Daire
                        </div>
                        <div class="text-sm text-slate-600">{{ $due->account?->name }}</div>
                    </div>
                    <div>
                        @if ($isOverdue)
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-red-600"></span>
                                Gecikmiş
                            </span>
                        @elseif ($due->status === 'paid')
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                                Ödendi
                            </span>
                        @elseif ($due->status === 'partial')
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                                Kısmi
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                                Bekliyor
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Info Grid --}}
                <div class="grid grid-cols-2 gap-3 mb-3 text-sm">
                    <div>
                        <div class="text-xs text-slate-500 mb-1">Oluşturulma</div>
                        <div class="font-medium text-slate-900 tabular-nums">{{ $due->created_at_manual ? \Carbon\Carbon::parse($due->created_at_manual)->format('d.m.Y') : $due->created_at->format('d.m.Y') }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 mb-1">Kategori</div>
                        <div class="font-medium text-slate-900">{{ $due->category?->name ?? '-' }}</div>
                    </div>
                    <div class="col-span-2">
                        <div class="text-xs text-slate-500 mb-1">Açıklama</div>
                        <div class="font-medium text-slate-900">{{ $due->description ?: '-' }}</div>
                    </div>
                </div>

                {{-- Amount Section --}}
                <div class="bg-slate-50 rounded-lg p-3 mb-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs text-slate-500 mb-1">Tutar</div>
                            <div class="text-lg font-bold text-slate-900">{{ number_format($due->amount, 2, ',', '.') }} TL</div>
                        </div>
                        @if($due->remaining_amount > 0 && $due->remaining_amount != $due->amount)
                            <div class="text-right">
                                <div class="text-xs text-slate-500 mb-1">Kalan</div>
                                <div class="text-base font-semibold text-amber-600">{{ number_format($due->remaining_amount, 2, ',', '.') }} TL</div>
                            </div>
                        @elseif($due->remaining_amount == 0)
                            <div class="text-right">
                                <div class="text-xs text-emerald-600 font-medium">Tamamen Ödendi</div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex gap-2">
                    @if ($due->status !== 'paid')
                        <a href="{{ route('dues.payment.create', $due) }}" class="flex-1 rounded-lg bg-emerald-600 px-3 py-2.5 text-sm font-semibold text-white text-center hover:bg-emerald-700">
                            Tahsil Et
                        </a>
                    @endif
                    <a href="{{ route('dues.show', $due) }}" class="flex-1 rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">
                        Detay
                    </a>
                </div>
            </div>
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
