@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Giderler</h1>
            <p class="mt-1 text-sm text-slate-500">Apartman giderleri ve tedarikçi hesap bağlantıları burada yönetilecek.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('expenses.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Gider Ekle</a>
        </div>
    </div>

    {{-- Filtre Barı --}}
    <form method="GET" action="{{ route('expenses.index') }}" class="mb-5 flex flex-wrap gap-3 items-end">
        <input type="month" name="period" value="{{ $filters['filterPeriod'] }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
        <select name="status" class="rounded-xl border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            <option value="">— Tüm Durumlar —</option>
            <option value="paid"   @selected($filters['filterStatus'] === 'paid')>Ödendi</option>
            <option value="unpaid" @selected($filters['filterStatus'] === 'unpaid')>Bekliyor</option>
        </select>
        <select name="category" class="rounded-xl border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            <option value="">— Tüm Kategoriler —</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat }}" @selected($filters['filterCategory'] === $cat)>{{ $cat }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrele</button>
        @if ($filters['filterPeriod'] || $filters['filterStatus'] || $filters['filterCategory'])
            <a href="{{ route('expenses.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Temizle</a>
        @endif
    </form>

    {{-- Desktop Table View --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <a href="{{ route('expenses.index', ['sort_by' => 'expense_date', 'sort_direction' => $sortBy === 'expense_date' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 hover:text-slate-700">Tarih @if ($sortBy === 'expense_date')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Hesap</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <a href="{{ route('expenses.index', ['sort_by' => 'period_month', 'sort_direction' => $sortBy === 'period_month' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 hover:text-slate-700">Dönem @if ($sortBy === 'period_month')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <a href="{{ route('expenses.index', ['sort_by' => 'category', 'sort_direction' => $sortBy === 'category' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 hover:text-slate-700">Kategori @if ($sortBy === 'category')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Açıklama</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">
                        <a href="{{ route('expenses.index', ['sort_by' => 'amount', 'sort_direction' => $sortBy === 'amount' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center justify-end gap-1 hover:text-slate-700">Tutar @if ($sortBy === 'amount')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <a href="{{ route('expenses.index', ['sort_by' => 'is_paid', 'sort_direction' => $sortBy === 'is_paid' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 hover:text-slate-700">Durum @if ($sortBy === 'is_paid')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($expenses as $expense)
                    @php
                        $months = ['January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart', 'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',
                                   'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül', 'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'];
                        $periodText = $expense->period_month ? $expense->period_month->format('F Y') : null;
                        if ($periodText) { foreach ($months as $en => $tr) { $periodText = str_replace($en, $tr, $periodText); } }
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-5 py-4 font-semibold text-slate-900 tabular-nums">{{ $expense->expense_date->format('d.m.Y') }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $expense->account?->name ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-700 tabular-nums">{{ $periodText ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $expense->category }}</td>
                        <td class="px-5 py-4 text-slate-600 max-w-48 truncate">{{ $expense->description ?? '-' }}</td>
                        <td class="px-5 py-4 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($expense->amount, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4">
                            @if ($expense->is_paid)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Ödendi
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Bekliyor
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-2">
                                @unless ($expense->is_paid)
                                    <a href="{{ route('expenses.payment.create', $expense) }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition-colors">Ödeme Ekle</a>
                                @endunless
                                <a href="{{ route('expenses.show', $expense) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Detay</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-slate-400">Henüz gider kaydı yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Card View --}}
    <div class="md:hidden space-y-3">
        @forelse ($expenses as $expense)
            <div class="rounded-xl bg-white p-4 shadow-sm border border-slate-200">
                {{-- Header: Ref No & Status --}}
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="text-lg font-bold text-slate-900">#{{ $expense->reference_number ?? '-' }}</div>
                        <div class="text-sm text-slate-600">{{ $expense->expense_date->format('d.m.Y') }}</div>
                    </div>
                    <div>
                        @if ($expense->is_paid)
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                                Ödendi
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                                Bekliyor
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Info Grid --}}
                <div class="grid grid-cols-2 gap-3 mb-3 text-sm">
                    <div>
                        <div class="text-xs text-slate-500 mb-1">Dönem</div>
                        @php
                        $months = ['January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart', 'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',
                                   'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül', 'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'];
                        $periodText = $expense->period_month ? $expense->period_month->format('F Y') : null;
                        if ($periodText) {
                            foreach ($months as $en => $tr) {
                                $periodText = str_replace($en, $tr, $periodText);
                            }
                        }
                        @endphp
                        <div class="font-medium text-slate-900">{{ $periodText ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 mb-1">Kategori</div>
                        <div class="font-medium text-slate-900">{{ $expense->category }}</div>
                    </div>
                    <div class="col-span-2">
                        <div class="text-xs text-slate-500 mb-1">Açıklama</div>
                        <div class="font-medium text-slate-900">{{ $expense->description ?? '-' }}</div>
                    </div>
                </div>

                {{-- Amount Section --}}
                <div class="bg-slate-50 rounded-lg p-3 mb-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs text-slate-500 mb-1">Tutar</div>
                            <div class="text-lg font-bold text-slate-900">{{ number_format($expense->amount, 2, ',', '.') }} TL</div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex flex-wrap gap-2">
                    @unless ($expense->is_paid)
                        <a href="{{ route('expenses.payment.create', $expense) }}" class="flex-1 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white text-center hover:bg-emerald-700">Ödeme Ekle</a>
                    @endunless
                    <a href="{{ route('expenses.show', $expense) }}" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Detay</a>
                    <a href="{{ route('expenses.edit', $expense) }}" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Düzenle</a>
                    <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="flex-1" onsubmit="return confirm('Gider kaydı silinsin mi?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Sil</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm">
                Henüz gider kaydı yok.
            </div>
        @endforelse
    </div>

    {{-- Sayfalama --}}
    @if ($expenses->hasPages())
        <div class="mt-6">
            {{ $expenses->links() }}
        </div>
    @endif
@endsection
