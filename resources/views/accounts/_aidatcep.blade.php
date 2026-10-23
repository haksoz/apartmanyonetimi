@if (in_array($account->type, [App\Models\Account::TYPE_OWNER, App\Models\Account::TYPE_TENANT]))
    @php
        $portalOpen = (bool) $account->user;
        $portalInvitation = $account->portalInvitations()->whereNull('accepted_at')->latest()->first();
        $portalOffer = $account->relationLoaded('portalOffers')
            ? $account->portalOffers->first()
            : $account->portalOffers()->where('status', 'pending')->first();
        $portalAccepted = $portalOpen && $account->user->isActivated();
        $portalSuspended = $portalAccepted && $account->portalIsSuspended();
        $portalPending = ! $portalAccepted && ($portalOpen || $portalOffer || $portalInvitation);
        $portalStatus = $portalAccepted
            ? ($portalSuspended ? 'Geçici durduruldu' : 'Açık')
            : ($portalPending ? ($portalInvitation?->expires_at->isPast() ? 'Davet süresi doldu' : 'Kabul bekliyor') : 'Kapalı');
        $portalStatusColor = $portalPending || $portalSuspended
            ? 'border-amber-200 bg-amber-50 text-amber-700'
            : ($portalAccepted ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700');
    @endphp
    <div class="flex items-center justify-between gap-3 bg-white px-4 py-3">
        <div class="min-w-0">
            <div class="text-xs text-slate-500">AidatCep Portal Erişimi <span class="inline-flex rounded-md border px-2 py-0.5 font-semibold {{ $portalStatusColor }}">{{ $portalStatus }}</span></div>
            @if (! $portalOpen && ! $account->email)
                <p class="mt-1 text-xs text-slate-400">Önce bu hesaba e-posta yazın.</p>
            @endif
            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('suspended_until')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('action')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('invite')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        @if ($portalOpen && $account->user->isActivated())
            @include('accounts._portal_access_controls', ['account' => $account])
        @elseif ($portalOpen)
            <form method="POST" action="{{ route('accounts.user.destroy', $account) }}" onsubmit="return confirm('Bekleyen davet iptal edilsin mi?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-sm text-amber-700">Kabul bekliyor · Daveti iptal et</button>
            </form>
        @elseif ($portalOffer || $portalInvitation)
            <form method="POST" action="{{ route('accounts.user.destroy', $account) }}" onsubmit="return confirm('Bekleyen davet iptal edilsin mi?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-2">
                    <span class="text-sm font-semibold text-amber-600">{{ $portalInvitation?->expires_at->isPast() ? 'Davet süresi doldu' : 'Kabul bekliyor' }}</span>
                    <span class="relative h-6 w-11 rounded-full bg-amber-500">
                        <span class="absolute top-0.5 left-0.5 h-5 w-5 translate-x-5 rounded-full bg-white"></span>
                    </span>
                </button>
            </form>
        @elseif ($account->email)
            <form method="POST" action="{{ route('accounts.user.store', $account) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Portal erişimini aç</button>
            </form>
        @else
            <button type="button" disabled title="Önce bu hesaba e-posta yazın." class="cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-400">Portal erişimini aç</button>
        @endif
    </div>
    @if ($portalInvitation && ! $portalOpen)
        <div class="min-w-0 bg-white px-4 py-3 md:col-span-2">
            @if (session('invite_url') && (int) session('invite_account_id') === $account->id)
                @include('users._invite_link', ['inviteEmail' => $account->email])
            @else
                <p class="text-xs text-slate-500">Davet henüz kabul edilmedi. Bağlantıyı paylaşmak için yeni davet bağlantısı oluşturabilirsiniz.</p>
            @endif
            <form method="POST" action="{{ route('accounts.user.invite', $account) }}" class="mt-3">
                @csrf
                <button type="submit" class="text-xs font-semibold text-slate-700 underline">Yeni davet bağlantısı oluştur</button>
            </form>
            <p class="mt-1 text-xs text-slate-400">Yeni bağlantı oluşturulduğunda önceki bağlantı geçersiz olur.</p>
        </div>
    @endif
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
