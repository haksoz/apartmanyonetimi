@extends('layouts.app')

@section('title', 'Banka Bilgileri')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Banka Bilgileri</h1>
            <p class="text-sm text-slate-500">Havale/EFT ödemelerinde kullanıcılara gösterilecek banka hesaplarını yönetin.</p>
        </div>
        <a href="{{ route('admin.bank-accounts.create') }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Yeni Banka Hesabı</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Hesap Adı</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Banka</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Hesap Sahibi</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">IBAN</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @foreach ($accounts as $account)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $account->name }}</div>
                            <div class="text-xs text-slate-500">{{ $account->branch }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $account->bank_name }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $account->account_holder }}</td>
                        <td class="px-4 py-3 text-slate-700 font-mono">{{ $account->iban }}</td>
                        <td class="px-4 py-3">
                            @if ($account->is_active)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Aktif</span>
                            @else
                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">Pasif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.bank-accounts.edit', $account) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Düzenle</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $accounts->links() }}
    </div>
@endsection
