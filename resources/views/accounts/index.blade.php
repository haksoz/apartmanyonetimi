@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Hesaplar</h1>
            <p class="mt-1 text-sm text-slate-500">Daire sakinleri, tedarikçiler ve apartmanla ilişkili tüm hesaplar.</p>
        </div>
        <a href="{{ route('accounts.create') }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Hesap Ekle</a>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-5 py-3">Daire No</th>
                    <th class="px-5 py-3">Adı Soyadı / Ünvan</th>
                    <th class="px-5 py-3">Tip</th>
                    <th class="px-5 py-3 text-right">Alacağı</th>
                    <th class="px-5 py-3 text-right">Borcu</th>
                    <th class="px-5 py-3 text-right">Bakiye</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($accounts as $account)
                    @php
                        $debit = (float) ($account->debit_total ?? 0);
                        $credit = (float) ($account->credit_total ?? 0);
                        $balance = $credit - $debit; // Match Account model ledger_balance logic
                    @endphp
                    <tr>
                        <td class="px-5 py-4">{{ $account->unit ? str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-' }}</td>
                        <td class="px-5 py-4 font-medium text-slate-950">
                            <a href="{{ route('accounts.show', $account) }}" class="hover:text-slate-700 hover:underline">{{ $account->name }}</a>
                        </td>
                        <td class="px-5 py-4">{{ $account->type_label }}</td>
                        <td class="px-5 py-4 text-right">{{ number_format($credit, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4 text-right">{{ number_format($debit, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4 text-right font-semibold {{ $balance < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format(abs($balance), 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('accounts.show', $account) }}" class="font-semibold text-slate-700 hover:text-slate-950">Detay</a>
                                <a href="{{ route('accounts.edit', $account) }}" class="font-semibold text-slate-700 hover:text-slate-950">Düzenle</a>
                                <form method="POST" action="{{ route('accounts.destroy', $account) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-semibold text-red-600 hover:text-red-700">Sil</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-slate-500">Henüz hesap yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
