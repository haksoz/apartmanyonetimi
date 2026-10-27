@php
    $menuAccount = $dueMenuAccount ?? null;
    $menuParams = $menuAccount ? ['account_id' => $menuAccount->id] : [];
    $canChargeOwner = ! $menuAccount || $menuAccount->type === \App\Models\Account::TYPE_OWNER;
@endphp
<details class="relative {{ ($dueMenuMobile ?? false) ? 'w-full' : 'flex-1 md:flex-none' }}">
    <summary class="cursor-pointer list-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 text-center">
        {{ $menuAccount ? '+ Borçlandır' : 'Borçlandır' }} <span aria-hidden="true">▾</span>
    </summary>
    <div class="absolute right-0 top-full z-30 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
        <a href="{{ route('dues.create', array_merge($menuParams, ['target_audience' => 'tenant_priority'])) }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">Daire Sakini</a>
        @if ($canChargeOwner)
            <a href="{{ route('dues.create', array_merge($menuParams, ['target_audience' => 'owner_only'])) }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">Kat Maliki</a>
        @else
            <span aria-disabled="true" class="block rounded-lg px-3 py-2 text-sm text-slate-400">Kat Maliki <span class="block text-xs">Kat maliki hesabından ilerleyin.</span></span>
        @endif
    </div>
</details>
