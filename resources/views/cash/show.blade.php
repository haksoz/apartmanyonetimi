@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">{{ $transaction->description }}</h1>
            <p class="mt-1 text-sm text-slate-500">Kasa hareketi detayı</p>
        </div>
        <div class="flex gap-2">
            @if (!$transaction->payment_id)
                <a href="{{ route('cash.edit', $transaction) }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Düzenle</a>
            @else
                <a href="{{ route('payments.show', $transaction->payment_id) }}" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Ödemeye Git</a>
            @endif
            <a href="{{ route('cash.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri</a>
        </div>
    </div>

    <div class="max-w-2xl rounded-2xl bg-white py-6 px-3 md:p-6 shadow-sm">
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
            @if ($transaction->expense)
            <div class="md:col-span-2">
                <dt class="text-slate-500">İlişkili Gider</dt>
                <dd class="mt-1">
                    <a href="{{ route('expenses.show', $transaction->expense) }}" class="inline-flex items-center gap-2 font-semibold text-blue-700 hover:text-blue-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        {{ $transaction->expense->reference_number }} — {{ $transaction->expense->description ?? $transaction->expense->category }}
                    </a>
                </dd>
            </div>
            @endif
            @if ($transaction->payment)
            <div class="md:col-span-2">
                <dt class="text-slate-500">İlişkili Ödeme</dt>
                <dd class="mt-1">
                    <a href="{{ route('payments.show', $transaction->payment) }}" class="inline-flex items-center gap-2 font-semibold text-blue-700 hover:text-blue-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        {{ $transaction->payment->reference_number }} — {{ $transaction->account?->name ?? 'Ödeme' }}
                    </a>
                </dd>
            </div>
            @endif
        </dl>
    </div>
@endsection
