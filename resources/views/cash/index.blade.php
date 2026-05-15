@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Kasa</h1>
            <p class="mt-1 text-sm text-slate-500">Tahsilat ve giderlerden oluşan kasa hareketleri.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('cash-boxes.create') }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Kasa Ekle</a>
            <a href="{{ route('cash.create') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Kasa Hareketi Ekle</a>
        </div>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Gelir</div><div class="mt-2 text-2xl font-bold text-emerald-600">{{ number_format($income, 2, ',', '.') }} TL</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Gider</div><div class="mt-2 text-2xl font-bold text-red-600">{{ number_format($expense, 2, ',', '.') }} TL</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Bakiye</div><div class="mt-2 text-2xl font-bold">{{ number_format($balance, 2, ',', '.') }} TL</div></div>
    </div>

    <div class="mb-6 rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-slate-950">Kasalar</h2>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($cashBoxes as $cashBox)
                @php
                    $cashBoxIncome = $cashBox->transactions->where('type', 'income')->sum('amount');
                    $cashBoxExpense = $cashBox->transactions->where('type', 'expense')->sum('amount');
                    $cashBoxBalance = $cashBoxIncome - $cashBoxExpense;
                @endphp
                <div class="rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold text-slate-950">{{ $cashBox->name }}</div>
                            <div class="mt-1 text-sm text-slate-500">{{ $cashBox->description ?: 'Açıklama yok' }}</div>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $cashBox->is_active ? 'bg-slate-100 text-slate-700' : 'bg-amber-50 text-amber-700' }}">{{ $cashBox->is_active ? 'Aktif' : 'Pasif' }}</span>
                    </div>
                    <div class="mt-4 text-2xl font-bold text-slate-950">{{ number_format($cashBoxBalance, 2, ',', '.') }} TL</div>
                    @if ($cashBox->bank_name || $cashBox->iban || $cashBox->account_number)
                        <div class="mt-2 text-xs text-slate-500">{{ $cashBox->bank_name }} {{ $cashBox->account_number }} {{ $cashBox->iban }}</div>
                    @endif
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('cash.index', ['cash_box_id' => $cashBox->id]) }}" class="rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Hareketleri Gör</a>
                        <a href="{{ route('cash-boxes.edit', $cashBox) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
                        <form method="POST" action="{{ route('cash-boxes.destroy', $cashBox) }}" onsubmit="return confirm('Kasa silinsin mi?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Sil</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="text-sm text-slate-500">Henüz kasa tanımı yok.</div>
            @endforelse
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-slate-950">Kasa Hareketleri</h2>
        @if ($selectedCashBox)
            <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                Seçili Kasa: <span class="font-semibold text-slate-950">{{ $selectedCashBox->name }}</span>
            </div>
            @forelse ($transactions as $transaction)
                <div class="flex flex-col gap-3 border-b border-slate-100 py-4 text-sm last:border-0 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('cash.show', $transaction) }}" class="font-medium text-slate-950 hover:underline">{{ $transaction->description ?: ucfirst($transaction->type) }}</a>
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $transaction->type === 'income' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">{{ $transaction->type === 'income' ? 'Gelir' : 'Gider' }}</span>
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $transaction->is_active ? 'bg-slate-100 text-slate-700' : 'bg-amber-50 text-amber-700' }}">{{ $transaction->is_active ? 'Aktif' : 'Pasif' }}</span>
                            @if ($transaction->reference_number)
                                <span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">{{ $transaction->reference_number }}</span>
                            @endif
                        </div>
                        <div class="mt-1 text-slate-500">{{ $transaction->transaction_date->format('d.m.Y') }} @if ($transaction->category) · {{ $transaction->category->name }} @endif @if ($transaction->account) · {{ $transaction->account->name }} @endif</div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="font-semibold">{{ number_format($transaction->amount, 2, ',', '.') }} TL</div>
                        <a href="{{ route('cash.edit', $transaction) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
                        <form method="POST" action="{{ route('cash.destroy', $transaction) }}" onsubmit="return confirm('Kasa hareketi silinsin mi?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Sil</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="py-6 text-sm text-slate-500">Seçili kasaya ait hareket bulunamadı.</div>
            @endforelse
        @else
            <div class="py-6 text-sm text-slate-500">Kasa hareketlerini görmek için bir kasa seçin.</div>
        @endif
    </div>
@endsection
