@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Aidatlar</h1>
            <p class="mt-1 text-sm text-slate-500">Tekil veya toplu aidat tahakkukları burada yönetilecek.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('dues.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 text-center">Borçlandır</a>
            <a href="{{ route('dues.batch.create') }}" class="flex-1 md:flex-none rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 text-center">Toplu Borçlandır</a>
        </div>
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr>
                <th class="px-5 py-3">Daire / Hesap</th>
                <th class="px-5 py-3">Kategori</th>
                <th class="px-5 py-3">Dönem</th>
                <th class="px-5 py-3">Açıklama</th>
                <th class="px-5 py-3 text-right"><a href="{{ route('dues.index', ['sort_by' => 'amount', 'sort_direction' => $sortBy === 'amount' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center justify-end gap-1 cursor-pointer hover:text-slate-700">Tutar @if ($sortBy === 'amount')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                <th class="px-5 py-3"><a href="{{ route('dues.index', ['sort_by' => 'status', 'sort_direction' => $sortBy === 'status' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 cursor-pointer hover:text-slate-700">Durum @if ($sortBy === 'status')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                <th class="px-5 py-3 text-right">İşlemler</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($dues as $index => $due)
                    @php
                        $isOverdue = $due->status !== 'paid' && $due->due_date && $due->due_date->isPast();
                    @endphp
                    <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-slate-50' }} hover:bg-slate-100 transition-colors">
                        <td class="px-5 py-4 font-medium text-slate-900">
                            {{ $due->unit ? str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-' }} {{ $due->account?->name }}
                        </td>
                        <td class="px-5 py-4 text-slate-600">{{ $due->category?->name ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $due->period }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $due->description ?: '-' }}</td>
                        <td class="px-5 py-4 text-right">
                            <div class="font-medium text-slate-900">{{ number_format($due->amount, 2, ',', '.') }} TL</div>
                            @if($due->remaining_amount > 0)
                                <div class="text-xs text-amber-600">Kalan: {{ number_format($due->remaining_amount, 2, ',', '.') }} TL</div>
                            @else
                                <div class="text-xs text-emerald-600">Ödendi</div>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if ($isOverdue)
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-600/20">
                                    <span class="h-1.5 w-1.5 rounded-full bg-red-600"></span>
                                    Gecikmiş
                                </span>
                            @elseif ($due->status === 'paid')
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                                    Ödendi
                                </span>
                            @elseif ($due->status === 'partial')
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                                    Kısmi
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-500/20">
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                                    Bekliyor
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5">
                                @if ($due->status !== 'paid')
                                    <a href="{{ route('dues.payment.create', $due) }}" class="rounded bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Tahsil Et</a>
                                @endif
                                <a href="{{ route('dues.show', $due) }}" class="rounded border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Detay</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">Henüz aidat kaydı yok.</td></tr>
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
                        <div class="text-xs text-slate-500 mb-1">Dönem</div>
                        <div class="font-medium text-slate-900">{{ $due->period }}</div>
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
@endsection
