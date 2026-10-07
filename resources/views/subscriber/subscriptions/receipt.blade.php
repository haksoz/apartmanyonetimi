@extends('layouts.app')

@section('title', 'Sipariş')

@section('content')
    @php
        $periodLabel = $subscription->period === 'yearly' ? 'Yıllık' : 'Aylık';
        $isPending = $subscription->status === App\Models\UserSubscription::STATUS_PENDING;
        $isCard = $subscription->payment_method === 'kredi_kartı';
        $isHavale = ! $isCard;
        $hasProof = $subscription->hasPaymentProof();
        $canSubmitProof = $isPending && ! $hasProof && ! $isCard;
        $canCancel = $isPending && $isHavale && ! $hasProof;
        $reopenPaymentModal = $canSubmitProof && ($errors->has('reference_code') || $errors->has('receipt') || $errors->has('payment_info'));
        $statusLabel = match (true) {
            $isPending && $hasProof => 'Onay bekliyor',
            $isPending => 'Ödeme bekliyor',
            $subscription->status === App\Models\UserSubscription::STATUS_ACTIVE => 'Aktif',
            $subscription->status === App\Models\UserSubscription::STATUS_CANCELLED => 'İptal edildi',
            default => '—',
        };
    @endphp

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-950 sm:text-2xl">Sipariş</h1>
            <p class="mt-1 font-mono text-sm text-slate-500">{{ $subscription->order_number ?: '—' }}</p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2">
            @if ($canSubmitProof)
                <button type="button" onclick="document.getElementById('payment-info-modal').classList.remove('hidden')" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">Ödeme bilgisi gir</button>
            @endif
            <a href="{{ route('subscriber.subscriptions.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Siparişlerime dön</a>
        </div>
    </div>

    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <dl class="divide-y divide-slate-200 text-sm">
            <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-slate-500">Sipariş no</dt>
                <dd class="mt-1 font-mono font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $subscription->order_number ?: '—' }}</dd>
            </div>
            <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-slate-500">Tarih</dt>
                <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $subscription->created_at?->format('d.m.Y H:i') ?? '—' }}</dd>
            </div>
            <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-slate-500">Durum</dt>
                <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $statusLabel }}</dd>
            </div>

            @if ($subscription->items->isNotEmpty())
                @foreach ($subscription->items as $item)
                    @php
                        $address = trim((string) ($item->apartment?->address ?? ''));
                        $district = trim((string) ($item->apartment?->district ?? ''));
                        $province = trim((string) ($item->apartment?->province ?? ''));
                        $place = collect([$district, $province])->filter()->implode(' / ');
                        if ($address !== '' && ($address === $district || $address === $place)) {
                            $address = '';
                        }
                        $addressLine = collect([$address !== '' ? $address : null, $place !== '' ? $place : null])->filter()->implode(' · ');
                    @endphp
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-slate-500">Hizmet</dt>
                        <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $item->apartment_name }} · {{ $item->unit_count }} daire AidatCep Kullanım / {{ $periodLabel }}</dd>
                    </div>
                    @if ($addressLine !== '')
                        <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-slate-500">Adres</dt>
                            <dd class="mt-1 text-slate-900 sm:col-span-2 sm:mt-0">{{ $addressLine }}</dd>
                        </div>
                    @endif
                    @if ($item->apartmentSubscription?->subscription_no)
                        <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-slate-500">Abonelik no</dt>
                            <dd class="mt-1 font-mono font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $item->apartmentSubscription->subscription_no }}</dd>
                        </div>
                    @endif
                @endforeach
            @else
                <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-slate-500">Hizmet</dt>
                    <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $subscription->package?->name ?? 'Eski paket kaydı' }}</dd>
                </div>
                <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-slate-500">Dönem</dt>
                    <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $periodLabel }}</dd>
                </div>
            @endif

            <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-slate-500">Ödeme yöntemi</dt>
                <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $subscription->payment_method === 'kredi_kartı' ? 'Kredi kartı' : 'Havale / EFT' }}</dd>
            </div>
            <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-slate-500">Tutar</dt>
                <dd class="mt-1 font-semibold text-slate-900 sm:col-span-2 sm:mt-0">{{ number_format((float) $subscription->price, 2, ',', '.') }} ₺</dd>
            </div>
            @if ($subscription->receipt_reference)
                <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-slate-500">Dekont / referans</dt>
                    <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $subscription->receipt_reference }}</dd>
                </div>
            @endif
            @if ($subscription->receipt_path)
                <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-slate-500">Dekont</dt>
                    <dd class="mt-1 sm:col-span-2 sm:mt-0">
                        <a href="{{ Storage::url($subscription->receipt_path) }}" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-800">Dekontu görüntüle</a>
                    </dd>
                </div>
            @endif
        </dl>

        @if ($isCard && $isPending && ! $hasProof)
            <p class="border-t border-slate-200 px-4 py-4 text-sm text-slate-600 sm:px-6">Kredi kartı ödemesi henüz aktif değil.</p>
        @endif

        @if ($isPending && $hasProof)
            <p class="border-t border-amber-200 bg-amber-50 px-4 py-4 text-sm font-semibold text-amber-800 sm:px-6">Ödeme bilgileriniz alınmıştır, admin onayı bekleniyor.</p>
        @endif

        @if ($isHavale && $accounts->isNotEmpty())
            <div class="border-t border-slate-200 px-4 py-5 sm:px-6">
                <h2 class="text-base font-semibold text-slate-900">Banka hesap bilgileri</h2>
                <p class="mt-2 text-sm text-slate-600">Havale/EFT açıklama kısmına aşağıdaki sipariş numarasını yazmayı unutmayın.</p>
                <p class="mt-3 flex items-center gap-3 font-mono text-sm font-semibold text-slate-900">
                    <span id="order-number">{{ $subscription->order_number }}</span>
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('order-number').textContent)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Kopyala</button>
                </p>
                <div class="mt-4 divide-y divide-slate-200 rounded-xl border border-slate-200 text-sm">
                    @foreach ($accounts as $account)
                        <div class="px-4 py-4">
                            <p class="font-semibold text-slate-900">{{ $account->name }}</p>
                            <p class="mt-2 text-slate-700">Banka: {{ $account->bank_name }}</p>
                            @if ($account->branch)
                                <p class="mt-1 text-slate-700">Şube: {{ $account->branch }}</p>
                            @endif
                            <p class="mt-1 text-slate-700">Hesap sahibi: {{ $account->account_holder }}</p>
                            @if ($account->account_number)
                                <p class="mt-1 text-slate-700">Hesap no: {{ $account->account_number }}</p>
                            @endif
                            <p class="mt-2 flex flex-wrap items-center gap-3">
                                <span class="font-mono text-slate-900">{{ $account->iban }}</span>
                                <button type="button" onclick="navigator.clipboard.writeText('{{ $account->iban }}')" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">IBAN kopyala</button>
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($canCancel)
            <div class="border-t border-slate-200 px-4 py-4 sm:px-6">
                <form method="POST" action="{{ route('subscriber.subscriptions.cancel', $subscription) }}">
                    @csrf
                    <button class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Siparişi iptal et</button>
                </form>
            </div>
        @endif
    </article>

    @if ($canSubmitProof)
        <div id="payment-info-modal" @class([
            'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4',
            'hidden' => ! $reopenPaymentModal,
        ])>
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="payment-info-title">
                <h2 id="payment-info-title" class="text-lg font-bold text-slate-900">Ödeme bilgisi gir</h2>
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
                        <button type="button" onclick="document.getElementById('payment-info-modal').classList.add('hidden')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            document.getElementById('payment-info-modal').addEventListener('click', function (event) {
                if (event.target === this) this.classList.add('hidden');
            });
        </script>
    @endif
@endsection
