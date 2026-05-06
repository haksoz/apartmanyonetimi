@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Giderler</h1>
            <p class="mt-1 text-sm text-slate-500">Apartman giderleri ve tedarikçi hesap bağlantıları burada yönetilecek.</p>
        </div>
        <a href="{{ route('expenses.create') }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Gider Ekle</a>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr>
                <th class="px-5 py-3"><a href="{{ route('expenses.index', ['sort_by' => 'expense_date', 'sort_direction' => $sortBy === 'expense_date' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 cursor-pointer hover:text-slate-700">Tarih @if ($sortBy === 'expense_date')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                <th class="px-5 py-3"><a href="{{ route('expenses.index', ['sort_by' => 'period_month', 'sort_direction' => $sortBy === 'period_month' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 cursor-pointer hover:text-slate-700">Dönem @if ($sortBy === 'period_month')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                <th class="px-5 py-3"><a href="{{ route('expenses.index', ['sort_by' => 'category', 'sort_direction' => $sortBy === 'category' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 cursor-pointer hover:text-slate-700">Kategori @if ($sortBy === 'category')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                <th class="px-5 py-3"><a href="{{ route('expenses.index', ['sort_by' => 'account_id', 'sort_direction' => $sortBy === 'account_id' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 cursor-pointer hover:text-slate-700">Hesap @if ($sortBy === 'account_id')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                <th class="px-5 py-3 text-right"><a href="{{ route('expenses.index', ['sort_by' => 'amount', 'sort_direction' => $sortBy === 'amount' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center justify-end gap-1 cursor-pointer hover:text-slate-700">Tutar @if ($sortBy === 'amount')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                <th class="px-5 py-3"><a href="{{ route('expenses.index', ['sort_by' => 'is_paid', 'sort_direction' => $sortBy === 'is_paid' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 cursor-pointer hover:text-slate-700">Durum @if ($sortBy === 'is_paid')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                <th class="px-5 py-3 text-right">İşlemler</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="px-5 py-4">{{ $expense->expense_date->format('d.m.Y') }}</td>
                        <td class="px-5 py-4">{{ $expense->period_month?->translatedFormat('F Y') ?? '-' }}</td>
                        <td class="px-5 py-4">{{ $expense->category }}</td>
                        <td class="px-5 py-4">{{ $expense->account?->name ?? '-' }}</td>
                        <td class="px-5 py-4 text-right">{{ number_format($expense->amount, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4">{{ $expense->is_paid ? 'Ödendi' : 'Bekliyor' }}</td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">
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
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-slate-500">Henüz gider kaydı yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
