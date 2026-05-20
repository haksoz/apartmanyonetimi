@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Genel Bakış</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $apartment->name }} — Tüm zamanlar
            </p>
        </div>
    </div>

    {{-- Özet Kartlar --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Daire</div>
            <div class="mt-2 text-3xl font-bold text-slate-950">{{ $totalUnits }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Aktif Hesap</div>
            <div class="mt-2 text-3xl font-bold text-slate-950">{{ $totalAccounts }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kasa Bakiyesi</div>
            <div class="mt-2 text-2xl font-bold {{ $cashBalance >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                {{ number_format($cashBalance, 2, ',', '.') }} TL
            </div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Toplam Gider</div>
            <div class="mt-2 text-2xl font-bold text-slate-950">{{ number_format($totalExpenses, 2, ',', '.') }} TL</div>
        </div>
    </div>

    {{-- Grafikler - Üst Satır --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">

        {{-- Aidat Durum Pastası --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-1">Aidat Durumu</h3>

            {{-- Kategori filtre butonları --}}
            <div class="flex flex-wrap gap-1 mb-4">
                <button onclick="selectDueCat('all')" id="due-btn-all"
                    class="due-cat-btn rounded-lg px-2.5 py-1 text-xs font-semibold bg-slate-950 text-white">
                    Tümü
                </button>
                @foreach ($dueByCat as $catId => $cat)
                    <button onclick="selectDueCat({{ $catId }})" id="due-btn-{{ $catId }}"
                        class="due-cat-btn rounded-lg px-2.5 py-1 text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200">
                        {{ $cat['name'] }}
                    </button>
                @endforeach
            </div>

            @php $dueTotal = $duePaid + $dueUnpaid + $duePartial; @endphp
            @if ($dueTotal > 0 || count($dueByCat) > 0)
                <div class="relative flex justify-center">
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
                        <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-red-400 inline-block"></span>Bekliyor</span>
                        <span id="due-label-unpaid" class="font-semibold">{{ number_format($dueUnpaid, 2, ',', '.') }} TL</span>
                    </div>
                </div>
            @else
                <div class="flex items-center justify-center h-40 text-slate-400 text-sm">Veri yok</div>
            @endif
        </div>

        {{-- Gider Kategorileri Pastası --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-1">Gider Dağılımı</h3>
            <p class="text-xs text-slate-400 mb-4">Tüm zamanlar, kategorilere göre</p>
            @if ($expenseByCategory->isNotEmpty())
                <div class="relative flex justify-center">
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
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-1">Kasa Akışı</h3>
            <p class="text-xs text-slate-400 mb-4">Toplam gelir ve gider</p>
            @if ($cashIncome + $cashExpense > 0)
                <div class="relative flex justify-center">
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

        // Aidat kategori verisi — obje olarak (key = kategori id)
        const dueByCatMap = {!! json_encode(collect($dueByCat)->mapWithKeys(fn($v, $k) => [(string)$k => $v])) !!};
        const dueAll = { paid: {{ $duePaid }}, partial: {{ $duePartial }}, unpaid: {{ $dueUnpaid }} };

        const fmt = (n) => Number(n).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' TL';

        @if ($duePaid + $dueUnpaid + $duePartial > 0 || count($dueByCat) > 0)
        const duePieChart = new Chart(document.getElementById('duePieChart'), {
            type: 'doughnut',
            data: {
                labels: ['Ödendi', 'Kısmen', 'Bekliyor'],
                datasets: [{
                    data: [{{ $duePaid }}, {{ $duePartial }}, {{ $dueUnpaid }}],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: { ...chartDefaults, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + fmt(c.raw) } } } }
        });

        function selectDueCat(catId) {
            let paid, partial, unpaid;
            if (catId === 'all') {
                paid = dueAll.paid; partial = dueAll.partial; unpaid = dueAll.unpaid;
            } else {
                const cat = dueByCatMap[String(catId)] || { paid: 0, partial: 0, unpaid: 0 };
                paid = cat.paid || 0; partial = cat.partial || 0; unpaid = cat.unpaid || 0;
            }
            duePieChart.data.datasets[0].data = [paid, partial, unpaid];
            duePieChart.update();
            document.getElementById('due-label-paid').textContent    = fmt(paid);
            document.getElementById('due-label-partial').textContent  = fmt(partial);
            document.getElementById('due-label-unpaid').textContent   = fmt(unpaid);

            document.querySelectorAll('.due-cat-btn').forEach(b => {
                b.classList.remove('bg-slate-950', 'text-white');
                b.classList.add('bg-slate-100', 'text-slate-600');
            });
            const activeBtn = document.getElementById('due-btn-' + catId);
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
