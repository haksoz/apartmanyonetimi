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
        $statusLabel = match (true) {
            $isPending && $hasProof => 'Onay bekliyor',
            $isPending => 'Ödeme bekliyor',
            $subscription->status === App\Models\UserSubscription::STATUS_ACTIVE => 'Aktif',
            $subscription->status === App\Models\UserSubscription::STATUS_CANCELLED => 'İptal edildi',
            default => '—',
        };
    @endphp

    <div class="max-w-3xl">
        <a href="{{ route('subscriber.subscriptions.index') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Siparişlerime Dön</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Sipariş</h1>
        <p class="mt-1 font-mono text-sm text-slate-600">{{ $subscription->order_number ?: '—' }}</p>

        <dl class="mt-8 divide-y divide-slate-200 border-y border-slate-200 text-sm">
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-slate-500">Sipariş no</dt>
                <dd class="mt-1 font-mono font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $subscription->order_number ?: '—' }}</dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-slate-500">Tarih</dt>
                <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $subscription->created_at?->format('d.m.Y H:i') ?? '—' }}</dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
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
                    <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                        <dt class="text-slate-500">Hizmet</dt>
                        <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $item->apartment_name }} · {{ $item->unit_count }} daire AidatCep Kullanım / {{ $periodLabel }}</dd>
                    </div>
                    @if ($addressLine !== '')
                        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt class="text-slate-500">Adres</dt>
                            <dd class="mt-1 text-slate-900 sm:col-span-2 sm:mt-0">{{ $addressLine }}</dd>
                        </div>
                    @endif
                    @if ($item->apartmentSubscription?->subscription_no)
                        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt class="text-slate-500">Abonelik no</dt>
                            <dd class="mt-1 font-mono font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $item->apartmentSubscription->subscription_no }}</dd>
                        </div>
                    @endif
                @endforeach
            @else
                <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                    <dt class="text-slate-500">Hizmet</dt>
                    <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $subscription->package?->name ?? 'Eski paket kaydı' }}</dd>
                </div>
                <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                    <dt class="text-slate-500">Dönem</dt>
                    <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $periodLabel }}</dd>
                </div>
            @endif

            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-slate-500">Ödeme yöntemi</dt>
                <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $subscription->payment_method === 'kredi_kartı' ? 'Kredi kartı' : 'Havale / EFT' }}</dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-slate-500">Tutar</dt>
                <dd class="mt-1 font-semibold text-slate-900 sm:col-span-2 sm:mt-0">{{ number_format((float) $subscription->price, 2, ',', '.') }} ₺</dd>
            </div>
            @if ($subscription->receipt_reference)
                <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                    <dt class="text-slate-500">Dekont / referans</dt>
                    <dd class="mt-1 font-medium text-slate-900 sm:col-span-2 sm:mt-0">{{ $subscription->receipt_reference }}</dd>
                </div>
            @endif
            @if ($subscription->receipt_path)
                <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                    <dt class="text-slate-500">Dekont</dt>
                    <dd class="mt-1 sm:col-span-2 sm:mt-0">
                        <a href="{{ Storage::url($subscription->receipt_path) }}" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-800">Dekontu görüntüle</a>
                    </dd>
                </div>
            @endif
        </dl>

        @if ($isCard && $isPending && ! $hasProof)
            <p class="mt-6 text-sm text-slate-600">Kredi kartı ödemesi henüz aktif değil.</p>
        @endif

        @if ($isPending && $hasProof)
            <p class="mt-6 text-sm text-slate-600">Ödeme bilgileriniz alınmıştır, admin onayı bekleniyor.</p>
        @endif

        @if ($isHavale && $accounts->isNotEmpty())
            <h2 class="mt-10 text-base font-semibold text-slate-900">Banka hesap bilgileri</h2>
            <p class="mt-2 text-sm text-slate-600">Havale/EFT açıklama kısmına aşağıdaki sipariş numarasını yazmayı unutmayın.</p>
            <p class="mt-3 flex items-center gap-3 font-mono text-sm font-semibold text-slate-900">
                <span id="order-number">{{ $subscription->order_number }}</span>
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('order-number').textContent)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Kopyala</button>
            </p>
            <div class="mt-4 divide-y divide-slate-200 border-y border-slate-200 text-sm">
                @foreach ($accounts as $account)
                    <div class="py-4">
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
        @endif

        @if ($canSubmitProof)
            <h2 class="mt-10 text-base font-semibold text-slate-900">Ödeme bilgisi gir</h2>
            <p class="mt-2 text-sm text-slate-600">Referans numarası veya dekont dosyasından en az birini girin.</p>
            <form method="POST" action="{{ route('subscriber.subscriptions.payment-info', $subscription) }}" enctype="multipart/form-data" class="mt-4 max-w-lg space-y-4">
                @csrf
                <label class="block text-sm font-medium text-slate-700">
                    Dekont / referans numarası
                    <input type="text" name="reference_code" value="{{ old('reference_code', $subscription->receipt_reference) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm" placeholder="Örn. DEKONT123456">
                    @error('reference_code')<span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm font-medium text-slate-700">
                    Dekont dosyası
                    <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block w-full text-sm text-slate-700 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-semibold hover:file:bg-slate-200">
                    @error('receipt')<span class="mt-1 block text-sm font-normal text-red-600">{{ $message }}</span>@enderror
                </label>
                @error('payment_info')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Kaydet</button>
            </form>
        @endif

        @if ($canCancel)
            <form method="POST" action="{{ route('subscriber.subscriptions.cancel', $subscription) }}" class="mt-6">
                @csrf
                <button class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Siparişi iptal et</button>
            </form>
        @endif
    </div>
@endsection
