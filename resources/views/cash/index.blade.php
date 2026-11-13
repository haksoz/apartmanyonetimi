@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Kasa</h1>
            <p class="mt-1 text-sm text-slate-500">Tahsilat ve giderlerden oluşan kasa hareketleri.</p>
        </div>
        <div class="hidden gap-2 md:flex">
            <a href="{{ route('cash-boxes.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Kasa Ekle</a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="mb-6 md:rounded-2xl md:bg-white md:p-6 md:shadow-sm">
    <div class="grid grid-cols-3 gap-1 rounded-2xl bg-slate-100 p-1 text-center ring-1 ring-inset ring-slate-200/60 sm:gap-3 sm:rounded-none sm:bg-transparent sm:p-0 sm:text-left sm:ring-0">
        <div class="min-w-0 rounded-xl bg-emerald-50 px-1 py-3 sm:border sm:border-emerald-200 sm:p-4">
            <div class="text-xs font-medium text-emerald-700 sm:text-sm">Toplam Gelir</div>
            <div class="mt-1 whitespace-nowrap text-[clamp(0.65rem,3.5vw,1rem)] font-bold leading-tight text-emerald-700 tabular-nums sm:mt-2 sm:text-2xl">{{ number_format($income, 2, ',', '.') }} TL</div>
        </div>
        <div class="min-w-0 rounded-xl bg-white px-1 py-3 shadow-sm sm:border sm:border-slate-200 sm:bg-slate-50 sm:p-4 sm:shadow-none">
            <div class="text-xs font-medium text-slate-500 sm:text-sm sm:text-slate-600">Toplam Gider</div>
            <div class="mt-1 whitespace-nowrap text-[clamp(0.65rem,3.5vw,1rem)] font-bold leading-tight text-red-600 tabular-nums sm:mt-2 sm:text-2xl">{{ number_format($expense, 2, ',', '.') }} TL</div>
        </div>
        <div class="min-w-0 rounded-xl px-1 py-3 sm:border sm:p-4 {{ $balance < 0 ? 'bg-red-50 sm:border-red-200' : 'bg-white sm:border-slate-200 sm:bg-slate-50' }}">
            <div class="text-xs font-medium text-slate-500 sm:text-sm sm:text-slate-600">Toplam Bakiye</div>
            <div class="mt-1 whitespace-nowrap text-[clamp(0.65rem,3.5vw,1rem)] font-bold leading-tight tabular-nums sm:mt-2 sm:text-2xl {{ $balance >= 0 ? 'text-slate-900' : 'text-red-600' }}">{{ number_format($balance, 2, ',', '.') }} TL</div>
        </div>
    </div>
    </div>

    <div class="mb-6 rounded-2xl bg-white py-6 px-3 md:p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-slate-950">Kasalar</h2>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($cashBoxes as $cashBox)
                @php
                    $cashBoxIncome = $cashBox->transactions->where('type', 'income')->sum('amount');
                    $cashBoxExpense = $cashBox->transactions->where('type', 'expense')->sum('amount');
                    $cashBoxBalance = $cashBoxIncome - $cashBoxExpense;
                @endphp
                <div class="rounded-2xl border border-slate-200 py-4 px-3 md:p-4">
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
        <div class="mt-5 border-t border-slate-200 pt-4 md:hidden">
            <a href="{{ route('cash-boxes.create') }}" class="block rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Kasa Ekle</a>
        </div>
    </div>

@endsection
