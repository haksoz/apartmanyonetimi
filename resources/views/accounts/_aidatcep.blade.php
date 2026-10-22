@if (in_array($account->type, [App\Models\Account::TYPE_OWNER, App\Models\Account::TYPE_TENANT]))
    @php
        $portalOpen = (bool) $account->user;
        $portalOffer = $account->relationLoaded('portalOffers')
            ? $account->portalOffers->first()
            : $account->portalOffers()->where('status', 'pending')->first();
    @endphp
    <div class="flex items-center justify-between gap-3 bg-white px-4 py-3">
        <div class="min-w-0">
            <div class="text-xs text-slate-500">AidatCep Portal Erişimi</div>
            @if (! $portalOpen && ! $account->email)
                <p class="mt-1 text-xs text-slate-400">Önce bu hesaba e-posta yazın.</p>
            @endif
            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('invite')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        @if ($portalOpen)
            <form method="POST" action="{{ route('accounts.user.destroy', $account) }}" onsubmit="return confirm('AidatCep portal erişimi kapatılsın mı?')">
                @csrf
                @method('DELETE')
                <button type="submit" role="switch" aria-checked="true" aria-label="AidatCep Portal Erişimi açık" class="inline-flex items-center gap-2">
                    <span class="text-sm font-semibold text-emerald-600">Açık</span>
                    <span class="relative h-6 w-11 rounded-full bg-emerald-500">
                        <span class="absolute top-0.5 left-0.5 h-5 w-5 translate-x-5 rounded-full bg-white"></span>
                    </span>
                </button>
            </form>
        @elseif ($portalOffer)
            <form method="POST" action="{{ route('accounts.user.destroy', $account) }}" onsubmit="return confirm('Bekleyen davet iptal edilsin mi?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-2">
                    <span class="text-sm font-semibold text-amber-600">Kabul bekliyor</span>
                    <span class="relative h-6 w-11 rounded-full bg-amber-500">
                        <span class="absolute top-0.5 left-0.5 h-5 w-5 translate-x-5 rounded-full bg-white"></span>
                    </span>
                </button>
            </form>
        @elseif ($account->email)
            <form method="POST" action="{{ route('accounts.user.store', $account) }}">
                @csrf
                <button type="submit" role="switch" aria-checked="false" aria-label="AidatCep Portal Erişimi kapalı" class="inline-flex items-center gap-2">
                    <span class="text-sm font-semibold text-red-600">Kapalı</span>
                    <span class="relative h-6 w-11 rounded-full bg-red-500">
                        <span class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white"></span>
                    </span>
                </button>
            </form>
        @else
            <div class="inline-flex items-center gap-2" title="Önce bu hesaba e-posta yazın.">
                <span class="text-sm font-semibold text-red-600">Kapalı</span>
                <span class="relative h-6 w-11 rounded-full bg-red-300">
                    <span class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white"></span>
                </span>
            </div>
        @endif
    </div>
    @if ($portalOpen && $account->user && ! $account->user->isActivated())
        <div class="bg-white px-4 py-3">
            <p class="text-xs font-semibold text-slate-900">
                Portal erişimi olan hesabın durumunu
                <a href="{{ route('users.show', $account->user) }}" class="underline">Kullanıcılar</a>
                ekranından takip edin ve yönetin.
            </p>
        </div>
    @endif
@endif
