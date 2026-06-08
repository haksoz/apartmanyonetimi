@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Hesaplar</h1>
            <p class="mt-1 text-sm text-slate-500">Daire sakinleri, tedarikçiler ve apartmanla ilişkili tüm hesaplar.</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            @if($isOwner && $hasImported)
            <form method="POST" action="/accounts/imported-transactions" onsubmit="return confirm('Tüm içe aktarılmış cari hareketler ve bağlı giderler silinecek. Emin misiniz?')">
                @csrf
                <button type="submit" class="flex-1 md:flex-none rounded-xl border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">İçe Aktarılanları Sil</button>
            </form>
            @endif
            @if($isOwner)
            <a href="{{ route('accounts.bulk-import') }}" class="flex-1 md:flex-none rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-emerald-700">Genel Hesap İçe Aktar</a>
            @endif
            <a href="{{ route('accounts.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Hesap Ekle</a>
        </div>
    </div>

    {{-- Arama + Filtre --}}
    <div class="mb-5 flex flex-col md:flex-row gap-2">

        {{-- Arama --}}
        <form method="GET" action="{{ route('accounts.index') }}" class="flex gap-2 flex-1">
            @if ($filters['filterType'])
                <input type="hidden" name="type" value="{{ $filters['filterType'] }}">
            @endif
            @if (($filters['filterStatus'] ?? 'active') !== 'active')
                <input type="hidden" name="status" value="{{ $filters['filterStatus'] }}">
            @endif
            <input type="text" name="search" value="{{ $filters['filterSearch'] }}"
                placeholder="Ad veya daire no..."
                class="flex-1 rounded-xl border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ara</button>
            @if ($filters['filterSearch'])
                <a href="{{ route('accounts.index', array_filter(['type' => $filters['filterType'], 'status' => $filters['filterStatus']])) }}"
                   class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-50">✕</a>
            @endif
        </form>

        {{-- Filtreler --}}
        <form method="GET" action="{{ route('accounts.index') }}" class="flex gap-2 items-center flex-wrap md:flex-nowrap">
            @if ($filters['filterSearch'])
                <input type="hidden" name="search" value="{{ $filters['filterSearch'] }}">
            @endif
            <select name="type" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                <option value="">— Tüm Tipler —</option>
                <option value="owner"    @selected($filters['filterType'] === 'owner')>Kat Maliki</option>
                <option value="tenant"   @selected($filters['filterType'] === 'tenant')>Kiracı</option>
                <option value="supplier" @selected($filters['filterType'] === 'supplier')>Tedarikçi</option>
            </select>
            <select name="status" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                <option value="active"   @selected(($filters['filterStatus'] ?? 'active') === 'active')>Aktif</option>
                <option value="inactive" @selected(($filters['filterStatus'] ?? '') === 'inactive')>Pasif</option>
                <option value="all"      @selected(($filters['filterStatus'] ?? '') === 'all')>Tümü</option>
            </select>
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrele</button>
            @if ($filters['filterType'] || ($filters['filterStatus'] ?? 'active') !== 'active')
                <a href="{{ route('accounts.index', array_filter(['search' => $filters['filterSearch']])) }}"
                   class="text-xs text-slate-400 hover:text-slate-600 whitespace-nowrap">Temizle</a>
            @endif
        </form>
    </div>

    {{-- Hesapsız Ödemeler Kartı --}}
    @if($orphanPaymentsCount > 0)
    <div class="mb-5">
        <a href="{{ route('payments.index') }}?filter=orphan" class="flex items-center justify-between p-4 rounded-xl bg-amber-50 border border-amber-200 hover:bg-amber-100 transition-colors">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-amber-100">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-amber-900">Hesapsız Ödemeler</div>
                    <div class="text-sm text-amber-700">{{ $orphanPaymentsCount }} adet tahsis edilecek ödeme</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold text-amber-900">{{ number_format($orphanPaymentsTotal, 2, ',', '.') }} TL</div>
                <div class="text-xs text-amber-600">Tahsis için tıklayın</div>
            </div>
        </a>
    </div>
    @endif

    @php
        $sortBy  = $filters['sortBy']  ?? 'name';
        $sortDir = $filters['sortDir'] ?? 'asc';
        $baseParams = array_filter([
            'search'    => $filters['filterSearch'],
            'type'      => $filters['filterType'],
            'status'    => ($filters['filterStatus'] ?? 'active') !== 'active' ? $filters['filterStatus'] : null,
        ]);
        $sortLink = function (string $col) use ($sortBy, $sortDir, $baseParams): string {
            $dir = ($sortBy === $col && $sortDir === 'asc') ? 'desc' : 'asc';
            return route('accounts.index', array_merge($baseParams, ['sort' => $col, 'direction' => $dir]));
        };
        $sortIcon = function (string $col) use ($sortBy, $sortDir): string {
            if ($sortBy !== $col) {
                return '<span class="inline-flex flex-col leading-none ml-1 text-slate-300"><span>▲</span><span>▼</span></span>';
            }
            return $sortDir === 'asc'
                ? '<span class="ml-1 text-slate-700">▲</span>'
                : '<span class="ml-1 text-slate-700">▼</span>';
        };
    @endphp

    {{-- Desktop Table View --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-center">Daire</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <a href="{{ $sortLink('name') }}" class="inline-flex items-center gap-0.5 hover:text-slate-700">Adı Soyadı / Ünvan {!! $sortIcon('name') !!}</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <a href="{{ $sortLink('type') }}" class="inline-flex items-center gap-0.5 hover:text-slate-700">Tip {!! $sortIcon('type') !!}</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Durum</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">
                        <a href="{{ $sortLink('debit_total') }}" class="inline-flex items-center gap-0.5 hover:text-slate-700">Borç {!! $sortIcon('debit_total') !!}</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">
                        <a href="{{ $sortLink('credit_total') }}" class="inline-flex items-center gap-0.5 hover:text-slate-700">Alacak {!! $sortIcon('credit_total') !!}</a>
                    </th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">
                        <a href="{{ $sortLink('balance') }}" class="inline-flex items-center gap-0.5 hover:text-slate-700">Bakiye {!! $sortIcon('balance') !!}</a>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($accounts as $account)
                    @php
                        $debit = (float) ($account->debit_total ?? 0);
                        $credit = (float) ($account->credit_total ?? 0);
                        $balance = $credit - $debit;
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors cursor-pointer {{ ! $account->is_active ? 'bg-slate-50/50 text-slate-400' : '' }}" onclick="window.location.href='{{ route('accounts.show', $account) }}'">
                        <td class="px-5 py-4 text-center tabular-nums">
                            @if ($account->unit)
                                <span class="text-xs font-semibold text-slate-700">No: {{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}</span>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-semibold {{ $account->is_active ? 'text-slate-900' : 'text-slate-500' }}">{{ $account->name }}</div>
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-600">{{ $account->type_label }}</td>
                        <td class="px-5 py-4">
                            @if ($account->is_active)
                                <span class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 font-medium">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Aktif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-500">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-300 inline-block"></span> Pasif
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right font-medium text-red-600 tabular-nums">{{ number_format($debit, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4 text-right font-medium text-emerald-600 tabular-nums">{{ number_format($credit, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4 text-right font-semibold tabular-nums {{ $balance < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                            <div class="flex items-center justify-end gap-2">
                                <span>{{ number_format(abs($balance), 2, ',', '.') }} TL</span>
                                @if ($balance < 0)
                                    <span class="text-xs text-red-500">(B)</span>
                                @elseif ($balance > 0)
                                    <span class="text-xs text-emerald-500">(A)</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Henüz hesap yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Card View --}}
    <div class="md:hidden space-y-3">
        @forelse ($accounts as $account)
            @php
                $debit = (float) ($account->debit_total ?? 0);
                $credit = (float) ($account->credit_total ?? 0);
                $balance = $credit - $debit;
            @endphp
            <a href="{{ route('accounts.show', $account) }}" class="flex items-start justify-between rounded-xl bg-white p-3 shadow-sm border border-slate-200 hover:bg-slate-50 transition-colors">
                <div class="flex-1">
                    <div class="text-sm text-slate-900">
                        <span class="font-bold">{{ $account->name }}</span>
                        <span class="mx-1 text-slate-400">•</span>
                        <span class="text-xs text-slate-500">{{ $account->type_label }}</span>
                        @if ($account->unit)
                            <span class="mx-1 text-slate-400">•</span>
                            <span class="text-xs text-slate-500">Daire {{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3 mt-1 text-xs text-slate-600">
                        <span>Borç: {{ number_format($debit, 2, ',', '.') }} TL</span>
                        <span>Alacak: {{ number_format($credit, 2, ',', '.') }} TL</span>
                    </div>
                </div>
                <div class="ml-3 text-right">
                    <div class="font-bold {{ $balance < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format(abs($balance), 2, ',', '.') }} TL</div>
                    @if ($balance < 0)
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-red-50 px-2 py-1 text-xs font-semibold text-red-700 mt-1">(B)</span>
                    @elseif ($balance > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 mt-1">(A)</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm">
                Henüz hesap yok.
            </div>
        @endforelse
    </div>

    {{-- Sayfalama --}}
    @if ($accounts->hasPages())
        <div class="mt-6">
            {{ $accounts->links() }}
        </div>
    @endif
@endsection
