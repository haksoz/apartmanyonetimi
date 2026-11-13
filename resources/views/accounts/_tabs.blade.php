@php
    $withOverview = $withOverview ?? true;
    $showTransfer = $showTransfer ?? false;
    $compactHeader = $compactHeader ?? false;

    $tabs = [];

    if ($withOverview) {
        $tabs[] = ['key' => 'overview', 'label' => 'Genel Bakış', 'url' => route('accounts.show', $account), 'color' => 'bg-slate-700 hover:bg-slate-800'];
    }

    $tabs[] = ['key' => 'statement', 'label' => 'Tüm Hareketler', 'url' => route('accounts.statement', $account), 'color' => 'bg-slate-950 hover:bg-slate-800'];

    if (in_array($account->type, [App\Models\Account::TYPE_OWNER, App\Models\Account::TYPE_TENANT])) {
        $tabs[] = ['key' => 'due', 'label' => '+ Borçlandır', 'url' => route('dues.create', ['account_id' => $account->id]), 'color' => 'bg-slate-600 hover:bg-slate-700'];
        $tabs[] = ['key' => 'payment', 'label' => '+ Tahsilat Ekle', 'url' => route('payments.create', ['account_id' => $account->id]), 'color' => 'bg-emerald-600 hover:bg-emerald-700'];
    } elseif ($account->type === App\Models\Account::TYPE_SUPPLIER) {
        $tabs[] = ['key' => 'expense', 'label' => '+ Gider Ekle', 'url' => route('expenses.create', ['account_id' => $account->id]), 'color' => 'bg-slate-600 hover:bg-slate-700'];
    }

    $tabs[] = ['key' => 'edit', 'label' => 'Düzenle', 'url' => route('accounts.edit', $account), 'color' => 'bg-amber-500 hover:bg-amber-600'];

    $tabs = array_filter($tabs, fn ($tab) => ! in_array($tab['key'], ['edit', 'payment', 'due', 'statement'], true) || $tab['key'] !== $active);
@endphp

{{-- Masaüstü butonlar --}}
<div class="hidden lg:flex flex-wrap gap-2">
    @if ($showTransfer)
        <button type="button" onclick="document.getElementById('transfer-dues-modal').classList.remove('hidden')" class="shrink-0 min-h-[3.5rem] sm:min-h-0 inline-flex items-center justify-center rounded-2xl sm:rounded-xl bg-orange-500 px-5 text-xs sm:text-sm sm:px-4 sm:py-2.5 font-semibold text-white hover:bg-orange-600">
            Borç Devret
        </button>
    @endif

    @foreach ($tabs as $tab)
        @if ($tab['key'] === 'due')
            @include('dues._create-menu', ['dueMenuAccount' => $account])
        @else
        <a href="{{ $tab['url'] }}"
           class="min-h-[3.5rem] sm:min-h-0 inline-flex items-center justify-center rounded-2xl sm:rounded-xl px-5 text-xs sm:text-sm sm:px-4 sm:py-2.5 font-semibold text-white {{ $tab['color'] }} {{ $active === $tab['key'] ? 'ring-2 ring-offset-2 ring-slate-950' : '' }}">
            {{ $tab['label'] }}
        </a>
        @endif
    @endforeach
</div>

{{-- Mobil işlemler menüsü --}}
<details class="lg:hidden relative group">
    <summary class="inline-flex h-12 cursor-pointer list-none items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300">
        İşlemler
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </summary>
    <div class="absolute right-0 top-full z-20 mt-2 flex w-44 flex-col gap-2 rounded-xl bg-white p-2 shadow-lg ring-1 ring-slate-200">
        @if ($showTransfer)
            <button type="button" onclick="document.getElementById('transfer-dues-modal').classList.remove('hidden')" class="flex w-full items-center justify-start rounded-xl border border-orange-200 px-3 py-2.5 text-left text-sm font-semibold text-orange-700 hover:bg-orange-50">
                Borç Devret
            </button>
        @endif

        @foreach ($tabs as $tab)
            <a href="{{ $tab['url'] }}"
               class="flex w-full items-center justify-start rounded-xl border border-slate-300 px-3 py-2.5 text-left text-sm font-semibold text-slate-700 hover:bg-slate-50 {{ $active === $tab['key'] ? 'ring-2 ring-offset-2 ring-slate-950' : '' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</details>
