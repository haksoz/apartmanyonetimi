@php
    $baseClass = $mobile
        ? 'block w-full text-left rounded-xl px-4 py-2 text-sm font-semibold text-white'
        : 'rounded-xl px-4 py-2 text-sm font-semibold text-white';
    $secondaryClass = $mobile
        ? 'block w-full text-left rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700'
        : 'rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50';
@endphp

<a href="{{ route('accounts.statement.export', ['id' => $account->id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
   class="{{ $baseClass }} bg-emerald-600 hover:bg-emerald-700">
    Excel'e Aktar
</a>

<a href="{{ route('accounts.show', $account) }}" class="{{ $secondaryClass }}">
    Hesaba Dön
</a>
