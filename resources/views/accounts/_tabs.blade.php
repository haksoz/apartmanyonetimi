@php
    $withOverview = $withOverview ?? true;
    $showTransfer = $showTransfer ?? false;

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
        $tabs[] = ['key' => 'payment', 'label' => '+ Ödeme Ekle', 'url' => route('accounts.supplier-payment.create', $account), 'color' => 'bg-emerald-600 hover:bg-emerald-700'];
    }

    $tabs[] = ['key' => 'edit', 'label' => 'Düzenle', 'url' => route('accounts.edit', $account), 'color' => 'bg-amber-500 hover:bg-amber-600'];
@endphp

{{-- Masaüstü butonlar --}}
<div class="hidden lg:flex flex-wrap gap-2">
    @if ($showTransfer)
        <button type="button" onclick="document.getElementById('transfer-dues-modal').classList.remove('hidden')" class="shrink-0 min-h-[3.5rem] sm:min-h-0 inline-flex items-center justify-center rounded-2xl sm:rounded-xl bg-orange-500 px-5 text-xs sm:text-sm sm:px-4 sm:py-2.5 font-semibold text-white hover:bg-orange-600">
            Borç Devret
        </button>
    @endif

    @foreach ($tabs as $tab)
        <a href="{{ $tab['url'] }}"
           class="min-h-[3.5rem] sm:min-h-0 inline-flex items-center justify-center rounded-2xl sm:rounded-xl px-5 text-xs sm:text-sm sm:px-4 sm:py-2.5 font-semibold text-white {{ $tab['color'] }} {{ $active === $tab['key'] ? 'ring-2 ring-offset-2 ring-slate-950' : '' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>

{{-- Mobil işlemler menüsü --}}
<details class="lg:hidden relative group">
    <summary class="cursor-pointer list-none min-h-[3.5rem] inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 text-xs sm:text-sm font-semibold text-white hover:bg-slate-800 gap-2 focus:outline-none">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
        İşlem
    </summary>
    <div class="absolute right-0 top-full mt-2 w-56 rounded-2xl bg-white p-3 shadow-lg ring-1 ring-slate-100 flex flex-col gap-2 z-20">
        @if ($showTransfer)
            <button type="button" onclick="document.getElementById('transfer-dues-modal').classList.remove('hidden')" class="block w-full text-left min-h-[3.5rem] flex items-center rounded-2xl bg-orange-500 px-5 text-xs sm:text-sm font-semibold text-white hover:bg-orange-600">
                Borç Devret
            </button>
        @endif

        @foreach ($tabs as $tab)
            <a href="{{ $tab['url'] }}"
               class="block w-full text-left min-h-[3.5rem] flex items-center rounded-2xl px-5 text-xs sm:text-sm font-semibold text-white {{ $tab['color'] }} {{ $active === $tab['key'] ? 'ring-2 ring-offset-2 ring-slate-950' : '' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</details>
