@extends('layouts.app')

@section('content_spacing', 'py-3 sm:py-8')

@section('content')
    @include('accounts._page_heading', ['account' => $account, 'active' => 'statement'])

    <div class="mb-6 rounded-2xl bg-white p-4 shadow-sm md:p-6">
        <div class="mb-5">
            <div class="text-xl font-bold text-slate-900">Tüm Hareketler</div>
            <p class="mt-0.5 text-sm text-slate-500">Seçilen tarih aralığındaki borç, alacak ve bakiye hareketleri.</p>
        </div>
        <form method="GET" action="{{ route('accounts.statement', $account) }}" class="border-t border-slate-100 pt-5">
            <div class="flex flex-row flex-wrap items-end gap-4">
                <div class="flex-1">
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">Başlangıç Tarihi</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-950 focus:outline-none">
                </div>
                <div class="flex-1">
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">Bitiş Tarihi</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-950 focus:outline-none">
                </div>
                <div class="flex w-full flex-nowrap items-center gap-2 md:w-auto">
                    <button type="submit" class="inline-flex min-w-0 flex-1 items-center justify-center whitespace-nowrap rounded-xl bg-slate-950 px-2 py-2.5 text-xs font-semibold text-white hover:bg-slate-800 sm:px-4 sm:text-sm md:flex-none md:px-6">Filtrele</button>
                    @if($dateFrom || $dateTo)
                        <a href="{{ route('accounts.statement', $account) }}" class="inline-flex min-w-0 flex-1 items-center justify-center whitespace-nowrap rounded-xl border border-slate-300 px-2 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 sm:px-4 sm:text-sm md:flex-none">Temizle</a>
                    @endif
                    <a href="{{ route('accounts.statement.export', ['id' => $account->id, 'type' => 'excel', 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="inline-flex min-w-0 flex-1 items-center justify-center gap-1 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-2 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:px-4 sm:text-sm md:flex-none">
                        <svg class="h-4 w-4 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Excel
                    </a>
                    <a href="{{ route('accounts.statement.export', ['id' => $account->id, 'type' => 'pdf', 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="inline-flex min-w-0 flex-1 items-center justify-center gap-1 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-2 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:px-4 sm:text-sm md:flex-none">
                        <svg class="h-4 w-4 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        PDF
                    </a>
                </div>
            </div>
        </form>
    </div>

    @include('accounts._statement-table')

    @if ($transactions->hasPages())
        <div class="mt-6">{{ $transactions->links() }}</div>
    @endif
@endsection
