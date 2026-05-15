@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Aidatlar</h1>
            <p class="mt-1 text-sm text-slate-500">Tekil veya toplu aidat tahakkukları burada yönetilecek.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('dues.create') }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Borçlandır</a>
            <a href="{{ route('dues.batch.create') }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Toplu Borçlandır</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr>
                <th class="px-5 py-3"><a href="{{ route('dues.index', ['sort_by' => 'created_at', 'sort_direction' => $sortBy === 'created_at' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 cursor-pointer hover:text-slate-700">Ref No @if ($sortBy === 'created_at')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                <th class="px-5 py-3"><a href="{{ route('dues.index', ['sort_by' => 'unit_id', 'sort_direction' => $sortBy === 'unit_id' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 cursor-pointer hover:text-slate-700">Daire @if ($sortBy === 'unit_id')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                <th class="px-5 py-3">Hesap</th>
                <th class="px-5 py-3">Kategori</th>
                <th class="px-5 py-3">Dönem</th>
                <th class="px-5 py-3">Açıklama</th>
                <th class="px-5 py-3 text-right"><a href="{{ route('dues.index', ['sort_by' => 'amount', 'sort_direction' => $sortBy === 'amount' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center justify-end gap-1 cursor-pointer hover:text-slate-700">Tutar @if ($sortBy === 'amount')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                <th class="px-5 py-3 text-right">Kalan</th>
                <th class="px-5 py-3"><a href="{{ route('dues.index', ['sort_by' => 'status', 'sort_direction' => $sortBy === 'status' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 cursor-pointer hover:text-slate-700">Durum @if ($sortBy === 'status')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                <th class="px-5 py-3 text-right">İşlemler</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($dues as $due)
                    <tr>
                        <td class="px-5 py-4">{{ $due->reference_number ?? '-' }}</td>
                        <td class="px-5 py-4">{{ $due->unit ? str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-' }}</td>
                        <td class="px-5 py-4">{{ $due->account?->name }}</td>
                        <td class="px-5 py-4">{{ $due->category?->name ?? '-' }}</td>
                        <td class="px-5 py-4">{{ $due->period }}</td>
                        <td class="px-5 py-4">{{ $due->description ?: '-' }}</td>
                        <td class="px-5 py-4 text-right">{{ number_format($due->amount, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4 text-right">{{ number_format($due->remaining_amount, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4">
                            @php
                                $statusLabels = [
                                    'paid'     => 'Ödendi',
                                    'partial'  => 'Kısmi Ödeme',
                                    'unpaid'   => 'Ödenmedi',
                                    'overdue'  => 'Gecikmiş',
                                ];
                            @endphp
                            {{ $statusLabels[$due->status] ?? $due->status }}
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if ($due->status !== 'paid')
                                    <a href="{{ route('dues.payment.create', $due) }}" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Ödeme Ekle</a>
                                @endif
                                <a href="{{ route('dues.show', $due) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">İncele</a>
                                <a href="{{ route('dues.edit', $due) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-5 py-8 text-center text-slate-500">Henüz aidat kaydı yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
