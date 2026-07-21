@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Kasa</h1>
            <p class="mt-1 text-sm text-slate-500">Tahsilat ve giderlerden oluşan kasa hareketleri.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('cash-boxes.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Kasa Ekle</a>
        </div>
    </div>

    <div class="mb-6 rounded-2xl bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 md:grid md:grid-cols-3 md:gap-4 md:divide-x md:divide-slate-100">
            <div class="flex items-center justify-between md:flex-col md:justify-center md:text-center px-2">
                <div class="text-sm text-slate-500">Gelir</div>
                <div class="text-lg md:text-2xl font-bold text-emerald-600">{{ number_format($income, 2, ',', '.') }} TL</div>
            </div>
            <div class="flex items-center justify-between md:flex-col md:justify-center md:text-center px-2">
                <div class="text-sm text-slate-500">Gider</div>
                <div class="text-lg md:text-2xl font-bold text-red-600">{{ number_format($expense, 2, ',', '.') }} TL</div>
            </div>
            <div class="flex items-center justify-between md:flex-col md:justify-center md:text-center px-2">
                <div class="text-sm text-slate-500">Bakiye</div>
                <div class="text-lg md:text-2xl font-bold text-slate-900">{{ number_format($balance, 2, ',', '.') }} TL</div>
            </div>
        </div>
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
                        <a href="{{ route('cash-boxes.show', $cashBox) }}" class="rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Hesap Hareketleri</a>
                        <a href="{{ route('cash-boxes.edit', $cashBox) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
                        @if ($cashBox->transactions->isNotEmpty())
                            <button type="button" onclick="alert('Bu kasada işlem kaydı olduğu için silinemez.')" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Sil</button>
                        @else
                            <form method="POST" action="{{ route('cash-boxes.destroy', $cashBox) }}" onsubmit="return confirm('Kasa silinsin mi?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Sil</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-sm text-slate-500">Henüz kasa tanımı yok.</div>
            @endforelse
        </div>
    </div>

@endsection
