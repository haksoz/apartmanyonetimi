@extends('layouts.app')

@section('title', 'Ödeme Talimatları')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('subscriber.subscriptions.index') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Siparişlerime Dön</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Ödeme Talimatları</h1>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-900 mb-4">Sipariş Özeti</h2>
            <div class="grid gap-4 sm:grid-cols-2 text-sm">
                <div>
                    <span class="text-slate-500">Sipariş No</span>
                    <p class="font-medium text-slate-900">{{ $subscription->order_number ?? '-' }}</p>
                </div>
                <div>
                    <span class="text-slate-500">Paket</span>
                    <p class="font-medium text-slate-900">{{ $subscription->package->name }}</p>
                </div>
                <div>
                    <span class="text-slate-500">Dönem</span>
                    <p class="font-medium text-slate-900">{{ $subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }}</p>
                </div>
                <div>
                    <span class="text-slate-500">Tutar</span>
                    <p class="font-medium text-slate-900">{{ number_format($subscription->price, 2) }} ₺</p>
                </div>
                <div>
                    <span class="text-slate-500">Ödeme Yöntemi</span>
                    <p class="font-medium text-slate-900">{{ $subscription->payment_method === 'kredi_kartı' ? 'Kredi Kartı' : 'Havale / EFT' }}</p>
                </div>
                <div>
                    <span class="text-slate-500">Durum</span>
                    <p>
                        @if ($subscription->status === App\Models\UserSubscription::STATUS_PENDING)
                            <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Bekliyor</span>
                        @elseif ($subscription->status === App\Models\UserSubscription::STATUS_ACTIVE)
                            <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Aktif</span>
                        @elseif ($subscription->status === App\Models\UserSubscription::STATUS_CANCELLED)
                            <span class="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">İptal Edildi</span>
                        @endif
                    </p>
                </div>
            </div>

            @if ($subscription->receipt_reference)
                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
                    <span class="text-slate-500">Girilen Referans:</span>
                    <span class="font-medium text-slate-900">{{ $subscription->receipt_reference }}</span>
                </div>
            @endif

            @if ($subscription->receipt_path)
                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
                    <span class="text-slate-500">Yüklenen Dekont:</span>
                    <a href="{{ Storage::url($subscription->receipt_path) }}" target="_blank" class="font-medium text-emerald-600 hover:text-emerald-700">Görüntüle</a>
                </div>
            @endif
        </div>

        @if ($subscription->payment_method !== 'kredi_kartı')
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 mb-6">
                Ödeme yaptıktan sonra <a href="{{ route('subscriber.subscriptions.index') }}" class="font-semibold underline hover:text-amber-900">Siparişlerim</a> sayfasından dekont numaranızı veya dekontunuzu yollamayı unutmayın.
            </div>
        @endif

        @if ($subscription->payment_method === 'kredi_kartı')
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 mb-6 text-sm text-amber-800">
                <h2 class="text-lg font-semibold text-amber-900 mb-2">Kredi Kartı Ödemesi</h2>
                <p>Kredi kartı ödeme altyapısı henüz aktif değildir. Ödemenizi tamamladığınızda aşağıdan dekont veya referans bilgisi girebilirsiniz. Havale/EFT ile ödemek isterseniz yeni bir sipariş oluşturabilirsiniz.</p>
            </div>
        @endif

        @if ($subscription->status === App\Models\UserSubscription::STATUS_PENDING)
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Ödeme Bilgisi Gir</h2>

                <form method="POST" action="{{ route('subscriber.subscriptions.payment-info', $subscription) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div>
                        <label for="reference_code" class="block text-sm font-medium text-slate-700 mb-2">Dekont / Referans Numarası</label>
                        <input type="text" id="reference_code" name="reference_code" value="{{ old('reference_code', $subscription->receipt_reference) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Örn. DEKONT123456">
                        @error('reference_code')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="receipt" class="block text-sm font-medium text-slate-700 mb-2">Dekont Dosyası</label>
                        <input type="file" id="receipt" name="receipt" accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-semibold hover:file:bg-slate-200">
                        @error('receipt')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    @error('payment_info')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    <p class="text-xs text-slate-500">Referans numarası veya dekont dosyası alanlarından en az birini doldurmalısınız.</p>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Bilgileri Kaydet</button>
                    </div>
                </form>
            </div>
        @endif

        {{-- Banka Bilgileri Modalı --}}
        @if ($subscription->payment_method !== 'kredi_kartı' && $accounts->isNotEmpty())
            <div id="bank-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display: flex;">
                <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-slate-900">Banka Hesap Bilgilerimiz</h3>
                        <button type="button" onclick="closeBankModal()" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <p class="mb-4 text-sm text-slate-600">Aşağıdaki hesaplarımıza Havale/EFT yaparak ödemenizi gerçekleştirebilirsiniz.</p>

                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 mb-4">
                        <p class="text-sm font-semibold text-emerald-900">Havale/EFT açıklama kısmına aşağıdaki sipariş numarasını yazmayı unutmayın:</p>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <span class="font-mono text-lg font-bold text-slate-900" id="order-number">{{ $subscription->order_number }}</span>
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('order-number').textContent)" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Kopyala</button>
                        </div>
                    </div>

                    <div class="space-y-4">
                        @foreach ($accounts as $account)
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-semibold text-slate-900">{{ $account->name }}</span>
                                    <button type="button" onclick="navigator.clipboard.writeText('{{ $account->iban }}')" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700">IBAN Kopyala</button>
                                </div>
                                <div class="grid gap-2 text-sm text-slate-700">
                                    <div><span class="text-slate-500">Banka:</span> {{ $account->bank_name }}</div>
                                    @if ($account->branch)
                                        <div><span class="text-slate-500">Şube:</span> {{ $account->branch }}</div>
                                    @endif
                                    <div><span class="text-slate-500">Hesap Sahibi:</span> {{ $account->account_holder }}</div>
                                    @if ($account->account_number)
                                        <div><span class="text-slate-500">Hesap No:</span> {{ $account->account_number }}</div>
                                    @endif
                                    <div class="font-mono text-slate-900">{{ $account->iban }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="button" onclick="closeBankModal()" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Tamam, anladım</button>
                    </div>
                </div>
            </div>

            <script>
                function closeBankModal() {
                    document.getElementById('bank-modal').classList.add('hidden');
                    document.getElementById('bank-modal').style.display = 'none';
                }
            </script>
        @endif
    </div>
@endsection
