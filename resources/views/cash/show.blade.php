@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">{{ $transaction->description }}</h1>
            <p class="mt-1 text-sm text-slate-500">Kasa hareketi detayı</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('cash.edit', $transaction) }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Düzenle</a>
            <a href="{{ route('cash.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</a>
        </div>
    </div>

    <div class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm">
        <dl class="grid gap-4 text-sm md:grid-cols-2">
            @if ($transaction->reference_number)
                <div>
                    <dt class="text-slate-500">Referans No</dt>
                    <dd class="mt-1 font-semibold text-blue-700">{{ $transaction->reference_number }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-slate-500">Tür</dt>
                <dd class="mt-1 font-semibold text-slate-950">{{ $transaction->type === 'income' ? 'Gelir' : 'Gider' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Durum</dt>
                <dd class="mt-1 font-semibold {{ $transaction->is_active ? 'text-emerald-600' : 'text-slate-500' }}">{{ $transaction->is_active ? 'Aktif' : 'Pasif' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Tutar</dt>
                <dd class="mt-1 font-semibold text-slate-950">{{ number_format($transaction->amount, 2, ',', '.') }} TL</dd>
            </div>
            <div>
                <dt class="text-slate-500">İşlem Tarihi</dt>
                <dd class="mt-1 font-semibold text-slate-950">{{ $transaction->transaction_date->format('d.m.Y') }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Kategori</dt>
                <dd class="mt-1 font-semibold text-slate-950">{{ $transaction->category?->name ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Kasa</dt>
                <dd class="mt-1 font-semibold text-slate-950">{{ $transaction->cashBox?->name ?? '-' }}</dd>
            </div>
            <div class="md:col-span-2">
                <dt class="text-slate-500">Hesap Bilgisi</dt>
                <dd class="mt-1 font-semibold text-slate-950">{{ $transaction->account?->name ?? 'Hesap bağlantısı yok' }}</dd>
            </div>
        </dl>
    </div>
@endsection
