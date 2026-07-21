@php
    $baseClass = $mobile
        ? 'block w-full text-left rounded-xl px-4 py-2 text-sm font-semibold text-white'
        : 'rounded-xl px-4 py-2 text-sm font-semibold text-white';
@endphp

<a href="{{ route('accounts.statement', $account) }}" class="{{ $baseClass }} bg-slate-950 hover:bg-slate-800">
    Tüm Hareketler
</a>

@if (in_array($account->type, [App\Models\Account::TYPE_OWNER, App\Models\Account::TYPE_TENANT]))
    <a href="{{ route('dues.create', ['account_id' => $account->id]) }}" class="{{ $baseClass }} bg-slate-600 hover:bg-slate-700">
        + Borçlandır
    </a>
@endif

@if ($account->type === App\Models\Account::TYPE_SUPPLIER)
    <a href="{{ route('expenses.create', ['account_id' => $account->id]) }}" class="{{ $baseClass }} bg-slate-600 hover:bg-slate-700">
        + Gider Ekle
    </a>
    <a href="{{ route('accounts.supplier-payment.create', $account) }}" class="{{ $baseClass }} bg-emerald-600 hover:bg-emerald-700">
        + Ödeme Ekle
    </a>
@else
    <a href="{{ route('payments.create', ['account_id' => $account->id]) }}" class="{{ $baseClass }} bg-emerald-600 hover:bg-emerald-700">
        + Tahsilat Ekle
    </a>
@endif

@if (in_array($account->type, [App\Models\Account::TYPE_OWNER, App\Models\Account::TYPE_TENANT]) && ($account->dues->isNotEmpty() || $importedDues->isNotEmpty()) && $transferableAccounts->isNotEmpty())
    <button type="button" onclick="document.getElementById('transfer-dues-modal').classList.remove('hidden')" class="{{ $baseClass }} bg-orange-500 hover:bg-orange-600">
        Borç Devret
    </button>
@endif

<a href="{{ route('accounts.edit', $account) }}" class="{{ $baseClass }} bg-amber-500 hover:bg-amber-600">
    Düzenle
</a>
