@extends('layouts.app')

@section('content')
    @if ($partialAidatConfirmation)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl border-2 border-red-500">
                <h2 class="text-lg font-bold text-red-600">Planlı Aidat Oluşturulma Hatası!!</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    {{ \Carbon\Carbon::parse($partialAidatConfirmation['period'].'-01')->locale('tr')->isoFormat('MMMM YYYY') }} döneminde oluşturulması gereken aidatlar {{ $partialAidatConfirmation['completed_count'] }} hesapta oluşturulmuş aidat olduğu için oluşturulamadı. Kalan {{ $partialAidatConfirmation['missing_count'] }} hesap için plan aidatı oluşturulsun mu?
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="this.closest('.fixed').remove()" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Şimdi Değil</button>
                    <form method="POST" action="{{ route('due-plans.generate-month', $partialAidatConfirmation['plan']) }}">
                        @csrf
                        <input type="hidden" name="period" value="{{ $partialAidatConfirmation['period'] }}">
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Eksikleri Oluştur</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Genel Bakış</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $apartment->name }} — Tüm zamanlar
            </p>
        </div>
    </div>

    {{-- Hızlı Erişim Butonları --}}
    @php
        $quickActions = collect([
            ['label' => 'Gider Ekle', 'route' => route('expenses.create'), 'show' => true],
            ['label' => 'Aidat Ekle', 'route' => route('dues.create'), 'show' => true],
            ['label' => 'Hesaplar', 'route' => route('accounts.index'), 'show' => $navIsOwner],
            ['label' => 'Kasa', 'route' => route('cash.index'), 'show' => $navIsOwner],
        ])->where('show', true)->values();
    @endphp
    <div class="grid gap-0 mb-6 {{ $quickActions->count() === 2 ? 'grid-cols-2' : 'grid-cols-3 sm:grid-cols-4' }}">
        @foreach ($quickActions as $i => $action)
            <a href="{{ $action['route'] }}"
               class="relative items-center justify-center min-h-[3.5rem] w-full bg-white text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-50 active:scale-[0.98] transition-colors border border-slate-300 px-2 sm:px-3 truncate
                      {{ $i > 0 ? 'border-l-0' : '' }}
                      {{ $loop->first ? 'rounded-l-2xl' : '' }}
                      {{ $loop->last ? 'rounded-r-2xl' : '' }}
                      {{ $action['label'] === 'Kasa' ? 'hidden sm:flex' : 'flex' }}
                      {{ $action['label'] === 'Hesaplar' ? 'rounded-r-2xl sm:rounded-r-none' : '' }}">
                {{ $action['label'] }}
            </a>
        @endforeach
    </div>

    {{-- Özet Kartlar --}}
    {{-- Masaüstü: 3 ayrı kart --}}
    <div class="hidden sm:grid sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kasa Bakiyesi</div>
            <div class="mt-2 text-2xl font-bold {{ $cashBalance >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                {{ number_format($cashBalance, 2, ',', '.') }} TL
            </div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Ödenmemiş Gider</div>
            <div class="mt-2 text-2xl font-bold text-red-600">{{ number_format($expenseUnpaid, 2, ',', '.') }} TL</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tahsil Edilmemiş Aidat</div>
            <div class="mt-2 text-2xl font-bold text-amber-600">{{ number_format($uncollectedDues, 2, ',', '.') }} TL</div>
        </div>
    </div>

    {{-- Mobil: Birleşik minimal kart --}}
    <div class="rounded-2xl bg-white shadow-sm sm:hidden mb-6">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 last:border-0">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kasa Bakiyesi</span>
            <span class="text-lg font-bold {{ $cashBalance >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                {{ number_format($cashBalance, 2, ',', '.') }} TL
            </span>
        </div>
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 last:border-0">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Ödenmemiş Gider</span>
            <span class="text-lg font-bold text-red-600">{{ number_format($expenseUnpaid, 2, ',', '.') }} TL</span>
        </div>
        <div class="flex items-center justify-between px-5 py-4">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tahsil Edilmemiş Aidat</span>
            <span class="text-lg font-bold text-amber-600">{{ number_format($uncollectedDues, 2, ',', '.') }} TL</span>
        </div>
    </div>

    {{-- Grafikler - Üst Satır --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">

        {{-- Aidat Durum Pastası --}}
        <div class="rounded-2xl bg-white p-4 sm:p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-1">Aidat Durumu</h3>

            {{-- Tür filtre butonları --}}
            <div class="flex flex-wrap gap-1 mb-4">
                <button onclick="selectDueType('all')" id="due-btn-all"
                    class="due-type-btn rounded-lg px-2.5 py-1 text-xs font-semibold bg-slate-950 text-white">
                    Tümü
                </button>
                @foreach ($dueByType as $typeValue => $type)
                    <button onclick="selectDueType('{{ $typeValue }}')" id="due-btn-{{ $typeValue }}"
                        class="due-type-btn rounded-lg px-2.5 py-1 text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200">
                        {{ $type['name'] }}
                    </button>
                @endforeach
            </div>

            @php $dueTotal = $duePaid + $dueUnpaid + $duePartial + $dueOverdue; @endphp
            @if ($dueTotal > 0 || count($dueByType) > 0)
                <div class="relative hidden sm:flex justify-center">
                    <canvas id="duePieChart" width="180" height="180"></canvas>
                </div>
                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>Ödendi</span>
                        <span id="due-label-paid" class="font-semibold">{{ number_format($duePaid, 2, ',', '.') }} TL</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block"></span>Kısmen</span>
                        <span id="due-label-partial" class="font-semibold">{{ number_format($duePartial, 2, ',', '.') }} TL</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-red-600 inline-block"></span>Gecikmiş</span>
                        <span id="due-label-overdue" class="font-semibold">{{ number_format($dueOverdue, 2, ',', '.') }} TL</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-red-400 inline-block"></span>Bekliyor</span>
                        <span id="due-label-unpaid" class="font-semibold">{{ number_format($dueUnpaid, 2, ',', '.') }} TL</span>
                    </div>
                </div>
            @else
                <div class="flex items-center justify-center h-40 text-slate-400 text-sm">Veri yok</div>
            @endif
        </div>

        {{-- Gider Kategorileri Pastası --}}
        <div class="rounded-2xl bg-white p-4 sm:p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-1">Gider Dağılımı</h3>
            <p class="text-xs text-slate-400 mb-4">Tüm zamanlar, kategorilere göre</p>
            @if ($expenseByCategory->isNotEmpty())
                <div class="relative hidden sm:flex justify-center">
                    <canvas id="expensePieChart" width="180" height="180"></canvas>
                </div>
                <div class="mt-4 space-y-1.5">
                    @foreach ($expenseByCategory->take(5) as $cat => $amt)
                        <div class="flex items-center justify-between text-xs">
                            <span class="flex items-center gap-2 truncate max-w-[60%]">
                                <span class="w-2.5 h-2.5 rounded-full inline-block flex-shrink-0" style="background:{{ ['#6366f1','#f59e0b','#10b981','#ef4444','#3b82f6','#8b5cf6','#ec4899','#14b8a6'][$loop->index % 8] }}"></span>
                                {{ $cat }}
                            </span>
                            <span class="font-semibold">{{ number_format($amt, 0, ',', '.') }} TL</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex items-center justify-center h-40 text-slate-400 text-sm">Veri yok</div>
            @endif
        </div>

        {{-- Kasa Gelir/Gider Pastası --}}
        <div class="rounded-2xl bg-white p-4 sm:p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-1">Kasa Akışı</h3>
            <p class="text-xs text-slate-400 mb-4">Toplam gelir ve gider</p>
            @if ($cashIncome + $cashExpense > 0)
                <div class="relative hidden sm:flex justify-center">
                    <canvas id="cashPieChart" width="180" height="180"></canvas>
                </div>
                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>Toplam Gelir</span>
                        <span class="font-semibold">{{ number_format($cashIncome, 2, ',', '.') }} TL</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-red-400 inline-block"></span>Toplam Gider</span>
                        <span class="font-semibold">{{ number_format($cashExpense, 2, ',', '.') }} TL</span>
                    </div>
                    <div class="border-t border-slate-100 pt-2 flex items-center justify-between text-xs font-semibold">
                        <span>Net Bakiye</span>
                        <span class="{{ $cashBalance >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($cashBalance, 2, ',', '.') }} TL</span>
                    </div>
                </div>
            @else
                <div class="flex items-center justify-center h-40 text-slate-400 text-sm">Veri yok</div>
            @endif
        </div>
    </div>

    {{-- Son 6 Ay Bar Grafiği --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm mb-4">
        <h3 class="text-sm font-semibold text-slate-700 mb-1">Son 6 Ay</h3>
        <p class="text-xs text-slate-400 mb-4">Aidat tahakkuku ve gider karşılaştırması</p>
        <canvas id="monthlyBarChart" height="90"></canvas>
    </div>

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const chartDefaults = {
            plugins: { legend: { display: false } },
            cutout: '65%',
        };

        // Aidat tür verisi — obje olarak (key = tür değeri)
        const dueByTypeMap = {!! json_encode($dueByType) !!};
        const dueAll = { paid: {{ $duePaid }}, partial: {{ $duePartial }}, overdue: {{ $dueOverdue }}, unpaid: {{ $dueUnpaid }} };

        const fmt = (n) => Number(n).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' TL';

        @if ($duePaid + $dueUnpaid + $duePartial + $dueOverdue > 0 || count($dueByType) > 0)
        const duePieChart = new Chart(document.getElementById('duePieChart'), {
            type: 'doughnut',
            data: {
                labels: ['Ödendi', 'Kısmen', 'Gecikmiş', 'Bekliyor'],
                datasets: [{
                    data: [{{ $duePaid }}, {{ $duePartial }}, {{ $dueOverdue }}, {{ $dueUnpaid }}],
                    backgroundColor: ['#10b981', '#f59e0b', '#dc2626', '#f87171'],
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: { ...chartDefaults, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + fmt(c.raw) } } } }
        });

        function selectDueType(typeValue) {
            let paid, partial, overdue, unpaid;
            if (typeValue === 'all') {
                paid = dueAll.paid; partial = dueAll.partial; overdue = dueAll.overdue; unpaid = dueAll.unpaid;
            } else {
                const type = dueByTypeMap[typeValue] || { paid: 0, partial: 0, overdue: 0, unpaid: 0 };
                paid = type.paid || 0; partial = type.partial || 0; overdue = type.overdue || 0; unpaid = type.unpaid || 0;
            }
            duePieChart.data.datasets[0].data = [paid, partial, overdue, unpaid];
            duePieChart.update();
            document.getElementById('due-label-paid').textContent    = fmt(paid);
            document.getElementById('due-label-partial').textContent  = fmt(partial);
            document.getElementById('due-label-overdue').textContent  = fmt(overdue);
            document.getElementById('due-label-unpaid').textContent   = fmt(unpaid);

            document.querySelectorAll('.due-type-btn').forEach(b => {
                b.classList.remove('bg-slate-950', 'text-white');
                b.classList.add('bg-slate-100', 'text-slate-600');
            });
            const activeBtn = document.getElementById('due-btn-' + typeValue);
            if (activeBtn) {
                activeBtn.classList.add('bg-slate-950', 'text-white');
                activeBtn.classList.remove('bg-slate-100', 'text-slate-600');
            }
        }
        @endif

        @if ($expenseByCategory->isNotEmpty())
        new Chart(document.getElementById('expensePieChart'), {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($expenseByCategory->keys()) !!},
                datasets: [{
                    data: {!! json_encode($expenseByCategory->values()) !!},
                    backgroundColor: ['#6366f1','#f59e0b','#10b981','#ef4444','#3b82f6','#8b5cf6','#ec4899','#14b8a6'],
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: { ...chartDefaults, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + Number(c.raw).toLocaleString('tr-TR') + ' TL' } } } }
        });
        @endif

        @if ($cashIncome + $cashExpense > 0)
        new Chart(document.getElementById('cashPieChart'), {
            type: 'doughnut',
            data: {
                labels: ['Gelir', 'Gider'],
                datasets: [{
                    data: [{{ $cashIncome }}, {{ $cashExpense }}],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: { ...chartDefaults, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + Number(c.raw).toLocaleString('tr-TR') + ' TL' } } } }
        });
        @endif

        new Chart(document.getElementById('monthlyBarChart'), {
            type: 'bar',
            data: {
                labels: {!! json_encode($monthLabels) !!},
                datasets: [
                    {
                        label: 'Aidat Tahakkuku',
                        data: {!! json_encode($monthDueData) !!},
                        backgroundColor: '#6366f1',
                        borderRadius: 6,
                    },
                    {
                        label: 'Gider',
                        data: {!! json_encode($monthExpData) !!},
                        backgroundColor: '#f59e0b',
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 12 } } },
                    tooltip: { callbacks: { label: (c) => ' ' + c.dataset.label + ': ' + Number(c.raw).toLocaleString('tr-TR') + ' TL' } }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { ticks: { callback: (v) => v.toLocaleString('tr-TR') + ' TL' }, grid: { color: '#f1f5f9' } }
                },
                responsive: true,
            }
        });
    </script>
@endsection
