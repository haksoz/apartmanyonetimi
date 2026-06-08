@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('accounts.index') }}" class="hover:text-slate-600">Hesaplar</a>
                <span>/</span>
                <a href="{{ route('accounts.show', $account) }}" class="hover:text-slate-600">{{ $account->type_label }}</a>
            </div>
            <h1 class="text-2xl font-bold text-slate-950">Seçili Giderleri Öde</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $account->name }}
                @if ($account->unit)
                    — Daire No: {{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}
                @endif
                &mdash; {{ $expenses->count() }} gider seçildi
            </p>
        </div>
        <a href="{{ route('accounts.show', $account) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Geri Dön</a>
    </div>

    {{-- Seçili Giderler --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-3">Seçili Giderler</h2>
        <div class="overflow-hidden rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Tarih</th>
                        <th class="px-5 py-3">Açıklama</th>
                        <th class="px-5 py-3 text-right">Tutar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($expenses as $expense)
                        <tr>
                            <td class="px-5 py-3 text-slate-700">{{ $expense->expense_date?->format('d.m.Y') ?? '-' }}</td>
                            <td class="px-5 py-3 text-slate-700">{{ $expense->description ?: 'Gider' }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-slate-900">{{ number_format($expense->amount, 2, ',', '.') }} TL</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50">
                    <tr>
                        <td colspan="2" class="px-5 py-3 text-sm font-semibold text-slate-700">Toplam</td>
                        <td class="px-5 py-3 text-right text-sm font-bold text-slate-900">{{ number_format($totalAmount, 2, ',', '.') }} TL</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Ödeme Formu --}}
    <form method="POST" action="{{ route('accounts.expenses.multi-pay.store', $account) }}" class="rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        <input type="hidden" name="expense_ids" value="{{ $expenses->pluck('id')->join(',') }}">

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Kasa <span class="text-red-500">*</span></label>
                <select name="cash_box_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="">Seçin...</option>
                    @foreach ($cashBoxes as $box)
                        <option value="{{ $box->id }}" {{ old('cash_box_id') == $box->id ? 'selected' : '' }}>{{ $box->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Kategori <span class="text-red-500">*</span></label>
                <select name="category_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="">Seçin...</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Ödeme Tarihi <span class="text-red-500">*</span></label>
                <input type="date" name="payment_date" required value="{{ old('payment_date', date('Y-m-d')) }}"
                    class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-950 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Açıklama</label>
                <input type="text" name="description" value="{{ old('description') }}" placeholder="İsteğe bağlı"
                    class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-950 focus:outline-none">
            </div>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                Öde &mdash; Kaydet ({{ number_format($totalAmount, 2, ',', '.') }} TL)
            </button>
            <a href="{{ route('accounts.show', $account) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</a>
        </div>
    </form>
@endsection
