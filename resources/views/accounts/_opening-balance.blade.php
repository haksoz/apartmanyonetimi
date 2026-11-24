<section id="opening-balance" class="mt-6 rounded-2xl bg-white p-4 shadow-sm md:p-6" aria-labelledby="opening-balance-heading">
    <h3 id="opening-balance-heading" class="text-base font-semibold text-slate-900">Açılış Bakiyesi</h3>
    <p class="mt-1 text-sm text-slate-500">Hesap açılış tarihindeki borç veya alacaktır. Kasayı etkilemez; hesap bilgilerinden ayrı kaydedilir.</p>
    @if ($errors->openingBalance->any())
        <div role="alert" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">
            @foreach ($errors->openingBalance->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif
    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
        <div><dt class="text-slate-500">Hesap Açılış Tarihi</dt><dd class="mt-1 font-medium text-slate-900">{{ $account->account_opening_date?->format('d.m.Y') ?? 'Henüz girilmemiş' }}</dd></div>
        <div><dt class="text-slate-500">Açılış Tutarı</dt><dd class="mt-1 font-medium text-slate-900">{{ number_format((float) ($openingTransaction?->amount ?? 0), 2, ',', '.') }} TL{{ $openingTransaction ? ($openingTransaction->type === 'debit' ? ' · Borç' : ' · Alacak') : '' }}</dd></div>
        <div><dt class="text-slate-500">Durum</dt><dd class="mt-1 font-medium text-slate-900">{{ $openingLocked ? 'Eşleştirilmiş · Düzenlemeye kapalı' : ($openingTransaction ? 'Eşleştirilmemiş' : 'Sıfır bakiye · Açılış kaydı yok') }}</dd></div>
    </dl>
    @if ($openingTransaction)
        <dl class="mt-4 grid gap-4 border-t border-slate-100 pt-4 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">{{ $openingTransaction->transactionable_type === App\Models\Payment::class ? 'Kullanılan Tutar' : 'Kapatılan Tutar' }}</dt><dd class="mt-1 font-medium">{{ number_format(max(0, (float) $openingTransaction->amount - $openingRemaining), 2, ',', '.') }} TL</dd></div>
            <div><dt class="text-slate-500">Kalan Tutar</dt><dd class="mt-1 font-medium">{{ number_format($openingRemaining, 2, ',', '.') }} TL</dd></div>
        </dl>
    @endif
    @if ($openingLocked)
        <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-800">Açılış bakiyesini veya hesap açılış tarihini değiştirmek için önce açılışa bağlı eşleştirmeleri kaldırın.</p>
        @php
            $openingRoute = match ($openingTransaction->transactionable_type) {
                App\Models\Payment::class => 'payments.show',
                App\Models\Due::class => 'dues.show',
                default => 'expenses.show',
            };
        @endphp
        <a href="{{ route($openingRoute, $openingTransaction->transactionable_id) }}" class="mt-3 inline-block text-sm font-semibold text-slate-700 underline">Bağlı İşlemleri Görüntüle</a>
    @else
        <details class="mt-4" @if ($errors->openingBalance->any()) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-slate-800">{{ $openingTransaction ? 'Açılış Bakiyesini Düzenle' : 'Açılış Bakiyesi Tanımla' }}</summary>
            <form method="POST" action="{{ route('accounts.opening-balance.update', $account) }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                <p class="text-xs text-slate-500">Yukarıdaki kayıtlı hesap açılış tarihi kullanılır. Tarihi değiştirecekseniz önce hesap bilgilerini kaydedin.</p>
                <div>
                    <label for="opening-balance-amount" class="mb-2 block text-sm font-medium text-slate-600">Açılış Tutarı (TL)</label>
                    <input id="opening-balance-amount" name="balance" type="number" min="0.01" step="0.01" required value="{{ old('balance', $openingTransaction?->amount) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm sm:max-w-xs">
                </div>
                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-slate-600">Borç / Alacak</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-4 py-3 text-sm"><input type="radio" name="opening_side" value="debit" required @checked(old('opening_side', $openingTransaction?->type) === 'debit')> Borç · Hesap bize borçlu</label>
                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-4 py-3 text-sm"><input type="radio" name="opening_side" value="credit" required @checked(old('opening_side', $openingTransaction?->type) === 'credit')> Alacak · Biz hesaba borçluyuz</label>
                    </div>
                </fieldset>
                @if (! $account->account_opening_date)
                    <p class="text-sm text-amber-800">Önce hesap bilgilerinden hesap açılış tarihini kaydedin.</p>
                @endif
                <button type="submit" @disabled(! $account->account_opening_date) class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white disabled:opacity-40">{{ $openingTransaction ? 'Açılış Değişikliğini Kaydet' : 'Açılış Bakiyesini Kaydet' }}</button>
            </form>
        </details>
        @if ($openingTransaction)
            <form method="POST" action="{{ route('accounts.opening-balance.destroy', $account) }}" class="mt-4" onsubmit="return confirm('Açılış bakiyesi kaldırılacak ve hesabın açılış bakiyesi 0 TL olacak. Devam edilsin mi?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-semibold text-red-700 underline">Açılış Bakiyesini Kaldır</button>
            </form>
        @endif
    @endif
</section>
