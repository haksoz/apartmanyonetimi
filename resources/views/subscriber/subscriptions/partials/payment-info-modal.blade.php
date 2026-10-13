<div id="payment-info-modal-{{ $subscription->id }}" @class([
    'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4',
    'hidden' => ! $open,
])>
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="payment-info-title-{{ $subscription->id }}">
        <h2 id="payment-info-title-{{ $subscription->id }}" class="text-lg font-bold text-slate-900">Ödeme bilgisi gir</h2>
        <p class="mt-2 text-sm text-slate-600">Referans numarası veya dekont dosyasından en az birini girin.</p>
        <form method="POST" action="{{ route('subscriber.subscriptions.payment-info', $subscription) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
            @csrf
            <label class="block text-sm font-medium text-slate-700">
                Dekont / referans numarası
                <input type="text" name="reference_code" value="{{ old('reference_code', $subscription->receipt_reference) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Örn. DEKONT123456">
                @error('reference_code')<span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-slate-700">
                Dekont dosyası
                <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block w-full text-sm text-slate-700 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-semibold hover:file:bg-slate-200">
                @error('receipt')<span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span>@enderror
            </label>
            @error('payment_info')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('payment-info-modal-{{ $subscription->id }}').classList.add('hidden')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Kaydet</button>
            </div>
        </form>
    </div>
</div>
<script>
    document.getElementById('payment-info-modal-{{ $subscription->id }}').addEventListener('click', function (event) {
        if (event.target === this) this.classList.add('hidden');
    });
</script>
