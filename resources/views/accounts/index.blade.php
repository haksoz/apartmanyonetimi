@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Hesaplar</h1>
            <p class="mt-1 text-sm text-slate-500">Daire sakinleri, tedarikçiler ve apartmanla ilişkili tüm hesaplar.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('accounts.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Hesap Ekle</a>
        </div>
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
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
                        $balance = $credit - $debit;
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

    {{-- Mobile Card View --}}
    <div class="md:hidden space-y-3">
        @forelse ($accounts as $account)
            @php
                $debit = (float) ($account->debit_total ?? 0);
                $credit = (float) ($account->credit_total ?? 0);
                $balance = $credit - $debit;
            @endphp
            <div class="rounded-xl bg-white p-4 shadow-sm border border-slate-200">
                {{-- Header: Name & Type --}}
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="text-lg font-bold text-slate-900">{{ $account->name }}</div>
                        <div class="text-sm text-slate-600">{{ $account->type_label }}</div>
                    </div>
                    @if ($account->unit)
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                            {{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }} No.lu Daire
                        </span>
                    @endif
                </div>

                {{-- Balance Section --}}
                <div class="bg-slate-50 rounded-lg p-3 mb-3">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs text-slate-500">Alacağı</div>
                        <div class="text-sm font-semibold text-emerald-600">{{ number_format($credit, 2, ',', '.') }} TL</div>
                    </div>
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs text-slate-500">Borcu</div>
                        <div class="text-sm font-semibold text-red-600">{{ number_format($debit, 2, ',', '.') }} TL</div>
                    </div>
                    <div class="border-t border-slate-200 pt-2 flex items-center justify-between">
                        <div class="text-xs text-slate-600 font-medium">Bakiye</div>
                        <div class="text-base font-bold {{ $balance < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format(abs($balance), 2, ',', '.') }} TL</div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex gap-2">
                    <a href="{{ route('accounts.show', $account) }}" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Detay</a>
                    <a href="{{ route('accounts.edit', $account) }}" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Düzenle</a>
                    <form method="POST" action="{{ route('accounts.destroy', $account) }}" class="flex-1" onsubmit="return confirm('Hesap silinsin mi?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Sil</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm">
                Henüz hesap yok.
            </div>
        @endforelse
    </div>
@endsection
