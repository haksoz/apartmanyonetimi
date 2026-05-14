@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Gideri İncele</h1>
            <p class="mt-1 text-sm text-slate-500">Gider detayları</p>
        </div>
        <a href="{{ route('expenses.index') }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Geri</a>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm p-6">
        <div class="grid grid-cols-2 gap-6 text-sm text-slate-700">
            <div>
                <div class="text-xs text-slate-500">Ref No</div>
                <div class="font-medium text-slate-900">{{ $expense->reference_number ?? '-' }}</div>
            </div>
            <div>
                <div class="text-xs text-slate-500">Tarih</div>
                <div class="font-medium text-slate-900">{{ $expense->expense_date->format('d.m.Y') }}</div>
            </div>

            <div>
                <div class="text-xs text-slate-500">Dönem</div>
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
                <div class="text-xs text-slate-500">Kategori</div>
                <div class="font-medium text-slate-900">{{ $expense->category }}</div>
            </div>

            <div class="col-span-2">
                <div class="text-xs text-slate-500">Açıklama</div>
                <div class="font-medium text-slate-900">{{ $expense->description ?? '-' }}</div>
            </div>

            <div>
                <div class="text-xs text-slate-500">Tutar</div>
                <div class="font-medium text-slate-900">{{ number_format($expense->amount, 2, ',', '.') }} TL</div>
            </div>

            <div>
                <div class="text-xs text-slate-500">Durum</div>
                <div class="font-medium text-slate-900">{{ $expense->is_paid ? 'Ödendi' : 'Bekliyor' }}</div>
            </div>

            <div class="col-span-2 mt-4 flex justify-end gap-2">
                @unless ($expense->is_paid)
                    <a href="{{ route('expenses.payment.create', $expense) }}" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Ödeme Ekle</a>
                @endunless
                <a href="{{ route('expenses.edit', $expense) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
                <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('Gider kaydı silinsin mi?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Sil</button>
                </form>
            </div>
        </div>
    </div>
@endsection
