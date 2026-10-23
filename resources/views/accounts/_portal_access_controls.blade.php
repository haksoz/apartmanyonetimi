@php $accessDialogId = 'portal-access-'.$account->id; @endphp
<button type="button" onclick="document.getElementById('{{ $accessDialogId }}').showModal()" class="rounded-lg border bg-white px-3 py-2 text-sm font-semibold {{ $account->portalIsSuspended() ? 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' : 'border-red-200 text-red-700 hover:bg-red-50' }}">
    {{ $account->portalIsSuspended() ? 'Portal erişimini aç' : 'Portal erişimini kapat' }}
</button>
<dialog id="{{ $accessDialogId }}" class="w-full max-w-lg rounded-2xl bg-white p-5 text-slate-900 shadow-xl backdrop:bg-black/50">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-lg font-bold">Portal erişimini yönet</h3>
        <button type="button" onclick="this.closest('dialog').close()" aria-label="Pencereyi kapat" class="rounded-lg border px-3 py-1.5 text-sm">Kapat</button>
    </div>
    <p class="mt-2 text-sm text-slate-500">{{ $account->name }} · {{ $account->residence_status_label }}. İşlemler yalnızca bu hesap bağlantısını etkiler.</p>
    @if ($account->portalIsSuspended())
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            <p>{{ $account->portal_suspension_reason }}</p>
            <p class="mt-1">Bitiş: {{ $account->portal_suspended_until->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}. Bu tarihte erişim otomatik açılır.</p>
        </div>
        <form method="POST" action="{{ route('accounts.user.access', $account) }}" class="mt-4 space-y-3">
            @csrf @method('PATCH')
            <input type="hidden" name="action" value="resume">
            <label class="block text-sm">Yeniden açma gerekçesi
                <textarea name="reason" required maxlength="1000" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 p-2"></textarea>
            </label>
            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Erişimi şimdi aç</button>
        </form>
    @else
        <details class="mt-4 rounded-xl border border-slate-200 p-3">
            <summary class="cursor-pointer text-sm font-semibold">Geçici olarak erişimi durdur</summary>
            <p class="mt-2 text-xs text-slate-500">Kullanıcı onayı aranmaz. Kişi panele girdiğinde gerekçeyi ve bitiş tarihini görür. Diğer daireleri etkilenmez.</p>
            <form method="POST" action="{{ route('accounts.user.access', $account) }}" class="mt-3 space-y-3">
                @csrf @method('PATCH')
                <input type="hidden" name="action" value="suspend">
                <label class="block text-sm">Gerekçe
                    <textarea name="reason" required maxlength="1000" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 p-2"></textarea>
                </label>
                <label class="block text-sm">Erişimin yeniden açılacağı tarih ve saat (Türkiye saati)
                    <input type="datetime-local" name="suspended_until" required min="{{ now()->timezone('Europe/Istanbul')->addMinute()->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2">
                </label>
                <button class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Erişimi geçici durdur</button>
            </form>
        </details>
    @endif
    <details class="mt-4 rounded-xl border border-red-200 p-3">
        <summary class="cursor-pointer text-sm font-semibold text-red-700">Yanlış hesap bağlantısını kaldır</summary>
        <p class="mt-2 text-xs text-slate-500">Yalnızca yanlış kişiye bağlanan hesaplar içindir. Kişinin AidatCep hesabı ve mali kayıtlar silinmez. Taşınma veya devir için kiracılığı / malikliği sonlandırın.</p>
        <form method="POST" action="{{ route('accounts.user.destroy', $account) }}" class="mt-3 space-y-3" onsubmit="return confirm('Yanlış hesap bağlantısı kaldırılsın mı? Kullanıcı bilgilendirilecektir.')">
            @csrf @method('DELETE')
            <input type="hidden" name="action" value="unlink">
            <label class="block text-sm">Yanlış bağlantı gerekçesi
                <textarea name="reason" required maxlength="1000" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 p-2"></textarea>
            </label>
            <button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Yanlış bağlantıyı kaldır</button>
        </form>
    </details>
    @php $accessEvents = $account->portalAccessEvents()->with('performer')->latest('id')->limit(20)->get(); @endphp
    @if ($accessEvents->isNotEmpty())
        <details class="mt-4 text-sm">
            <summary class="cursor-pointer font-semibold">İşlem geçmişi</summary>
            @foreach ($accessEvents as $event)
                <div class="mt-2 border-t border-slate-100 pt-2">
                    <p>{{ ['suspend' => 'Geçici durduruldu', 'resume' => 'Yeniden açıldı', 'unlink' => 'Yanlış bağlantı kaldırıldı'][$event->action] ?? $event->action }} · {{ $event->created_at->format('d.m.Y H:i') }}</p>
                    <p class="text-xs text-slate-500">{{ $event->performer?->name }} · {{ $event->reason }}</p>
                </div>
            @endforeach
        </details>
    @endif
</dialog>
