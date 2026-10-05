<details class="rounded-xl border border-slate-200 bg-slate-50 p-4">
    <summary class="cursor-pointer text-sm font-semibold text-emerald-700">Onayla veya reddet</summary>
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.managers.subscription.approve', [$manager, $subscription]) }}" class="space-y-3">
            @csrf
            @method('PATCH')
            <p class="text-sm font-semibold text-slate-900">Siparişi onayla</p>
            @if ($subscription->receipt_reference || $subscription->receipt_path)
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                    <p>Müşteri ödeme bilgisi gönderdi.</p>
                    @if ($subscription->receipt_reference)
                        <p class="mt-1 font-mono text-slate-900">{{ $subscription->receipt_reference }}</p>
                    @endif
                    @if ($subscription->receipt_path)
                        <a href="{{ Storage::url($subscription->receipt_path) }}" target="_blank" class="mt-1 inline-flex font-semibold text-emerald-700">Dekontu görüntüle</a>
                    @endif
                </div>
                @if ($subscription->payment_method === 'kredi_kartı')
                    <p class="text-sm text-amber-800">Kredi kartı ödemesi henüz onaylanamaz.</p>
                @else
                    <input type="hidden" name="payment_method" value="{{ $subscription->payment_method ?: 'havale' }}">
                    <input type="hidden" name="reference_code" value="{{ $subscription->receipt_reference }}">
                    <button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Onayla ve aktif et</button>
                @endif
            @else
                <label class="block text-sm text-slate-700">
                    Ödeme yöntemi
                    <select name="payment_method" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="havale">Havale</option>
                        <option value="nakit">Nakit</option>
                    </select>
                </label>
                <label class="block text-sm text-slate-700">
                    Referans
                    <input type="text" name="reference_code" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                </label>
                <button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Onayla ve aktif et</button>
            @endif
        </form>
        <form method="POST" action="{{ route('admin.managers.subscription.reject', [$manager, $subscription]) }}" class="space-y-3">
            @csrf
            @method('PATCH')
            <p class="text-sm font-semibold text-slate-900">Siparişi reddet</p>
            <textarea name="rejection_notes" rows="3" placeholder="Red nedeni" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            <button class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white">Reddet</button>
        </form>
    </div>
</details>
