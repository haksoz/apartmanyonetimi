@extends('layouts.app')

@section('title', 'Sipariş Detayı')

@section('content')
    @php
        $payer = $order->user;
        $linkedSubscription = $order->subscription;

        $planLabel = function ($plan) {
            return match ($plan) {
                \App\Models\SubscriptionItem::PLAN_PAID => 'Ücretli',
                \App\Models\SubscriptionItem::PLAN_FREE => 'Ücretsiz',
                default => '—',
            };
        };
        $itemStatusLabel = function ($status) {
            return match ($status) {
                \App\Models\SubscriptionItem::STATUS_PENDING => 'Bekliyor',
                \App\Models\SubscriptionItem::STATUS_ACTIVE => 'Aktif',
                \App\Models\SubscriptionItem::STATUS_CANCELLED => 'İptal',
                default => $status ?: '—',
            };
        };
        $periodText = function ($item) {
            if (! $item?->started_at) {
                return '—';
            }

            return $item->started_at->format('d.m.Y').' – '.($item->expires_at?->format('d.m.Y') ?? 'süresiz');
        };
        $paymentMethodLabel = function ($method) {
            return match ($method) {
                'kredi_kartı' => 'Kredi kartı',
                'nakit' => 'Nakit',
                'havale' => 'Havale / EFT',
                default => $method ?: '—',
            };
        };
        $apartmentNames = $order->items
            ->map(fn ($item) => $item->apartment?->name ?? $item->apartment_name)
            ->filter()
            ->unique()
            ->values();
        $periods = $order->items->map(fn ($item) => $periodText($item))->unique()->values();
        $summaryPeriod = $order->items->isEmpty() ? '—' : ($periods->count() === 1 ? $periods->first() : 'Birden fazla dönem');
        $summaryApartment = $apartmentNames->isNotEmpty()
            ? $apartmentNames->join(', ')
            : ($linkedSubscription?->displayApartmentName() ?: '—');
        $cycleLabel = match ($order->period) {
            \App\Models\UserSubscription::PERIOD_MONTHLY => 'Aylık',
            'yearly' => 'Yıllık',
            default => null,
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.orders.index') }}" class="text-sm font-semibold text-amber-700 hover:text-amber-800">← Siparişlere dön</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Sipariş Detayı</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($order->invoice)
                <a href="{{ route('admin.order-invoices.show', $order->invoice) }}" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Fatura takibi · {{ $order->invoice->trackingNumber() }}</a>
            @elseif ($canInvoice)
                <button type="button" onclick="document.getElementById('invoice-order-modal').showModal()" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Faturala</button>
            @endif
        @unless ($order->trashed())
            <button type="button" onclick="document.getElementById('purge-order-modal').classList.remove('hidden')" class="rounded-xl border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Siparişi pasif sil</button>
        @endunless
        </div>
    </div>

    @if (session('status'))<p class="mb-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</p>@endif
    @if ($order->trashed())<p class="mb-4 rounded-xl bg-slate-100 p-4 text-sm text-slate-700">Bu sipariş pasif silinmiştir. Geçmiş kayıtları görüntülüyorsunuz.</p>@endif
    @if ($canCloseTest)
        <details class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4" @if (old('test_close_action') || $errors->hasAny(['test_close_reason', 'test_checkout'])) open @endif>
            <summary class="cursor-pointer font-semibold text-amber-900">Test girişimini kapat</summary>
            <p class="mt-3 text-sm text-amber-900">Bu işlem PayTR tarafında iptal veya iade yapmaz. Test siparişini kapatır ve yeni denemeye izin verir. Geçmiş kayıtlar korunur; sonradan gelen bildirimler incelemeye alınır.</p>
            <form method="POST" action="{{ route('admin.orders.close-test', $order) }}" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="test_close_action" value="1">
                <label class="block text-sm font-medium">Gerekçe
                    <textarea name="test_close_reason" required maxlength="1000" class="mt-1 w-full rounded-xl border border-slate-300 p-3">{{ old('test_close_reason') }}</textarea>
                </label>
                <label class="block text-sm font-medium">Kendi parolanız
                    <input type="password" name="current_password" required autocomplete="current-password" class="mt-1 w-full rounded-xl border border-slate-300 p-3">
                </label>
                @foreach (['test_checkout', 'test_close_reason', 'current_password'] as $field)
                    @error($field)<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                @endforeach
                <button type="submit" class="rounded-xl bg-amber-800 px-4 py-2 text-sm font-semibold text-white">Test girişimini kapat</button>
            </form>
        </details>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sipariş no</p>
                <p class="mt-1 font-mono text-lg font-bold text-slate-900">{{ $order->order_number ?: '—' }}</p>
                <p class="mt-1 text-xs text-slate-500">Sipariş tarihi: {{ $order->created_at?->format('d.m.Y') ?? '—' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @include('admin.orders.partials.badges', ['order' => $order])
                @if ($order->status === \App\Models\UserSubscription::STATUS_CANCELLED && $order->cancelled_by === \App\Models\UserSubscription::CANCELLED_BY_CUSTOMER)
                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Müşteri iptal etti</span>
                @elseif ($order->status === \App\Models\UserSubscription::STATUS_CANCELLED && $order->cancelled_by === \App\Models\UserSubscription::CANCELLED_BY_ADMIN)
                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Admin iptal etti</span>
                @endif
            </div>
        </div>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-slate-500">Apartman</dt>
                <dd class="font-medium text-slate-900">
                    @php
                        $cardApartment = $linkedSubscription?->apartment ?? ($order->items->count() === 1 ? $order->items->first()?->apartment : null);
                    @endphp
                    @if ($cardApartment)
                        <a href="{{ route('admin.apartments.show', $cardApartment) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">{{ $cardApartment->name }}</a>
                    @else
                        {{ $summaryApartment }}
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Abonelik</dt>
                <dd class="font-medium text-slate-900">
                    @if ($linkedSubscription)
                        <a href="{{ route('admin.subscriptions.show', $linkedSubscription) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">{{ $linkedSubscription->subscription_no ?: 'Abonelik' }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Dönem</dt>
                <dd class="font-medium text-slate-900">{{ $summaryPeriod }}</dd>
            </div>
            <div class="order-last sm:col-span-2 lg:col-span-4 border-t border-slate-100 pt-3">
                <dt class="text-slate-500">Plan</dt>
                <dd class="font-medium text-slate-900">
                    @if ($order->items->isEmpty())
                        Bu kayıtta sipariş kalemi yok.
                    @else
                        <div class="mt-2 space-y-2">
                            @foreach ($order->items as $item)
                                @php
                                    $band = $item->band_label
                                        ?: (($item->band_min_units || $item->band_max_units)
                                            ? trim(($item->band_min_units ?: '1').'–'.($item->band_max_units ?: '∞')).' daire'
                                            : null);
                                @endphp
                                <div class="grid gap-2 rounded-lg bg-slate-50 p-3 lg:grid-cols-2 lg:items-center">
                                    <div>
                                    @if ($order->items->count() > 1)
                                        <div>
                                            @if ($item->apartment)
                                                <a href="{{ route('admin.apartments.show', $item->apartment) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">{{ $item->apartment->name }}</a>
                                            @else
                                                {{ $item->apartment_name ?: '—' }}
                                            @endif
                                            @if ($item->apartmentSubscription)
                                                · <a href="{{ route('admin.subscriptions.show', $item->apartmentSubscription) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">{{ $item->apartmentSubscription->subscription_no ?: 'Abonelik' }}</a>
                                            @endif
                                        </div>
                                    @endif
                                    <div>
                                        {{ $planLabel($item->plan) }}
                                        @if ($cycleLabel)
                                            · {{ $cycleLabel }}
                                        @endif
                                        @if ($item->unit_count)
                                            · {{ $item->unit_count }} daire
                                        @endif
                                    </div>
                                    @if ($band)
                                        <div class="font-normal text-slate-500">{{ $band }}</div>
                                    @endif
                                    <div class="text-xs font-normal text-slate-500">
                                        {{ $itemStatusLabel($item->status) }} · {{ $periodText($item) }}
                                    </div>
                                    </div>
                                    <dl class="grid grid-cols-3 gap-3 text-xs font-normal">
                                        <div>
                                            <dt class="text-slate-500">KDV hariç</dt>
                                            <dd class="mt-1 whitespace-nowrap font-medium tabular-nums text-slate-700">{{ number_format((float) ($item->net_amount ?? $item->amount), 2, ',', '.') }} ₺</dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-500">KDV (%{{ number_format((float) $item->tax_rate, 2, ',', '.') }})</dt>
                                            <dd class="mt-1 whitespace-nowrap font-medium tabular-nums text-slate-700">{{ number_format((float) $item->tax_amount, 2, ',', '.') }} ₺</dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-500">KDV dahil</dt>
                                            <dd class="mt-1 whitespace-nowrap font-semibold tabular-nums text-slate-900">{{ number_format((float) $item->amount, 2, ',', '.') }} ₺</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endforeach
                            @if ($order->items->count() > 1)
                                <p class="text-right text-sm font-semibold text-slate-900">Sipariş toplamı (KDV dahil): {{ number_format((float) $order->price, 2, ',', '.') }} ₺</p>
                            @endif
                        </div>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Müşteri</dt>
                <dd class="font-medium text-slate-900">
                    @if ($payer && ! $payer->isAdminPanelUser())
                        <a href="{{ route('admin.customers.show', $payer) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">{{ $payer->name ?: 'Müşteri detayı' }}</a>
                    @else
                        {{ $payer?->name ?: '—' }}
                    @endif
                    @if ($payer?->email)
                        <div class="font-normal text-slate-500">{{ $payer->email }}</div>
                    @endif
                    @if ($payer?->phone)
                        <div class="font-normal text-slate-500">{{ $payer->phone }}</div>
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    @if ($order->checkout_abandoned_at)
        @if ($order->paymentAttempts->first()?->token_failure_code)
        <p class="mt-3 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">PayTR ödeme ekranı hazırlanamadı. Teknik kayıt müşterinin sipariş listesinde gösterilmez.</p>
        @else
        <p class="mt-3 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">Kullanıcı ödeme sürecinden {{ $order->checkout_abandoned_at->format('d.m.Y H:i') }} tarihinde çıktı. Bu kayıt müşterinin sipariş listesinde gösterilmez. Sağlayıcı sonucu kesinleşene kadar işlem geçmişi korunur.</p>
        @endif
    @endif
    @if ($order->isPending() && ! $order->checkout_abandoned_at && $order->payment_method !== 'kredi_kartı' && $payer)
        <section class="mt-6">
            <h2 class="mb-3 text-lg font-semibold text-slate-900">Sipariş kararı</h2>
            @include('admin.managers.partials.approve', ['manager' => $payer, 'subscription' => $order, 'open' => true])
        </section>
    @endif

    <details class="mt-6 rounded-xl border border-slate-200 bg-white">
        <summary class="cursor-pointer px-6 py-4 text-sm font-semibold text-slate-900">Fatura bilgileri</summary>
        <div class="border-t border-slate-100 px-6 pb-2">
            @include('partials.billing-snapshot', ['subscription' => $order])
        </div>
    </details>

    <details class="mt-3 rounded-xl border border-slate-200 bg-white">
        <summary class="cursor-pointer px-6 py-4 text-sm font-semibold text-slate-900">Sözleşme kabulleri</summary>
        <div class="border-t border-slate-100 px-6 pb-2">
            @include('partials.order-legal-acceptances', ['subscription' => $order])
        </div>
    </details>

    @if ($order->paymentAttempts->where('provider', 'paytr')->isNotEmpty())
        <details class="mt-3 rounded-xl border border-slate-200 bg-white" @if ($order->paymentAttempts->contains('review_required', true)) open @endif>
            <summary class="cursor-pointer px-6 py-4 text-sm font-semibold text-slate-900">PayTR ödeme işlemleri</summary>
            <div class="space-y-3 border-t border-slate-100 p-6">
                @foreach ($order->paymentAttempts->where('provider', 'paytr') as $attempt)
                    @php($notification = $attempt->notifications->first())
                    <article class="rounded-xl border border-slate-200 p-4 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold">{{ $attempt->is_test ? 'Test işlemi' : 'Canlı işlem' }} · {{ match ($attempt->status) { 'succeeded' => 'Başarılı', 'failed' => 'Başarısız', 'cancelled' => 'İptal', 'unknown' => 'Sonuç belirsiz', default => 'Sonuç bekleniyor' } }}</span>
                            <span>{{ number_format($attempt->amount_minor / 100, 2, ',', '.') }} ₺</span>
                        </div>
                        <p class="mt-2 break-all font-mono text-xs text-slate-500">{{ $attempt->merchant_oid }}</p>
                        @if ($attempt->token_failure_code)
                            <p class="mt-2 text-xs text-slate-600">Ödeme ekranı hata kodu: <span class="font-mono">{{ $attempt->token_failure_code }}</span></p>
                        @endif
                        @if ($attempt->review_required)
                            <p class="mt-2 font-semibold text-amber-700">Ödeme incelemesi gerekiyor. Yeni ödeme ve manuel onay/iptal işlemleri durduruldu.</p>
                        @endif
                        @if ($notification)
                            <dl class="mt-2 space-y-1 text-xs text-slate-600">
                                <div><dt class="inline">Son kayıtlı sonuç:</dt> <dd class="inline">{{ $notification->provider_status === 'success' ? 'Başarılı bildirimi' : 'Başarısız bildirimi' }} · {{ $notification->updated_at->format('d.m.Y H:i') }}</dd></div>
                                <div><dt class="inline">Bildirilen tutar:</dt> <dd class="inline">{{ number_format($notification->total_amount_minor / 100, 2, ',', '.') }} {{ $notification->reported_currency ?? '—' }}</dd></div>
                                @if ($notification->provider_status === 'failed' && $notification->failed_reason_code !== null)
                                    <div><dt class="inline">PayTR başarısızlık kodu:</dt> <dd class="inline font-mono">{{ $notification->failed_reason_code }}</dd></div>
                                    @if ($notification->failed_reason_code === '6')
                                        <div>Ödeme süresi doldu veya müşteri ödeme sayfasından ayrıldı.</div>
                                    @endif
                                @endif
                                @if ($notification->reason)<div><dt class="inline">İnceleme kodu:</dt> <dd class="inline font-mono">{{ $notification->reason }}</dd></div>@endif
                            </dl>
                        @else
                            <p class="mt-2 text-xs text-slate-500">Henüz doğrulanmış ödeme bildirimi alınmadı.</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </details>
    @endif

    <details class="mt-3 rounded-xl border border-slate-200 bg-white">
        <summary class="cursor-pointer px-6 py-4 text-sm font-semibold text-slate-900">Tahsilatlar</summary>
        <div class="border-t border-slate-100 px-6 pb-6">
            @if ($order->receipt_reference || $order->receipt_path)
                <p class="mt-3 text-sm text-slate-700">
                    Dekont: {{ $order->receipt_reference ?: '—' }}
                    @if ($order->receipt_path)
                        <a href="{{ Storage::url($order->receipt_path) }}" target="_blank" class="ml-2 font-semibold text-amber-700">Dekontu aç</a>
                    @endif
                </p>
            @endif
            @if ($order->payments->isEmpty())
                <p class="mt-3 text-sm text-slate-500">Bu siparişe bağlı tahsilat yok.</p>
            @else
                <div class="mt-4 space-y-3">
                    @foreach ($order->payments as $payment)
                        <article class="rounded-xl border border-slate-200 p-4">
                            <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <dt class="text-slate-500">Ödeme tarihi</dt>
                                    <dd class="font-medium text-slate-900">{{ $payment->payment_date?->format('d.m.Y') ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Tutar</dt>
                                    <dd class="font-medium text-slate-900">{{ number_format((float) $payment->amount, 2, ',', '.') }} ₺</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Yöntem</dt>
                                    <dd class="font-medium text-slate-900">{{ $paymentMethodLabel($payment->payment_method) }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-slate-500">Referans</dt>
                                    <dd class="font-medium text-slate-900">{{ $payment->reference_code ?: '—' }}</dd>
                                </div>
                            </dl>
                            @if ($payment->notes)
                                <p class="mt-3 text-sm text-slate-600">{{ $payment->notes }}</p>
                            @endif
                            @unless ($order->invoice)
                            <button type="button" onclick="openPurgePayment(@js(route('admin.orders.payments.destroy', [$order, $payment])))" class="mt-3 text-sm font-semibold text-red-700 hover:text-red-800">Ödemeyi pasif sil</button>
                            @endunless
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </details>

    @if ($canInvoice)
        @include('admin.orders.partials.invoice-modal', ['order' => $order, 'modalId' => 'invoice-order-modal'])
    @else
        @error('invoicing')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
    @endif

    <div id="purge-order-modal" @class([
        'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4',
        'hidden' => ! $errors->has('order') && ! ($errors->has('current_password') && ! old('purge_payment_action') && ! old('test_close_action')),
    ])>
        <form method="POST" action="{{ route('admin.orders.destroy', $order) }}" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
            @csrf
            @method('DELETE')
            <h2 class="text-lg font-bold text-slate-900">Siparişi pasif sil</h2>
            <p class="mt-2 text-sm text-slate-600">{{ $order->order_number ?: 'Sipariş' }} listeden kalkar. Tahsilat kaydı olan siparişler silinemez.</p>
            <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Bu işlem PayTR ödemesini iptal etmez; sonradan ödeme bildirimi gelebilir. Sonucu belirsiz girişimler ve tekrar ödeme koruması devam eder. Gelen bildirimler incelemeye alınır.</p>
            <label class="mt-4 block text-sm font-medium text-slate-700">Silme gerekçesi
                <textarea name="deletion_reason" maxlength="1000" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">{{ old('deletion_reason') }}</textarea>
                @error('deletion_reason')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="mt-4 block text-sm font-medium text-slate-700">
                Kendi parolanız
                <input type="password" name="current_password" autocomplete="current-password" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">
                @error('current_password')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                @error('order')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('purge-order-modal').classList.add('hidden')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                <button type="submit" class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Pasif sil</button>
            </div>
        </form>
    </div>

    <div id="purge-payment-modal" @class([
        'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4',
        'hidden' => ! old('purge_payment_action'),
    ])>
        <form method="POST" action="{{ old('purge_payment_action', '#') }}" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
            @csrf
            @method('DELETE')
            <input type="hidden" name="purge_payment_action" value="{{ old('purge_payment_action') }}">
            <h2 class="text-lg font-bold text-slate-900">Ödemeyi pasif sil</h2>
            <p class="mt-2 text-sm text-slate-600">Ödeme listeden kalkar. Kayıt veritabanında durur.</p>
            <label class="mt-4 block text-sm font-medium text-slate-700">
                Kendi parolanız
                <input type="password" name="current_password" autocomplete="current-password" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">
                @error('current_password')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('purge-payment-modal').classList.add('hidden')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                <button type="submit" class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Pasif sil</button>
            </div>
        </form>
    </div>
    <script>
        function openPurgePayment(action) {
            const modal = document.getElementById('purge-payment-modal');
            const form = modal.querySelector('form');
            form.action = action;
            form.querySelector('input[name="purge_payment_action"]').value = action;
            modal.classList.remove('hidden');
        }
    </script>
@endsection
