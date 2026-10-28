@php
    $menuAccount = $dueMenuAccount ?? null;
    $menuParams = $menuAccount ? ['account_id' => $menuAccount->id] : [];
@endphp
<a href="{{ route('dues.create', $menuParams) }}" class="{{ ($dueMenuMobile ?? false) ? 'w-full' : 'flex-1 md:flex-none' }} inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
    {{ $menuAccount ? '+ Borçlandır' : 'Borçlandır' }}
</a>
