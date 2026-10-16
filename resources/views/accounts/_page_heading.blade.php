<div class="mb-6 flex flex-row items-center justify-between gap-3 min-w-0">
    <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-bold text-slate-950">
            <a href="{{ route('accounts.index') }}" class="hover:text-slate-600">Hesaplar</a>
            <span class="font-normal text-slate-400"> / </span>
            <a href="{{ route('accounts.show', $account) }}" class="hover:text-slate-600">
                @if ($account->unit)
                    Daire {{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}
                @else
                    {{ $account->name }}
                @endif
            </a>
            @if (!$account->is_active)
                <span class="ml-2 align-middle inline-flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-1 text-sm font-semibold text-red-600">Pasif</span>
            @endif
        </h1>
        <p class="mt-1 text-base font-semibold text-slate-800">{{ $account->name }} ({{ $account->type_label }})</p>
    </div>

    <div class="flex items-center justify-end gap-2 shrink-0">
        @include('accounts._tabs', ['account' => $account, 'active' => $active, 'withOverview' => false])
    </div>
</div>
